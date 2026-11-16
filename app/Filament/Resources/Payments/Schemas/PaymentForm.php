<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Filament\Resources\Homeowners\HomeownerResource;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Services\PaymentDuesAvailability;
use App\Support\AuthenticatedActor;
use App\Support\Money;
use App\Support\OperationalAccess;
use App\Support\PaymentMethods;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('dues_obligation_id')
                ->label(__('Dues obligation'))
                ->relationship('duesObligation', 'id', modifyQueryUsing: fn ($query) => $query->where('billing_year', '<=', now('Asia/Manila')->year)->whereIn('status', ['Pending', 'Partial', 'Overdue'])->with(['homeowner.user', 'duesSetting']))
                ->getOptionLabelFromRecordUsing(function (DuesObligation $record): string {
                    $label = $record->homeowner->user->full_name.' | '.$record->duesSetting->name.' | '.str_pad((string) $record->billing_month, 2, '0', STR_PAD_LEFT).'/'.$record->billing_year.' | PHP '.Money::fromMinor(Money::toMinor((string) $record->amount_due) + Money::toMinor((string) $record->penalty_amount) - Money::toMinor((string) $record->amount_paid)).' outstanding';
                    $reason = app(PaymentDuesAvailability::class)->reason($record);

                    return $reason ? $label.' | '.__($reason) : $label;
                })
                ->disableOptionWhen(fn ($value, string $operation): bool => $operation !== 'edit'
                    && app(PaymentDuesAvailability::class)->reason(PaymentPayerSelect::findObligation($value)) !== null)
                ->validationMessages(['in' => __('These dues have no eligible payer. Ask the HOA to review the homeowner account and property association.')])
                ->searchable()
                ->preload()
                ->noOptionsMessage('No unpaid dues available for this billing period. Please check the monthly dues settings.')
                ->helperText(fn (Get $get): HtmlString => new HtmlString(
                    'Select unpaid property dues. Existing payments are preserved. '
                    .(Gate::allows('viewAny', DuesSetting::class)
                        ? '<a class="font-medium text-primary-600 underline" href="'.e(DuesSettingResource::getUrl('index')).'">Check monthly dues settings</a> or use Generate current-month dues if obligations are missing.'
                        : 'Contact the administrator if expected dues are missing.')
                    .' '.e(__('Unavailable dues remain visible but cannot be selected for payment.')).' '
                    .self::reviewGuidance(PaymentPayerSelect::findObligation($get('dues_obligation_id')))->toHtml()
                ))
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $set('payer_homeowner_id', null);
                    $obligation = PaymentPayerSelect::findObligation($state);
                    $set('payer_homeowner_id', PaymentPayerSelect::defaultPayer($obligation));
                    $set('covered_period', $obligation
                        ? CarbonImmutable::create($obligation->billing_year, $obligation->billing_month)->format('F Y')
                        : null);
                })
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required(),
            PaymentPayerSelect::make('payer_homeowner_id')
                ->forObligation(fn (Get $get): ?DuesObligation => PaymentPayerSelect::findObligation($get('dues_obligation_id')))
                ->historicalPayment(fn (string $operation, ?Payment $record): ?\App\Models\Payment => $operation === 'edit' ? $record : null),
            TextInput::make('amount_paid')->label(__('Amount paid'))->numeric()->prefix('PHP')->minValue(0.01)->disabled(fn (string $operation): bool => $operation === 'edit')->required(),
            TextInput::make('penalty')->numeric()->prefix('PHP')->minValue(0)->default(0)->disabled(fn (string $operation): bool => $operation === 'edit')->required(),
            DatePicker::make('payment_date')->default(today())->maxDate(today())->required(),
            TextInput::make('covered_period')->label(__('Billing period'))->disabled()->dehydrated(false),
            TextInput::make('payment_method')->default(PaymentMethods::CASH)->readOnly()->required(),
            TextInput::make('transaction_reference')
                ->label(__('Transaction reference'))
                ->maxLength(120)
                ->readOnly()->dehydrated(false)
                ->visible(fn (Get $get): bool => $get('payment_method') !== 'Cash'),
            Hidden::make('submission_token')->default(fn (): string => (string) Str::uuid()),
            Textarea::make('notes')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }

    public static function reviewGuidance(?DuesObligation $obligation): HtmlString
    {
        $reason = $obligation ? app(PaymentDuesAvailability::class)->reason($obligation) : null;
        $text = $reason ? e(__($reason)).'. '.e(__(PaymentPayerSelect::NONE)).' ' : '';
        $text .= e(__(PaymentDuesAvailability::PORTAL_NOTICE));
        $actor = app(AuthenticatedActor::class)->user();
        if (app(AuthenticatedActor::class)->panel() === 'admin' && OperationalAccess::allows($actor, 'manage_homeowners', true)
            && Gate::forUser($actor)->allows('viewAny', Homeowner::class)) {
            $liable = $obligation?->homeowner;
            $url = $liable && Gate::forUser($actor)->allows('view', $liable)
                ? HomeownerResource::getUrl('view', ['record' => $liable], panel: 'admin')
                : HomeownerResource::getUrl('index', ['tableFilters' => ['trashed' => ['value' => '1']]], panel: 'admin');
            $text .= ' <a class="font-medium text-primary-600 underline" href="'.e($url).'">'.e(__('Review homeowner records')).'</a>';
        }

        return new HtmlString($text);
    }
}
