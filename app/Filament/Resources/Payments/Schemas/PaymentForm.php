<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\DuesSettings\DuesSettingResource;
use App\Models\DuesObligation;
use App\Services\PropertyDuesAccounts;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('dues_obligation_id')
                ->label('Dues obligation')
                ->relationship('duesObligation', 'id', modifyQueryUsing: fn ($query) => $query->whereIn('status', ['Pending', 'Partial', 'Overdue'])->with(['homeowner.user', 'duesSetting']))
                ->getOptionLabelFromRecordUsing(fn (DuesObligation $record): string => $record->homeowner->user->full_name.' | '.$record->duesSetting->name.' | '.str_pad((string) $record->billing_month, 2, '0', STR_PAD_LEFT).'/'.$record->billing_year)
                ->searchable()
                ->preload()
                ->noOptionsMessage(fn (): string => 'No unpaid or partially paid dues obligations are available.')
                ->helperText(fn (): HtmlString => new HtmlString(
                    'This list is generated for active homeowners from open obligations. '
                    .'<a class="font-medium text-primary-600 underline" href="'.e(DuesSettingResource::getUrl('create')).'">Create an active dues setting</a>, '
                    .'then run <code>php artisan hoa:generate-dues --month=YYYY-MM</code> before recording a payment.'
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
                ->label('Actual payer')
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
            TextInput::make('amount_paid')->label('Amount paid')->numeric()->prefix('PHP')->minValue(0.01)->disabled(fn (string $operation): bool => $operation === 'edit')->required(),
            TextInput::make('penalty')->numeric()->prefix('PHP')->minValue(0)->default(0)->disabled(fn (string $operation): bool => $operation === 'edit')->required(),
            DatePicker::make('payment_date')->default(today())->maxDate(today())->required(),
            TextInput::make('covered_period')->label('Billing period')->disabled()->dehydrated(false)
                ->formatStateUsing(fn ($record): ?string => $record?->covered_period),
            Select::make('payment_method')->options(['Cash' => 'Cash', 'Bank Transfer' => 'Bank Transfer', 'GCash' => 'GCash', 'Maya' => 'Maya'])->live()->required(),
            TextInput::make('transaction_reference')
                ->label('Transaction reference')
                ->maxLength(120)
                ->required(fn (Get $get): bool => in_array($get('payment_method'), ['Bank Transfer', 'GCash', 'Maya'], true))
                ->visible(fn (Get $get): bool => $get('payment_method') !== 'Cash'),
            Hidden::make('submission_token')->default(fn (): string => (string) Str::uuid()),
            Textarea::make('notes')->maxLength(500)->columnSpanFull(),
        ])->columns(2);
    }
}
