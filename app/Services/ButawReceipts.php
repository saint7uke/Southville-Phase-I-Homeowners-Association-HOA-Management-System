<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\PaymentDocument;
use App\Models\PaymentDocumentPreview;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ButawReceipts
{
    public function prepare(int $paymentId, User $actor, ?int $expectedHomeownerId = null): string
    {
        OperationalAccess::authorize($actor, 'manage_payments');
        $snapshot = $this->context($paymentId, expectedHomeownerId: $expectedHomeownerId);
        app(OriginalCertificateRenderer::class)->render($snapshot['layout'], $snapshot['values'], true);
        $token = Str::random(64);
        PaymentDocumentPreview::create(['token_hash' => hash('sha256', $token), 'actor_id' => $actor->id, 'payment_id' => $paymentId,
            'snapshot' => $snapshot, 'fingerprint' => $this->fingerprint($snapshot), 'expires_at' => now()->addMinutes(15)]);

        return $token;
    }

    public function read(string $token, User $actor): PaymentDocumentPreview
    {
        OperationalAccess::authorize($actor, 'manage_payments');
        app(ButawReceiptSource::class)->assertSchema();
        $preview = PaymentDocumentPreview::find(hash('sha256', $token));
        $this->assertPreview($preview, $actor);
        if (! hash_equals($preview->fingerprint, $this->fingerprint($this->context($preview->payment_id)))) {
            $this->invalid();
        }

        return $preview;
    }

    public function generate(string $token, int $paymentId, User $actor, ?int $expectedHomeownerId = null): PaymentDocument
    {
        OperationalAccess::authorize($actor, 'manage_payments');
        app(ButawReceiptSource::class)->assertSchema();
        $path = null;
        $sourceChecksum = null;
        try {
            return DB::transaction(function () use ($token, $paymentId, $actor, $expectedHomeownerId, &$path, &$sourceChecksum): PaymentDocument {
                // Same order as payment review: obligation before payment. Serializes different preview tokens.
                $payment = Payment::findOrFail($paymentId);
                $obligation = DuesObligation::whereKey($payment->dues_obligation_id)->lockForUpdate()->firstOrFail();
                $payment = Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
                $payment->setRelation('duesObligation', $obligation->load('duesSetting'));
                $preview = PaymentDocumentPreview::whereKey(hash('sha256', $token))->lockForUpdate()->first();
                $this->assertPreview($preview, $actor);
                if ($preview->payment_id !== $paymentId) {
                    $this->invalid();
                }
                $snapshot = $this->context($paymentId, $payment, $expectedHomeownerId);
                if (! hash_equals($preview->fingerprint, $this->fingerprint($snapshot))) {
                    $this->invalid();
                }
                $sourceChecksum = $snapshot['layout']['sha256'];
                // A locking read bypasses MySQL REPEATABLE READ's pre-lock snapshot.
                if ($existing = PaymentDocument::where('payment_id', $paymentId)->where('source_checksum', $sourceChecksum)->lockForUpdate()->first()) {
                    $this->assertDownload($existing, $actor);

                    return $existing;
                }
                $bytes = app(OriginalCertificateRenderer::class)->render($snapshot['layout'], $snapshot['values']);
                $path = 'payment-documents/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $bytes) || ! Storage::disk('local')->exists($path)) {
                    throw ValidationException::withMessages(['payment_id' => __('The receipt could not be stored. Please try again.')]);
                }
                $document = PaymentDocument::create([
                    'payment_id' => $paymentId, 'payment_document_source_id' => $snapshot['source_id'], 'source_checksum' => $sourceChecksum,
                    'snapshot' => $snapshot, 'path' => $path, 'file_checksum' => hash('sha256', $bytes), 'generated_by' => $actor->id,
                ]);
                $preview->update(['payment_document_id' => $document->id]);

                return $document;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                try {
                    Storage::disk('local')->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            if ($exception instanceof UniqueConstraintViolationException && $sourceChecksum !== null
                && ($existing = PaymentDocument::where('payment_id', $paymentId)->where('source_checksum', $sourceChecksum)->first())) {
                $this->assertDownload($existing, $actor);

                return $existing;
            }
            throw $exception;
        }
    }

    public function assertDownload(PaymentDocument $document, User $actor): void
    {
        abort_unless(! $actor->trashed() && $actor->account_status === 'Active', 403);
        $payment = $document->payment()->with(['homeowner.user', 'payer.user', 'duesObligation.duesSetting', 'recorder'])->first();
        if ($payment) {
            Gate::forUser($actor)->authorize('view', $payment);
        }
        abort_unless($payment && ! $payment->trashed(), 404);
        // Revoked/deleted/reversed payments never retain access merely because a PDF exists.
        app(ButawReceiptEligibility::class)->snapshot($payment);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        abort_unless(hash_equals($document->file_checksum, hash('sha256', Storage::disk('local')->get($document->path))), 409);
    }

    private function context(int $paymentId, ?Payment $payment = null, ?int $expectedHomeownerId = null): array
    {
        $source = app(ButawReceiptSource::class)->approved();
        $payment ??= Payment::find($paymentId);
        if (! $payment) {
            throw ValidationException::withMessages(['payment_id' => __('Select an eligible recorded payment before previewing the receipt.')]);
        }
        $snapshot = app(ButawReceiptEligibility::class)->snapshot($payment, $expectedHomeownerId);
        $layout = $source->manifest;
        if (! hash_equals($layout['font_sha256'], app(ResidencyFont::class)->checksum('butaw'))) {
            throw ValidationException::withMessages(['type' => __('The original receipt font changed. Ask the HOA to review the source configuration.')]);
        }

        return [...$snapshot, 'source_id' => $source->id, 'layout' => $layout];
    }

    private function assertPreview(?PaymentDocumentPreview $preview, User $actor): void
    {
        if (! $preview || $preview->actor_id !== $actor->id || $preview->expires_at->isPast()) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['preview_token' => __('The preview expired or its information changed. Generate a fresh preview before issuing.')]);
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
