<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Payment;
use App\Models\PaymentDocument;
use App\Models\User;
use App\Support\OperationalAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class PaymentReceiptResolver
{
    private ?bool $schemaReady = null;

    private ?bool $documentsReady = null;

    public function schemaReady(): bool
    {
        // Scoped to one application request, never a cross-request availability cache.
        return $this->schemaReady ??= app(ButawReceiptSource::class)->schemaReady();
    }

    public function withReceipts(Builder $query): Builder
    {
        return $query->with(['homeowner.user', 'payer.user', 'duesObligation.duesSetting', 'duesSetting', 'recorder'])
            ->when($this->documentsReady(), fn (Builder $q) => $q->with('latestReceiptDocument'));
    }

    private function documentsReady(): bool
    {
        // Reading a finalized file does not depend on temporary preview or generation tables.
        return $this->documentsReady ??= $this->schemaReady()
            || (Schema::hasTable('payment_documents') && Schema::hasColumn('payments', 'property_snapshot'));
    }

    /** saved / generate / detailed / setup / unavailable; this never renders or creates a PDF. */
    public function state(Payment $payment): string
    {
        if ($payment->trashed() || ! in_array($payment->review_status, ['Approved', 'Recorded'], true)
            || ! in_array($payment->status, ['Paid', 'Partial'], true)) {
            return 'unavailable';
        }
        if (! $this->documentsReady()) {
            return 'setup';
        }
        try {
            app(ButawReceiptEligibility::class)->snapshot($payment);
        } catch (ValidationException) {
            return 'detailed';
        }

        $payment->loadMissing('latestReceiptDocument');

        return $payment->latestReceiptDocument ? 'saved' : ($this->schemaReady() ? 'generate' : 'setup');
    }

    public function generationUrl(Payment $payment, ?User $actor): ?string
    {
        if (! OperationalAccess::allows($actor, 'manage_payments')) {
            return null;
        }
        $panel = Filament::getCurrentPanel()?->getId() ?? Auth::getDefaultDriver();
        if (! in_array($panel, ['admin', 'staff'], true)) {
            $panel = $actor->hasRole('hoa_admin') ? 'admin' : 'staff';
        }

        return CertificateResource::getUrl('create', ['document' => 'butaw', 'payment' => $payment->id], panel: $panel);
    }

    public function documentUrl(Payment $payment, bool $inline = false): ?string
    {
        return $this->state($payment) === 'saved'
            ? route('certificates.butaw.download', ['paymentDocument' => $payment->latestReceiptDocument->id, ...($inline ? ['view' => 1] : [])])
            : null;
    }

    public function historyQuery(): Builder
    {
        return PaymentDocument::query()->whereHas('payment', fn (Builder $query) => $query
            ->whereIn('payments.id', app(ButawReceiptEligibility::class)->query()->select('payments.id')))
            ->with(['payment' => fn ($payment) => $this->withReceipts($payment->getQuery())])
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    public function forPayment(Payment $payment, User $actor, bool $inline = false): Response
    {
        $payment = $this->withReceipts(Payment::query())->findOrFail($payment->id);
        $this->authorizeView($payment, $actor);
        $state = $this->state($payment);
        abort_if($state === 'unavailable', 404);
        if ($state === 'saved') {
            return $this->forDocument($payment->latestReceiptDocument, $actor, $inline);
        }
        if ($state === 'detailed') {
            $payment->loadMissing('reviewer');
            $pdf = Pdf::loadView('pdf.payment-receipt', ['payment' => $payment])->setPaper('a4')
                ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
            $name = "hoa-receipt-{$payment->or_number}.pdf";
            $response = $inline ? $pdf->stream($name) : $pdf->download($name);
            $response->headers->add($this->headers());

            return $response;
        }
        if ($state === 'setup') {
            return $this->unavailable(__(ButawReceiptSource::SETUP_MESSAGE), 409);
        }
        // A download GET may link to generation, never generate or silently substitute HTML.
        try {
            $source = app(ButawReceiptSource::class)->approved();
            if (! hash_equals($source->manifest['font_sha256'], app(ResidencyFont::class)->checksum('butaw'))) {
                throw ValidationException::withMessages(['type' => __('The original receipt font changed. Ask the HOA to review the source configuration.')]);
            }
        } catch (ValidationException $exception) {
            return $this->unavailable(collect($exception->errors())->flatten()->first(), 409, $this->generationUrl($payment, $actor));
        } catch (\JsonException $exception) {
            report($exception);

            return $this->unavailable(__('The original receipt source configuration requires administrator review.'), 409);
        }
        $url = $this->generationUrl($payment, $actor);
        if ($url !== null) {
            return redirect()->to($url)->withHeaders($this->headers());
        }

        return $this->unavailable(__('The HOA has not generated this monthly receipt yet. Please contact the HOA.'), 409);
    }

    public function forDocument(PaymentDocument $document, User $actor, bool $inline = false): Response
    {
        try {
            app(ButawReceipts::class)->assertDownload($document, $actor);
        } catch (ValidationException) {
            return $this->unavailable(__('This receipt is no longer available for this payment.'), 404);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return $this->unavailable(__('The saved receipt is missing, changed, or unavailable. Please contact the administrator.'), $exception->getStatusCode());
        } catch (FilesystemException $exception) {
            report($exception);

            return $this->unavailable(__('The saved receipt is missing, changed, or unavailable. Please contact the administrator.'), 503);
        }
        $name = 'resibo-butaw-'.$document->id.'.pdf';

        return $inline
            ? Storage::disk('local')->response($document->path, $name, $this->headers(), 'inline')
            : Storage::disk('local')->download($document->path, $name, $this->headers());
    }

    private function authorizeView(Payment $payment, User $actor): void
    {
        abort_unless(! $actor->trashed() && $actor->account_status === 'Active', 403);
        Gate::forUser($actor)->authorize('view', $payment);
    }

    private function unavailable(string $message, int $status, ?string $generationUrl = null): Response
    {
        return response()->view('errors.receipt-unavailable', compact('message', 'generationUrl'), $status, $this->headers());
    }

    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
    }
}
