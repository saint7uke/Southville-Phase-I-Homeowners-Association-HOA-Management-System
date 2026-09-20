<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class HoaReportExport implements FromQuery, WithHeadings, WithMapping
{
    /** @var array<string, string> */
    public const TITLES = [
        'payment-period' => 'Payment Summary by Period',
        'payment-homeowner' => 'Payment Summary by Homeowner',
        'delinquency' => 'Delinquency Report',
        'payment-history' => 'Full Payment Collection History',
        'complaints' => 'Complaint Summary',
        'requests' => 'Request Fulfillment Summary',
        'homeowners' => 'Homeowner Master List',
        'certificates' => 'Certificate Issuance Log',
    ];

    /** @var array<string, string> */
    public const DESCRIPTIONS = [
        'payment-period' => 'Aggregate payments grouped by billing period with totals and balances.',
        'payment-homeowner' => 'Aggregate payments per homeowner with totals and outstanding balances.',
        'delinquency' => 'All overdue dues obligations with penalty calculations.',
        'payment-history' => 'Complete transaction history of all payments with full details.',
        'complaints' => 'All submitted complaints with status, priority, and handler info.',
        'requests' => 'All service requests with fulfillment status and completion dates.',
        'homeowners' => 'Complete directory of all homeowners with contact and address info.',
        'certificates' => 'Log of all issued certificates with validity and issuer details.',
    ];

    /** @var array<string, string> */
    public const CATEGORIES = [
        'payment-period' => 'Payments',
        'payment-homeowner' => 'Payments',
        'payment-history' => 'Payments',
        'delinquency' => 'Compliance',
        'complaints' => 'Compliance',
        'requests' => 'Compliance',
        'homeowners' => 'Directory',
        'certificates' => 'Directory',
    ];

    /** @var array<string, array<int, string>> */
    public const FORMATS = [
        'payment-period' => ['csv', 'pdf'],
        'payment-homeowner' => ['csv', 'pdf'],
        'delinquency' => ['csv', 'pdf'],
        'payment-history' => ['csv'],
        'complaints' => ['csv', 'pdf'],
        'requests' => ['csv', 'pdf'],
        'homeowners' => ['csv'],
        'certificates' => ['csv'],
    ];

    /** @var array<string, array<int, string>> */
    public const STATUS_FILTERS = [
        'payment-period' => ['Paid', 'Partial', 'Overdue'],
        'payment-homeowner' => ['Paid', 'Partial', 'Overdue'],
        'payment-history' => ['Paid', 'Partial', 'Overdue', 'Pending', 'Processing', 'Approved', 'Completed', 'Rejected'],
        'delinquency' => ['Overdue'],
        'complaints' => ['Pending', 'Processing', 'Under Review', 'Resolved', 'Dismissed'],
        'requests' => ['Pending', 'Processing', 'Approved', 'Completed', 'Rejected'],
        'homeowners' => ['Active', 'Inactive', 'Delinquent'],
        'certificates' => ['Issued', 'Revoked', 'Active', 'Inactive'],
    ];

    public function __construct(
        public readonly string $report,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
        private readonly ?string $status = null,
    ) {
        if (! isset(self::TITLES[$report])) {
            throw new InvalidArgumentException('Unsupported HOA report.');
        }
    }

    public function title(): string
    {
        return self::TITLES[$this->report];
    }

    public function query(): Builder
    {
        $query = match ($this->report) {
            'payment-period' => Payment::query()
                ->selectRaw('covered_period, COUNT(*) as payment_count, SUM(amount_paid) as total_paid, SUM(balance) as total_balance, MAX(payment_date) as latest_payment_date')
                ->groupBy('covered_period')->orderBy('latest_payment_date'),
            'payment-homeowner' => Payment::query()->with(['homeowner.user'])
                ->selectRaw('homeowner_id, COUNT(*) as payment_count, SUM(amount_paid) as total_paid, SUM(balance) as total_balance, MAX(payment_date) as latest_payment_date')
                ->groupBy('homeowner_id')->orderBy('homeowner_id'),
            'payment-history' => Payment::query()->with(['homeowner.user', 'duesSetting', 'recorder'])->orderBy('payment_date')->orderBy('id'),
            'delinquency' => DuesObligation::query()->with(['homeowner.user', 'duesSetting'])->where('status', 'Overdue')->orderBy('due_date')->orderBy('id'),
            'complaints' => Complaint::query()->with(['homeowner.user', 'handler'])->orderBy('created_at')->orderBy('id'),
            'requests' => ServiceRequest::query()->with(['homeowner.user', 'handler'])->orderBy('created_at')->orderBy('id'),
            'homeowners' => Homeowner::query()->with('user')->orderBy('block')->orderBy('lot')->orderBy('id'),
            'certificates' => Certificate::query()->with(['homeowner.user', 'issuer'])->orderBy('issued_at')->orderBy('id'),
        };

        $dateColumn = match ($this->report) {
            'payment-period', 'payment-homeowner', 'payment-history' => 'payment_date',
            'delinquency' => 'due_date',
            'certificates' => 'issued_at',
            default => 'created_at',
        };

        return $query
            ->when($this->from, fn (Builder $query) => $query->whereDate($dateColumn, '>=', $this->from))
            ->when($this->to, fn (Builder $query) => $query->whereDate($dateColumn, '<=', $this->to))
            ->when($this->status, fn (Builder $query) => $query->where('status', $this->status));
    }

    public function headings(): array
    {
        return match ($this->report) {
            'payment-period' => ['Period', 'Payment Count', 'Total Paid', 'Total Balance', 'Latest Payment Date'],
            'payment-homeowner' => ['Homeowner', 'Address', 'Payment Count', 'Total Paid', 'Total Balance', 'Latest Payment Date'],
            'payment-history' => ['OR Number', 'Homeowner', 'Address', 'Dues Type', 'Amount Paid', 'Balance', 'Penalty', 'Period', 'Method', 'Status', 'Payment Date', 'Recorded By'],
            'delinquency' => ['Homeowner', 'Address', 'Dues Type', 'Billing Period', 'Due Date', 'Amount Due', 'Penalty', 'Amount Paid', 'Outstanding'],
            'complaints' => ['Ticket', 'Homeowner', 'Subject', 'Category', 'Priority', 'Status', 'Handled By', 'Submitted'],
            'requests' => ['Ticket', 'Homeowner', 'Request Type', 'Status', 'Handled By', 'Completed', 'Submitted'],
            'homeowners' => ['Homeowner', 'Email', 'Contact', 'Address', 'Ownership', 'Residency Date', 'Status'],
            'certificates' => ['Certificate Number', 'Homeowner', 'Type', 'Purpose', 'Status', 'Issued At', 'Issued By', 'Expires At'],
        };
    }

    public function map($record): array
    {
        $values = match ($this->report) {
            'payment-period' => [$record->covered_period, $record->payment_count, $record->total_paid, $record->total_balance, $record->latest_payment_date],
            'payment-homeowner' => [$record->homeowner->user->full_name, $record->homeowner->full_address, $record->payment_count, $record->total_paid, $record->total_balance, $record->latest_payment_date],
            'payment-history' => [$record->or_number, $record->homeowner->user->full_name, $record->homeowner->full_address, $record->duesSetting->name, $record->amount_paid, $record->balance, $record->penalty, $record->covered_period, $record->payment_method, $record->status, $record->payment_date->toDateString(), $record->recorder->full_name],
            'delinquency' => [$record->homeowner->user->full_name, $record->homeowner->full_address, $record->duesSetting->name, sprintf('%04d-%02d', $record->billing_year, $record->billing_month), $record->due_date->toDateString(), $record->amount_due, $record->penalty_amount, $record->amount_paid, Money::fromMinor(Money::toMinor((string) $record->amount_due) + Money::toMinor((string) $record->penalty_amount) - Money::toMinor((string) $record->amount_paid))],
            'complaints' => [$record->ticket_number, $record->homeowner->user->full_name, $record->subject, $record->category, $record->priority, $record->status, $record->handler?->full_name, $record->created_at->toDateTimeString()],
            'requests' => [$record->ticket_number, $record->homeowner->user->full_name, $record->request_type, $record->status, $record->handler?->full_name, $record->completed_at?->toDateTimeString(), $record->created_at->toDateTimeString()],
            'homeowners' => [$record->user->full_name, $record->user->email, $record->user->contact_number, $record->full_address, $record->ownership_type, $record->residency_date->toDateString(), $record->status],
            'certificates' => [$record->certificate_number, $record->homeowner->user->full_name, $record->type, $record->purpose, $record->status, $record->issued_at->toDateTimeString(), $record->issuer->full_name, $record->expires_at?->toDateTimeString()],
        };

        return array_map(
            static fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value,
            $values,
        );
    }
}
