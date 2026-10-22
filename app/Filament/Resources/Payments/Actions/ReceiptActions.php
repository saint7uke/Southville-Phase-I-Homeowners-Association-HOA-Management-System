<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use App\Services\PaymentReceiptResolver;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;

final class ReceiptActions
{
    public static function make(): array
    {
        return [
            Action::make('printReceipt')->label(__('View / Print'))->icon('heroicon-o-printer')
                ->url(fn (Payment $record): ?string => app(PaymentReceiptResolver::class)->documentUrl($record, true))
                ->openUrlInNewTab()->visible(fn (Payment $record): bool => app(PaymentReceiptResolver::class)->state($record) === 'saved'),
            Action::make('receipt')->label(__('Download'))->icon('heroicon-o-document-arrow-down')
                ->url(fn (Payment $record): ?string => app(PaymentReceiptResolver::class)->documentUrl($record))
                ->visible(fn (Payment $record): bool => app(PaymentReceiptResolver::class)->state($record) === 'saved'),
            Action::make('generateMonthlyReceipt')->label(__('Generate monthly receipt'))->icon('heroicon-o-document-plus')
                ->url(fn (Payment $record): ?string => app(PaymentReceiptResolver::class)->generationUrl($record, app(AuthenticatedActor::class)->user()))
                ->visible(fn (Payment $record): bool => app(PaymentReceiptResolver::class)->state($record) === 'generate'
                    && app(PaymentReceiptResolver::class)->generationUrl($record, app(AuthenticatedActor::class)->user()) !== null),
            Action::make('detailedReceipt')->label(__('Detailed receipt'))->icon('heroicon-o-document-arrow-down')
                ->url(fn (Payment $record): string => route('payments.receipt.download', $record))->openUrlInNewTab()
                ->visible(fn (Payment $record): bool => app(PaymentReceiptResolver::class)->state($record) === 'detailed'),
            Action::make('receiptSetup')->label(__('Receipt setup required'))->disabled()
                ->tooltip(__('Receipt setup is incomplete. Contact the administrator.'))
                ->visible(fn (Payment $record): bool => app(PaymentReceiptResolver::class)->state($record) === 'setup'),
            Action::make('receiptNotGenerated')->label(__('Receipt not generated yet'))->disabled()
                ->tooltip(__('The HOA has not generated this monthly receipt yet. Please contact the HOA.'))
                ->visible(fn (Payment $record): bool => app(PaymentReceiptResolver::class)->state($record) === 'generate'
                    && app(PaymentReceiptResolver::class)->generationUrl($record, app(AuthenticatedActor::class)->user()) === null),
        ];
    }
}
