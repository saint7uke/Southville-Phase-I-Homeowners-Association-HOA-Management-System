<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Services\PaymentDuesAvailability;
use App\Services\PropertyDuesAccounts;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** Presentation of the existing payer policy, never an alternative eligibility rule. */
final class PaymentPayerSelect extends Select
{
    public const NONE = 'No eligible payer is available for this property. Ask the HOA to review the account and property association.';

    public const STALE = 'This payer is no longer eligible for the selected property. Select an eligible payer or ask the HOA to review the account and property association.';

    protected Closure $obligationResolver;

    protected ?Closure $historyResolver = null;

    protected function setUp(): void
    {
        parent::setUp();

        // A property's short payer list needs no asynchronous search/label requests.
        $this->label(__('Actual payer'))->native()->required()
            ->options(fn (): array => $this->payerOptions())
            ->placeholder(__('Select an eligible payer'))
            ->helperText(fn (): string|HtmlString => $this->history() ? __('Original payer recorded for this installment.')
                : (! $this->obligation() ? __('Select dues before choosing the actual payer.')
                    : ($this->payerOptions() === [] ? PaymentForm::reviewGuidance($this->obligation()) : __('Choose the person who actually made this payment.'))))
            ->disabled(fn (): bool => $this->history() !== null || $this->obligation() === null)
            ->validationMessages(['required' => __('Select an eligible payer'), 'in' => __(self::STALE)])
            ->afterStateHydrated(function (mixed $state): void {
                if ($payment = $this->history()) {
                    $this->state($payment->payer_homeowner_id ?? $payment->homeowner_id);

                    return;
                }
                if (! $this->history() && filled($state) && ! $this->hasPayer($state)) {
                    $this->state(null);
                }
            })
            ->mutateStateForValidationUsing(function (mixed $state): mixed {
                if (! $this->history() && $this->obligation()
                    && ($this->payerOptions() === [] || (filled($state) && ! $this->hasPayer($state)))) {
                    $this->rejectSelection();
                }

                return $state;
            });
    }

    public function forObligation(Closure $resolver): static
    {
        $this->obligationResolver = $resolver;

        return $this;
    }

    public function historicalPayment(Closure $resolver): static
    {
        $this->historyResolver = $resolver;

        return $this;
    }

    public static function findObligation(mixed $id): ?DuesObligation
    {
        return (is_int($id) || (is_string($id) && ctype_digit($id)))
            ? DuesObligation::with('homeowner')->find($id) : null;
    }

    public static function defaultPayer(?DuesObligation $obligation): ?int
    {
        return $obligation?->homeowner && app(PropertyDuesAccounts::class)->payerIsEligible($obligation->homeowner, $obligation->homeowner)
            ? (int) $obligation->homeowner_id : null;
    }

    public function rejectSelection(): never
    {
        $message = __($this->payerOptions() === [] ? self::NONE : self::STALE);
        if ($this->payerOptions() === [] && app(PaymentDuesAvailability::class)->reason($this->obligation()) === PaymentDuesAvailability::ARCHIVED) {
            $message = __(PaymentDuesAvailability::ARCHIVED).'. '.__(PaymentDuesAvailability::PORTAL_NOTICE);
        }
        $this->state(null);
        Notification::make()->danger()->title(__('Please review the form'))->body($message)->send();

        throw ValidationException::withMessages([$this->getStatePath() => $message]);
    }

    private function obligation(): ?DuesObligation
    {
        return isset($this->obligationResolver) ? $this->evaluate($this->obligationResolver) : null;
    }

    private function history(): ?Payment
    {
        return $this->historyResolver ? $this->evaluate($this->historyResolver) : null;
    }

    private function payerOptions(): array
    {
        if ($payment = $this->history()) {
            $payer = $payment->payer ?? $payment->homeowner;

            return $payer ? [$payer->id => self::payerLabel($payer)] : [];
        }
        $liable = $this->obligation()?->homeowner;

        return $liable ? app(PropertyDuesAccounts::class)->eligiblePayers($liable)->with('user')->get()
            ->mapWithKeys(fn (Homeowner $payer): array => [$payer->id => self::payerLabel($payer)])->all() : [];
    }

    private function hasPayer(mixed $id): bool
    {
        return (is_int($id) || (is_string($id) && ctype_digit($id))) && array_key_exists($id, $this->payerOptions());
    }

    private static function payerLabel(Homeowner $payer): string
    {
        return ($payer->user?->full_name ?: __('Unavailable resident')).' — '.__($payer->ownership_type);
    }
}
