<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Services\PropertyDuesAccounts;
use App\Support\Money;
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
                ->relationship('duesObligation', 'id', modifyQueryUsing: fn ($query) => $query->whereIn('status', ['Pending', 'Partial', 'Overdue'])->with(['homeowner.user', 'duesSetting']))
                ->getOptionLabelFromRecordUsing(fn (DuesObligation $record): string => $record->homeowner->user->full_name.' | '.$record->duesSetting->name.' | '.str_pad((string) $record->billing_month, 2, '0', STR_PAD_LEFT).'/'.$record->billing_year.' | PHP '.Money::fromMinor(Money::toMinor((string) $record->amount_due) + Money::toMinor((string) $record->penalty_amount) - Money::toMinor((string) $record->amount_paid)).' outstanding')
                ->searchable()
                ->preload()
                ->noOptionsMessage('No unpaid dues available for this billing period. Please check the monthly dues settings.')
                ->helperText(fn (): HtmlString => new HtmlString(
                    'Select unpaid property dues. Existing payments are preserved. '
                    .(Gate::allows('viewAny', DuesSetting::class)
                        ? '<a class="font-medium text-primary-600 underline" href="'.e(DuesSettingResource::getUrl('index')).'">Check monthly dues settings</a> or use Generate current-month dues if obligations are missing.'
                        : 'Contact the administrator if expected dues are missing.')
                ))
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $obligation = DuesObligation::query()->find($state);
                    $set('payer_homeowner_id', $obligation?->homeowner_id);
                    $set('covered_period', $obligation
                        ? CarbonImmutable::create($obligation->billing_year, $obligation->billing_month)->format('F Y')
                        : null);
                })
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required(),
            Select::make('payer_homeowner_id')
                ->label(__('Actual payer'))
                ->options(function (Get $get): array {
                    $obligation = DuesObligation::query()->with('homeowner')->find($get('dues_obligation_id'));
                    if ($obligation === null) {
                        return [];
                    }

                    return app(PropertyDuesAccounts::class)->eligiblePayers($obligation->homeowner)
                        ->with('user')->get()->mapWithKeys(fn ($homeowner): array => [
                            $homeowner->id => $homeowner->user->full_name.' — '.$homeowner->ownership_type,
                        ])->all();
                })
                ->searchable()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->required(),
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
}
