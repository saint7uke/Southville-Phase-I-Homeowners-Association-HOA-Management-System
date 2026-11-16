<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Actions\Payments\RecordAdditionalPayment;
use App\Filament\Resources\Payments\Schemas\PaymentPayerSelect;
use App\Models\Payment;
use App\Support\ActionFeedback;
use App\Support\AuthenticatedActor;
use App\Support\PaymentMethods;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class InstallmentActions
{
    public static function make(): array
    {
        return [
            Action::make('recordAdditionalPayment')->label(__('Record additional payment'))->icon('heroicon-o-plus-circle')->modal()
                ->tooltip(__('Record additional payment'))->button()->modalSubmitActionLabel(__('Record payment'))
                ->visible(fn (Payment $record): bool => self::canRecord($record))
                ->modalContent(fn (Payment $record) => view('filament.payments.installment-summary', ['obligation' => $record->duesObligation()->with('homeowner.user', 'duesSetting', 'payments')->first()]))
                ->fillForm(fn (Payment $record): array => ['payer_homeowner_id' => PaymentPayerSelect::defaultPayer(PaymentPayerSelect::findObligation($record->dues_obligation_id)),
                    'payment_method' => PaymentMethods::CASH, 'payment_date' => today()->toDateString(), 'submission_token' => (string) Str::uuid()])
                ->schema([
                    PaymentPayerSelect::make('payer_homeowner_id')
                        ->forObligation(fn (Payment $record) => PaymentPayerSelect::findObligation($record->dues_obligation_id)),
                    TextInput::make('amount_paid')->label(__('Installment amount'))->numeric()->prefix('PHP')->minValue(0.01)->required(),
                    DatePicker::make('payment_date')->label(__('Payment date'))->required()->maxDate(today()),
                    TextInput::make('payment_method')->label(__('Payment method'))->readOnly()->required(),
                    TextInput::make('transaction_reference')->label(__('Transaction reference'))->maxLength(120),
                    Textarea::make('notes')->label(__('Notes'))->maxLength(500),
                    Hidden::make('submission_token')->required(),
                ])
                ->action(function (Payment $record, array $data, Component $livewire): void {
                    try {
                        app(RecordAdditionalPayment::class)->handle($record, $data, app(AuthenticatedActor::class)->user());
                    } catch (ValidationException $exception) {
                        if (isset($exception->errors()['payer_homeowner_id'])) {
                            $livewire->getSchema($livewire->getMountedActionSchemaName())->getFlatFields()['payer_homeowner_id']->rejectSelection();
                        }
                        Notification::make()->danger()->title(__('Please review the form'))
                            ->body(collect($exception->errors())->flatten()->first())->send();
                        $path = $livewire->getSchema($livewire->getMountedActionSchemaName())->getStatePath();
                        $errors = [];
                        foreach ($exception->errors() as $field => $messages) {
                            $visible = in_array($field, ['amount_paid', 'payment_date', 'payer_homeowner_id', 'payment_method', 'transaction_reference', 'notes'], true) ? $field : 'amount_paid';
                            $errors[$path.'.'.$visible] = array_merge($errors[$path.'.'.$visible] ?? [], $messages);
                        }
                        throw ValidationException::withMessages($errors);
                    }
                    ActionFeedback::success('Additional Payment Recorded Successfully!');
                }),
            Action::make('installmentHistory')->label(__('Payment history'))->icon('heroicon-o-clock')->tooltip(__('Payment history'))->button()->modal()
                ->visible(fn (Payment $record): bool => $record->dues_obligation_id !== null && self::allows('view', $record))
                ->modalContent(function (Payment $record) {
                    Gate::forUser(app(AuthenticatedActor::class)->user())->authorize('view', $record);

                    return view('filament.payments.installment-history', ['obligation' => $record->duesObligation()->with(['homeowner.user', 'duesSetting',
                        'payments' => fn ($query) => $query->with('payer.user', 'recorder')->orderBy('reviewed_at')->orderBy('id')])->firstOrFail()]);
                })->modalSubmitAction(false)->modalCancelActionLabel(__('Close')),
        ];
    }

    private static function allows(string $ability, Payment|string $record): bool
    {
        $actor = app(AuthenticatedActor::class)->user();

        return $actor !== null && Gate::forUser($actor)->allows($ability, $record);
    }

    private static function canRecord(Payment $record): bool
    {
        $allowed = ! $record->trashed() && self::allows('create', Payment::class)
            && in_array($record->review_status, ['Recorded', 'Approved'], true)
            && in_array($record->duesObligation?->status, ['Pending', 'Partial', 'Overdue'], true);

        return $allowed;
    }
}
