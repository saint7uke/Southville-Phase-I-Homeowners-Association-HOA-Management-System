<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certificates\Schemas;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Certificate;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Services\ButawReceiptEligibility;
use App\Services\ButawReceiptSource;
use App\Services\CertificateTypeCatalog;
use App\Services\CohabitationCertificateData;
use App\Services\CohabitationCertificateSource;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use App\Support\PropertyIdentity;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_kind')->label(__('Document'))->options(function (): array {
                $actor = app(AuthenticatedActor::class)->user();

                return [
                    ...(OperationalAccess::allows($actor, 'manage_requests') ? ['certificate' => __('Certificate')] : []),
                    ...(OperationalAccess::allows($actor, 'manage_payments') ? ['butaw' => __(ButawReceiptSource::LABEL)] : []),
                ];
            })->default(fn (): string => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_requests') ? 'certificate' : 'butaw')->live()->required()->dehydrated(false)->visible(fn (string $operation): bool => $operation !== 'edit'),
            Group::make()->schema([
                Select::make('receipt_homeowner_id')->label(__('Homeowner / liable property account'))
                    ->searchable()->preload()->live()->required()->rules(['integer', 'min:1'])
                    ->options(fn (): array => self::receiptHomeowners())
                    ->getSearchResultsUsing(fn (string $search): array => self::receiptHomeowners($search))
                    ->getOptionLabelUsing(function ($value): ?string {
                        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
                        $homeowner = app(ButawReceiptEligibility::class)->homeowners()->find($value);

                        return $homeowner ? self::homeownerLabel($homeowner) : null;
                    })
                    ->helperText(__('Select the property account responsible for the dues. The actual payer may be a linked tenant or co-owner.')),
                Select::make('payment_id')->label(__('Accepted monthly Cash payment'))
                    ->searchable()->preload()->live()->required()->rules(['integer', 'min:1'])
                    ->disabled(fn (callable $get): bool => blank($get('receipt_homeowner_id')))
                    ->options(fn (callable $get): array => self::receiptPayments((int) $get('receipt_homeowner_id')))
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => self::receiptPayments((int) $get('receipt_homeowner_id'), $search))
                    ->getOptionLabelUsing(function ($value, callable $get): ?string {
                        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
                        $payment = app(ButawReceiptEligibility::class)->query()->where('homeowner_id', (int) $get('receipt_homeowner_id'))->find($value);

                        return $payment ? self::paymentLabel($payment) : null;
                    })
                    ->noSearchResultsMessage(__('No eligible monthly Cash payments found. Record or verify a qualifying payment first.'))
                    ->helperText(fn (): string => __(app(ButawReceiptSource::class)->schemaReady()
                        ? 'Only accepted 2026 monthly Cash payments without penalties and with verified property and collector details are eligible. Other payments keep their detailed receipt.'
                        : ButawReceiptSource::SETUP_MESSAGE)),
                ViewField::make('receipt_details')->view('filament.resources.certificates.receipt-details')->dehydrated(false)->columnSpanFull()
                    ->viewData(fn ($livewire): array => [
                        'details' => $livewire->receiptDetails(),
                        'hasHomeowner' => filled($livewire->data['receipt_homeowner_id'] ?? null),
                        'hasPayments' => filled($livewire->data['receipt_homeowner_id'] ?? null) && app(ButawReceiptEligibility::class)->query()->where('homeowner_id', (int) $livewire->data['receipt_homeowner_id'])->exists(),
                        'schemaReady' => app(ButawReceiptSource::class)->schemaReady(),
                        'sourceConfirmed' => app(ButawReceiptSource::class)->isConfirmed(),
                        'recordPaymentUrl' => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments') ? PaymentResource::getUrl('create') : null,
                    ]),
            ])->columns(2)->columnSpanFull()->visible(fn (callable $get): bool => $get('document_kind') === 'butaw' && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments')),
            Group::make()->schema([
                Select::make('homeowner_id')->relationship('homeowner', 'id', modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('user')->with('user:id,first_name,middle_name,last_name,suffix')->when(! OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_requests'), fn (Builder $q) => $q->whereRaw('1 = 0')))->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name.' | '.$record->full_address)->searchable()->preload()->live()->required()
                    ->disabled(fn (string $operation, $livewire): bool => $operation === 'edit' || ($livewire->sourceRequestId ?? null) !== null)
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),
                Select::make('type')
                    ->options(fn (?Certificate $record): array => $record ? [$record->type => $record->type] : app(CertificateTypeCatalog::class)->activeOptions())
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->default('Certificate of Residency')->live()
                    ->disabled(fn (string $operation, $livewire): bool => $operation === 'edit' || ($livewire->sourceRequestId ?? null) !== null)
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                    ->required(),
                TextInput::make('custom_type')
                    ->label(__('Custom certificate type'))
                    ->helperText(__('Enter a distinct certificate type. Capitalization and spacing differences do not create a new type.'))
                    ->maxLength(64)
                    ->required(fn (callable $get): bool => $get('type') === CertificateTypeCatalog::OTHER_VALUE)
                    ->visible(false)
                    ->dehydrated(fn (callable $get): bool => $get('type') === CertificateTypeCatalog::OTHER_VALUE),
                TextInput::make('purpose')->maxLength(255)->live(onBlur: true)->readOnly(fn (string $operation): bool => $operation === 'edit')
                    ->dehydratedWhenHidden()
                    ->visible(fn (callable $get): bool => $get('type') !== CohabitationCertificateData::TYPE),
                Placeholder::make('registered_phase')->label(__('Registered phase'))
                    ->content(fn (callable $get) => OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_requests') && ($resident = Homeowner::find($get('homeowner_id'))) ? PropertyIdentity::phase($resident) : 'Phase 1 -')
                    ->visible(fn (callable $get): bool => in_array($get('type'), CertificateTypeCatalog::ALLOWED, true)),
                ViewField::make('completed_preview')->view('filament.resources.certificates.completed-preview')->dehydrated(false)->columnSpanFull()
                    ->visible(fn ($livewire): bool => ($livewire->previewToken ?? null) !== null),
                Group::make()->schema([
                    ViewField::make('cohabitation_source_status')->view('filament.resources.certificates.cohabitation-source-status')->dehydrated(false)
                        ->viewData(fn (): array => ['status' => app(CohabitationCertificateSource::class)->setupStatus(), 'showReviewLink' => false])->columnSpanFull()
                        ->visible(fn (): bool => app(CohabitationCertificateSource::class)->setupStatus()['state'] !== 'confirmed'),
                    TextInput::make('cohabitant_one')->label(__('First resident full name'))->readOnly()->required()->maxLength(160)->live(debounce: 500),
                    DatePicker::make('cohabitant_one_birth_date')->label(__('First resident birth date'))->format('Y-m-d')->maxDate(today()->subDay())->required()
                        ->readOnly(fn ($livewire): bool => $livewire->registeredBirthDate('one') !== null)->live(debounce: 500),
                    Placeholder::make('cohabitation_address')->label(__('Registered shared address'))
                        ->content(fn ($livewire): string => ($owner = $livewire->cohabitationOwner()) ? $owner->full_address : __('Select a homeowner first.'))->columnSpanFull(),
                    Select::make('cohabitant_two_homeowner_id')->label(__('Registered second resident'))->placeholder(__('Enter verified details manually'))
                        ->searchable()->preload()->live()->options(function ($livewire): array {
                            $owner = $livewire->cohabitationOwner();

                            return $owner && $owner->property_key ? Homeowner::where('property_key', $owner->property_key)->whereKeyNot($owner->id)->whereHas('user')->with('user')->get()
                                ->mapWithKeys(fn ($partner) => [$partner->id => $partner->user->full_name])->all() : [];
                        })->helperText(__('Select an existing same-property resident, or enter verified partner details without creating an account.'))->columnSpanFull(),
                    TextInput::make('cohabitant_two')->label(__('Second resident full name'))->required()->maxLength(160)->live(debounce: 500)
                        ->readOnly(fn (callable $get, $livewire): bool => filled($get('cohabitant_two_homeowner_id')) || $livewire->sourceRequestId !== null),
                    DatePicker::make('cohabitant_two_birth_date')->label(__('Second resident birth date'))->format('Y-m-d')->maxDate(today()->subDay())->required()
                        ->readOnly(fn ($livewire): bool => $livewire->registeredBirthDate('two') !== null)->live(debounce: 500),
                    TextInput::make('years_together')->label(__('Completed years living together'))->required()->numeric()->minValue(0)->step(1)->live(debounce: 500)
                        ->helperText(__('Enter verified completed years. Do not infer this from account move-in dates.')),
                    Select::make('requester')->label(__('Requesting person'))->required()->default('one')->live()->options(fn (callable $get): array => [
                        'one' => $get('cohabitant_one') ?: __('First resident full name'), 'two' => $get('cohabitant_two') ?: __('Second resident full name')]),
                    Placeholder::make('cohabitation_issued_on')->label(__('Issuance date'))->content(fn () => today()->format('F j, Y')),
                ])->columns(2)->columnSpanFull()->visible(fn (callable $get, string $operation): bool => $operation !== 'edit' && $get('type') === CohabitationCertificateData::TYPE),
                DatePicker::make('expires_at')
                    ->default(fn () => today()->endOfYear())
                    ->displayFormat('F j, Y')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(__('Automatically set to the last day of the current year.')),
            ])->columns(2)->columnSpanFull()->visible(fn (callable $get): bool => $get('document_kind') !== 'butaw' && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_requests')),
            ViewField::make('receipt_preview')->view('filament.resources.certificates.receipt-preview')->dehydrated(false)->columnSpanFull()
                ->visible(fn (callable $get, $livewire): bool => $get('document_kind') === 'butaw' && (($livewire->previewToken ?? null) !== null || ($livewire->receiptDocumentId ?? null) !== null)),
        ])->columns(2)->disabled(fn (Schema $schema): bool => $schema->getOperation() === 'edit');
    }

    private static function paymentLabel(Payment $payment): string
    {
        return implode(' | ', [$payment->or_number, $payment->payer?->user?->full_name,
            CarbonImmutable::create((int) $payment->duesObligation->billing_year, (int) $payment->duesObligation->billing_month, 1)->format('F Y'), 'PHP '.$payment->amount_paid]);
    }

    private static function receiptHomeowners(string $search = ''): array
    {
        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
        $query = app(ButawReceiptEligibility::class)->homeowners();
        foreach (preg_split('/\s+/u', trim($search), flags: PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(fn (Builder $q) => $q->where('block', 'like', '%'.$term.'%')->orWhere('lot', 'like', '%'.$term.'%')
                ->orWhere('street', 'like', '%'.$term.'%')->orWhereHas('user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('middle_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('suffix', 'like', '%'.$term.'%')));
        }

        return $query->orderBy('id')->limit(30)->get()->mapWithKeys(fn (Homeowner $resident): array => [$resident->id => self::homeownerLabel($resident)])->all();
    }

    private static function homeownerLabel(Homeowner $homeowner): string
    {
        return $homeowner->user?->full_name.' | '.$homeowner->full_address
            .($homeowner->trashed() || $homeowner->user?->trashed() ? ' | '.__('Archived') : '');
    }

    private static function receiptPayments(int $homeownerId, string $search = ''): array
    {
        OperationalAccess::authorize(app(AuthenticatedActor::class)->user(), 'manage_payments');
        if ($homeownerId < 1) {
            return [];
        }
        $query = app(ButawReceiptEligibility::class)->query()->where('homeowner_id', $homeownerId);
        foreach (preg_split('/\s+/u', trim($search), flags: PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(fn (Builder $q) => $q->where('or_number', 'like', '%'.$term.'%')
                ->orWhereHas('payer.user', fn (Builder $user) => $user->where('first_name', 'like', '%'.$term.'%')
                    ->orWhere('middle_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')));
        }

        return $query->orderByDesc('payment_date')->orderByDesc('id')->limit(30)->get()->mapWithKeys(fn (Payment $payment): array => [$payment->id => self::paymentLabel($payment)])->all();
    }
}
