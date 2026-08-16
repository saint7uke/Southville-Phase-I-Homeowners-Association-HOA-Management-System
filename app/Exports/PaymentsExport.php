<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class PaymentsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly ?string $from = null, private readonly ?string $to = null, private readonly ?string $status = null) {}

    public function query(): Builder
    {
        return Payment::query()->with(['homeowner.user', 'duesSetting', 'recorder'])
            ->when($this->from, fn (Builder $query) => $query->whereDate('payment_date', '>=', $this->from))
            ->when($this->to, fn (Builder $query) => $query->whereDate('payment_date', '<=', $this->to))
            ->when($this->status, fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('payment_date')->orderBy('id');
    }

    public function headings(): array
    {
        return ['OR Number', 'Homeowner', 'Address', 'Dues Type', 'Amount Paid', 'Balance', 'Penalty', 'Covered Period', 'Method', 'Status', 'Date', 'Recorded By'];
    }

    public function map($payment): array
    {
        return [$payment->or_number, $payment->homeowner->user->full_name, $payment->homeowner->full_address, $payment->duesSetting->name, $payment->amount_paid, $payment->balance, $payment->penalty, $payment->covered_period, $payment->payment_method, $payment->status, $payment->payment_date->toDateString(), $payment->recorder->full_name];
    }
}
