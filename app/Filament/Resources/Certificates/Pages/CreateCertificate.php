<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Actions\Certificates\IssueCertificate;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ButawReceiptEligibility;
use App\Services\ButawReceipts;
use App\Services\ButawReceiptSource;
use App\Services\CertificateTypeCatalog;
use App\Services\CohabitationCertificateData;
use App\Services\ResidencyCertificatePreviews;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateCertificate extends CreateRecord
{
    use MapsDomainValidation;

    #[Locked]
    public ?string $previewToken = null;

    #[Locked]
    public ?int $sourceRequestId = null;

    #[Locked]
    public ?int $receiptDocumentId = null;

    protected static string $resource = CertificateResource::class;

    public function mount(): void
    {
        parent::mount();
        if (request()->integer('request')) {
            OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_requests');
            $request = ServiceRequest::where('status', 'Approved')->whereIn('request_type', CertificateTypeCatalog::ALLOWED)->findOrFail(request()->integer('request'));
            $this->sourceRequestId = $request->id;
            $this->form->fill(['document_kind' => 'certificate', 'homeowner_id' => $request->homeowner_id, 'type' => $request->request_type, 'purpose' => Str::limit((string) $request->details, 255, '')]);
            $this->prefillCohabitation();
        } elseif (request()->query('document') === 'butaw') {
            OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
            $this->form->fill(['document_kind' => 'butaw']);
            if (request()->has('payment')) {
                $id = request()->query('payment');
                if (! is_scalar($id) || ! preg_match('/^[1-9][0-9]*$/D', (string) $id) || ! app(ButawReceiptSource::class)->schemaReady()) {
                    $this->addError('data.payment_id', __('Select an eligible recorded payment before previewing the receipt.'));

                    return;
                }
                $payment = Payment::find($id);
                try {
                    if (! $payment) {
                        throw ValidationException::withMessages(['payment_id' => __('Select an eligible recorded payment before previewing the receipt.')]);
                    }
                    app(ButawReceiptEligibility::class)->snapshot($payment);
                    $this->form->fill(['document_kind' => 'butaw', 'receipt_homeowner_id' => $payment->homeowner_id, 'payment_id' => $payment->id]);
                } catch (ValidationException $exception) {
                    $this->addError('data.payment_id', collect($exception->errors())->flatten()->first());
                }
            }
        }
    }

    public function updatedData($value, $key): void
    {
        if (in_array($key, ['homeowner_id', 'type'], true)) {
            $this->prefillCohabitation();
        }
        if ($key === 'cohabitant_two_homeowner_id') {
            $owner = $this->cohabitationOwner();
            $partner = $value && $owner?->property_key ? Homeowner::whereKey($value)->where('property_key', $owner->property_key)->whereKeyNot($owner->id)->whereHas('user')->with('user')->first() : null;
            $request = $this->sourceRequestId ? ServiceRequest::find($this->sourceRequestId) : null;
            $this->data['cohabitant_two'] = $request?->cohabitant_two ?? $partner?->user?->full_name;
            $this->data['cohabitant_two_birth_date'] = $partner?->user?->date_of_birth?->toDateString();
            $this->resetValidation(['data.cohabitant_two', 'data.cohabitant_two_birth_date']);
        }
        if (in_array($key, ['document_kind', 'receipt_homeowner_id'], true)) {
            $this->data['payment_id'] = null;
        }
        if ($key === 'document_kind') {
            $this->data['receipt_homeowner_id'] = null;
        }
        if (in_array($key, ['homeowner_id', 'purpose', 'type', 'document_kind', 'receipt_homeowner_id', 'payment_id', ...CohabitationCertificateData::FIELDS], true)) {
            $this->previewToken = null;
            $this->receiptDocumentId = null;
        }
        if (($this->data['type'] ?? null) === CohabitationCertificateData::TYPE && in_array($key, CohabitationCertificateData::FIELDS, true)) {
            $rules = app(CohabitationCertificateData::class)->rules($this->data);
            $this->validateOnly('data.'.$key, collect($rules)->mapWithKeys(fn ($rule, $field) => ['data.'.$field => $rule])->all(), [],
                collect(app(CohabitationCertificateData::class)->labels())->mapWithKeys(fn ($label, $field) => ['data.'.$field => $label])->all());
            if (in_array($key, ['cohabitant_one_birth_date', 'cohabitant_two_birth_date'], true) && filled($this->data['years_together'] ?? null)) {
                $this->validateOnly('data.years_together', ['data.years_together' => $rules['years_together']]);
            }
        }
    }

    public function cohabitationOwner(): ?Homeowner
    {
        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_requests');

        $owner = filled($this->data['homeowner_id'] ?? null) ? Homeowner::whereHas('user')->with('user')->find($this->data['homeowner_id']) : null;
        // Livewire public helper return values are callable by clients: expose only document data.
        $owner?->setVisible(['id', 'user_id', 'house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix', 'property_key', 'full_address', 'user']);
        $owner?->user?->setVisible(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'full_name']);

        return $owner;
    }

    public function prefillCohabitation(): void
    {
        if (($this->data['type'] ?? null) !== CohabitationCertificateData::TYPE) {
            return;
        }
        $owner = $this->cohabitationOwner();
        $this->data = [...$this->data, ...array_fill_keys(CohabitationCertificateData::FIELDS, null)];
        $this->resetValidation(array_map(fn ($field) => 'data.'.$field, CohabitationCertificateData::FIELDS));
        if ($owner) {
            $request = $this->sourceRequestId ? ServiceRequest::findOrFail($this->sourceRequestId) : null;
            $this->data = [...$this->data, ...app(CohabitationCertificateData::class)->defaults($owner, $request)];
        }
    }

    public function registeredBirthDate(string $person): ?string
    {
        if (($this->data['type'] ?? null) !== CohabitationCertificateData::TYPE) {
            return null;
        }
        $owner = $this->cohabitationOwner();
        if ($person === 'one') {
            return $owner && ($this->data['cohabitant_one'] ?? null) === $owner->user->full_name ? $owner->user->date_of_birth?->toDateString() : null;
        }
        $id = $this->data['cohabitant_two_homeowner_id'] ?? null;
        $partner = $owner && $owner->property_key && $id ? Homeowner::whereKey($id)->where('property_key', $owner->property_key)->whereHas('user')->with('user')->first() : null;

        return $partner?->user?->date_of_birth?->toDateString();
    }

    /** Read-only presentation data; never accept receipt values from browser state. */
    public function receiptDetails(): ?array
    {
        if (($this->data['document_kind'] ?? null) !== 'butaw'
            || blank($this->data['receipt_homeowner_id'] ?? null) || blank($this->data['payment_id'] ?? null)) {
            return null;
        }
        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
        $payment = app(ButawReceiptEligibility::class)->query()
            ->where('homeowner_id', (int) $this->data['receipt_homeowner_id'])->find($this->data['payment_id']);
        if (! $payment) {
            return null;
        }
        try {
            $snapshot = app(ButawReceiptEligibility::class)->snapshot($payment, (int) $this->data['receipt_homeowner_id']);
        } catch (ValidationException) {
            return null;
        }

        return [...$snapshot, 'payment_date' => $payment->payment_date->format('F j, Y'), 'payment_method' => $payment->payment_method];
    }

    public function preparePreview(): void
    {
        $this->previewToken = null;
        try {
            if (($this->data['document_kind'] ?? null) === 'butaw') {
                OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
                $data = $this->form->getState();
                $this->previewToken = $this->runValidatedDomainAction(fn () => app(ButawReceipts::class)->prepare((int) $data['payment_id'], app(AuthenticatedActor::class)->user(), (int) $data['receipt_homeowner_id']), 'data');

                return;
            }
            $this->previewToken = $this->runValidatedDomainAction(fn () => app(ResidencyCertificatePreviews::class)->prepare(
                $this->form->getState(), app(AuthenticatedActor::class)->user(), $this->sourceRequestId
            ), 'data');
        } catch (ValidationException $exception) {
            if (($this->data['document_kind'] ?? null) === 'butaw') {
                throw $this->receiptValidationException($exception);
            }
            throw $exception;
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([($this->data['document_kind'] ?? null) === 'butaw' ? 'data.payment_id' : 'data.type' => __('The certificate preview could not be prepared. Please try again or contact the administrator.')]);
        }
    }

    protected function getFormActions(): array
    {
        return [Action::make('previewResidency')->label(fn (): string => ($this->data['document_kind'] ?? null) === 'butaw' ? __('Preview monthly dues receipt') : __('Preview completed certificate'))
            ->extraAttributes(['class' => 'hoa-receipt-primary-action'])
            ->visible(fn () => ($this->data['document_kind'] ?? null) === 'butaw' || in_array($this->data['type'] ?? null, CertificateTypeCatalog::ALLOWED, true))
            ->disabled(fn (): bool => ($this->data['document_kind'] ?? null) === 'butaw' && (blank($this->data['receipt_homeowner_id'] ?? null) || blank($this->data['payment_id'] ?? null)))
            ->action(fn () => $this->preparePreview()),
            $this->getCreateFormAction()->label(__('Issue certificate'))
                ->extraAttributes(['class' => 'hoa-receipt-primary-action'])
                ->visible(fn () => ($this->data['document_kind'] ?? null) !== 'butaw')
                ->disabled(fn () => in_array($this->data['type'] ?? null, CertificateTypeCatalog::ALLOWED, true) && $this->previewToken === null),
            Action::make('generateReceipt')->label(__('Generate monthly dues receipt'))->requiresConfirmation()
                ->extraAttributes(['class' => 'hoa-receipt-primary-action'])
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes(['class' => 'hoa-receipt-primary-action']))
                ->visible(fn () => ($this->data['document_kind'] ?? null) === 'butaw')
                ->disabled(fn () => $this->previewToken === null || $this->receiptDocumentId !== null)
                ->action(fn () => $this->generateReceipt()),
            $this->getCancelFormAction()];
    }

    public function generateReceipt(): void
    {
        try {
            OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
            $data = $this->form->getState();
            $document = $this->runValidatedDomainAction(fn () => app(ButawReceipts::class)->generate($this->previewToken ?? '', (int) ($data['payment_id'] ?? 0), app(AuthenticatedActor::class)->user(), (int) $data['receipt_homeowner_id']), 'data');
            $this->receiptDocumentId = $document->id;
            Notification::make()->success()->title(__('Monthly dues receipt is ready.'))->send();
        } catch (ValidationException $exception) {
            throw $this->receiptValidationException($exception);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.payment_id' => __('The receipt could not be generated. Please try again or contact the administrator.')]);
        }
    }

    private function receiptValidationException(ValidationException $exception): ValidationException
    {
        if (collect(array_keys($exception->errors()))->every(fn (string $field): bool => in_array($field, ['data.receipt_homeowner_id', 'data.payment_id'], true))) {
            return $exception;
        }
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $target = in_array($field, ['receipt_homeowner_id', 'data.receipt_homeowner_id'], true) ? 'data.receipt_homeowner_id' : 'data.payment_id';
            $errors[$target] = [...($errors[$target] ?? []), ...$messages];
        }

        return ValidationException::withMessages($errors);
    }

    protected function getRedirectUrl(): string
    {
        return CertificateResource::getUrl('index');
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AuthenticatedActor::class)->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        try {
            if ($this->sourceRequestId !== null) {
                return app(IssueCertificate::class)->fromApprovedRequest(ServiceRequest::findOrFail($this->sourceRequestId), $actor, $data['purpose'] ?? null, $this->previewToken, $data);
            }
            $data['preview_token'] = $this->previewToken;

            return app(IssueCertificate::class)->handle($data, $actor);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['data.type' => __('The certificate could not be issued. Please try again or contact the administrator.')]);
        }
    }
}
