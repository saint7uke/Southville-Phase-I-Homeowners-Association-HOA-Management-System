<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HoaReportCatalog
{
    private ?int $activeResidentCount = null;

    /** @var list<string> */
    private const STAFF_REPORTS = [
        'payment-period', 'payment-homeowner', 'dues-settings', 'delinquency',
        'payment-review', 'complaints', 'requests', 'certificates', 'announcements', 'contacts',
    ];

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        $spreadsheet = ['csv', 'xlsx'];
        $standard = ['csv', 'xlsx', 'pdf'];

        return [
            'payment-period' => $this->definition('Dues Assessment by Billing Period', 'Assessed dues, penalties, payments, and outstanding balances by property billing month.', 'Dues', $standard, ['Pending', 'Partial', 'Overdue', 'Paid'], 'Due date', ['period' => 'Billing period', 'assessed' => 'Assessed amount'], 'period'),
            'payment-homeowner' => $this->definition('Property Account Balances', 'Property-level obligations assigned to the liable Owner or fallback Co-owner.', 'Dues', $standard, ['Pending', 'Partial', 'Overdue', 'Paid'], 'Due date', ['resident' => 'Liable resident', 'outstanding' => 'Outstanding balance'], 'resident'),
            'dues-settings' => $this->definition('Dues Schedule Register', 'Current dues schedules and their effective billing periods.', 'Dues', $standard, ['Active', 'Inactive'], 'Effective date', ['name' => 'Schedule name', 'start' => 'Effective date'], 'name'),
            'delinquency' => $this->definition('Delinquent Obligations', 'Overdue property obligations with exact assessed, paid, and outstanding amounts.', 'Dues', $standard, ['Overdue'], 'Due date', ['due_date' => 'Due date', 'resident' => 'Liable resident', 'outstanding' => 'Outstanding balance'], 'due_date'),
            'payment-history' => $this->definition('Full Payment Collection History (Accepted Only)', 'Complete administrator-only history of accepted payment transactions. Pending and rejected proofs are excluded.', 'Payments', $spreadsheet, ['Paid', 'Partial'], 'Payment date', ['date' => 'Payment date', 'receipt' => 'Receipt number', 'amount' => 'Amount received'], 'date', true),
            'payment-review' => $this->definition('Payment Proof Review History', 'Pending, approved, and rejected electronic payment-proof submissions.', 'Payments', $spreadsheet, ['Pending', 'Approved', 'Rejected'], 'Submission date', ['date' => 'Submission date', 'review' => 'Review status', 'amount' => 'Submitted amount'], 'date', true),
            'complaints' => $this->definition('Complaint Summary', 'Complaint workflow records with assignment and resolution details.', 'Operations', $standard, ['Pending', 'Under Review', 'Resolved', 'Rejected', 'Closed', 'Dismissed'], 'Submitted date', ['date' => 'Submitted date', 'ticket' => 'Ticket number', 'status' => 'Status'], 'date', true),
            'requests' => $this->definition('Service Request Fulfillment', 'Service requests with request type, assignment, status, and completion date.', 'Operations', $standard, ['Pending', 'Processing', 'Approved', 'Completed', 'Rejected'], 'Submitted date', ['date' => 'Submitted date', 'ticket' => 'Ticket number', 'status' => 'Status'], 'date', true),
            'certificates' => $this->definition('Certificate Issuance Log', 'Issued and revoked certificates with historical type labels and validity dates.', 'Operations', $standard, ['Issued', 'Revoked'], 'Issued date', ['date' => 'Issued date', 'number' => 'Certificate number', 'status' => 'Status'], 'date', true),
            'announcements' => $this->definition('Announcement Publication and Readership', 'Publication metadata, unique reads, and current active-resident reach.', 'Communication', $standard, ['Draft', 'Published', 'Archived'], 'Publication date', ['date' => 'Publication date', 'title' => 'Title', 'reads' => 'Unique reads'], 'date', true),
            'contacts' => $this->definition('Contact Inquiry Activity', 'Public contact inquiries with workflow status and handler.', 'Operations', $standard, ContactMessage::STATUSES, 'Received date', ['date' => 'Received date', 'subject' => 'Subject', 'status' => 'Status'], 'date', true),
            'homeowners' => $this->definition('Homeowner Master List', 'Administrator-only resident directory with account, property, and occupancy information.', 'Directory', $spreadsheet, ['Active', 'Inactive', 'Delinquent'], 'Residency date', ['resident' => 'Resident name', 'date' => 'Residency date', 'status' => 'Status'], 'resident', true),
            'audit-activity' => $this->definition('Audit Activity', 'Administrator-only immutable audit activity and actor information.', 'Administration', $spreadsheet, [], 'Activity date', ['date' => 'Activity date', 'action' => 'Action'], 'date'),
        ];
    }

    /** @return array<string, mixed> */
    private function definition(string $title, string $description, string $category, array $formats, array $statuses, string $dateLabel, array $sorts, string $defaultSort, bool $archives = false): array
    {
        return compact('title', 'description', 'category', 'formats', 'statuses', 'dateLabel', 'sorts', 'defaultSort', 'archives');
    }

    public function exists(string $slug): bool
    {
        return isset($this->definitions()[$slug]);
    }

    public function authorize(User $user, string $panel, string $slug, ?string $format = null, string $scope = 'current'): void
    {
        abort_unless(! $user->trashed() && $user->account_status === 'Active'
            && in_array($panel, ['admin', 'staff'], true)
            && $user->can('export_reports') && $this->exists($slug), 403);
        if ($panel === 'staff') {
            abort_unless($user->hasRole('hoa_staff') && in_array($slug, self::STAFF_REPORTS, true), 403);
            abort_unless($scope === 'current', 403);
        } else {
            abort_unless($user->hasRole('hoa_admin'), 403);
        }
        if ($format !== null) {
            abort_unless(in_array($format, $this->definitions()[$slug]['formats'], true), 404);
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function availableFor(User $user, string $panel): array
    {
        if ($user->trashed() || $user->account_status !== 'Active' || ! in_array($panel, ['admin', 'staff'], true) || ! $user->can('export_reports')) {
            return [];
        }

        return collect($this->definitions())
            ->filter(fn (array $definition, string $slug): bool => $panel === 'admin'
                ? $user->hasRole('hoa_admin')
                : ($user->hasRole('hoa_staff') && in_array($slug, self::STAFF_REPORTS, true)))
            ->all();
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function validateFilters(string $slug, array $filters, bool $allowArchives): array
    {
        abort_unless($this->exists($slug), 404);
        $definition = $this->definitions()[$slug];
        $defaultSort = $definition['defaultSort'];
        $data = [
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'status' => $filters['status'] ?? null,
            'q' => trim((string) ($filters['q'] ?? '')) ?: null,
            'scope' => $filters['scope'] ?? 'current',
            'sort' => $filters['sort'] ?? $defaultSort,
            'direction' => strtolower((string) ($filters['direction'] ?? 'asc')),
        ];

        return Validator::make($data, [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in($definition['statuses'])],
            'q' => ['nullable', 'string', 'max:120'],
            'scope' => ['required', Rule::in($allowArchives && $definition['archives'] ? ['current', 'archived', 'all'] : ['current'])],
            'sort' => ['required', Rule::in(array_keys($definition['sorts']))],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
        ])->validate();
    }

    /** @param array<string, mixed> $filters */
    public function query(string $slug, array $filters): Builder
    {
        $query = match ($slug) {
            'payment-period' => DuesObligation::query()
                ->selectRaw('billing_year, billing_month, COUNT(*) AS obligation_count, SUM(amount_due) AS total_assessed, SUM(penalty_amount) AS total_penalties, SUM(amount_paid) AS total_paid, SUM(amount_due + penalty_amount - amount_paid) AS total_outstanding')
                ->groupBy('billing_year', 'billing_month'),
            'payment-homeowner' => DuesObligation::query()->with('homeowner.user')
                ->selectRaw('homeowner_id, COUNT(*) AS obligation_count, SUM(amount_due) AS total_assessed, SUM(penalty_amount) AS total_penalties, SUM(amount_paid) AS total_paid, SUM(amount_due + penalty_amount - amount_paid) AS total_outstanding, MAX(due_date) AS latest_due_date')
                ->groupBy('homeowner_id'),
            'dues-settings' => DuesSetting::query()->with('creator')->withCount('obligations'),
            'delinquency' => DuesObligation::query()->with(['homeowner.user', 'duesSetting'])->where('dues_obligations.status', 'Overdue'),
            'payment-history' => Payment::query()->with(['homeowner.user', 'payer.user', 'duesSetting', 'recorder'])->whereIn('review_status', ['Approved', 'Recorded']),
            'payment-review' => Payment::query()->with(['homeowner.user', 'payer.user', 'duesSetting', 'reviewer'])->whereNotNull('proof_path')->whereIn('review_status', ['Pending', 'Approved', 'Rejected']),
            'complaints' => Complaint::query()->with(['homeowner.user', 'handler']),
            'requests' => ServiceRequest::query()->with(['homeowner.user', 'handler']),
            'certificates' => Certificate::query()->with(['homeowner.user', 'issuer']),
            'announcements' => Announcement::query()->with('creator')->withCount([
                'readers',
                'readers as active_resident_readers_count' => fn (Builder $query) => $query
                    ->where('account_status', 'Active')
                    ->whereHas('roles', fn (Builder $query) => $query->where('name', 'homeowner')),
            ]),
            'contacts' => ContactMessage::query()
                ->with(['firstHandler', 'handler'])
                ->withMin(['activities as first_response_at' => fn (Builder $query) => $query->where('type', 'outbound_reply')], 'created_at'),
            'homeowners' => Homeowner::query()->with('user'),
            'audit-activity' => AuditLog::query()->with('user'),
            default => throw ValidationException::withMessages(['report' => __('Unsupported HOA report.')]),
        };

        $this->applyArchiveScope($query, $slug, $filters['scope']);
        $this->applyDateRange($query, $slug, $filters['from'], $filters['to']);
        $this->applyStatus($query, $slug, $filters['status']);
        $this->applySearch($query, $slug, $filters['q']);
        $this->applySort($query, $slug, $filters['sort'], $filters['direction']);

        return $query;
    }

    /** @return list<string> */
    public function headings(string $slug): array
    {
        return match ($slug) {
            'payment-period' => ['Billing Period', 'Properties', 'Assessed', 'Penalties', 'Paid', 'Outstanding'],
            'payment-homeowner' => ['Liable Resident', 'Property', 'Obligations', 'Assessed', 'Penalties', 'Paid', 'Outstanding', 'Latest Due Date'],
            'dues-settings' => ['Schedule', 'Amount', 'Frequency', 'Due Day', 'Effective From', 'Effective Until', 'Status', 'Obligations', 'Created By'],
            'delinquency' => ['Liable Resident', 'Property', 'Dues Type', 'Billing Period', 'Due Date', 'Amount Due', 'Penalty', 'Amount Paid', 'Outstanding'],
            'payment-history' => ['Receipt', 'Liable Resident', 'Actual Payer', 'Property', 'Dues Type', 'Amount Received', 'Penalty Assessed', 'Billing Period', 'Method', 'Reference', 'Payment Status', 'Review Status', 'Payment Date', 'Recorded By', 'Record Scope'],
            'payment-review' => ['Receipt', 'Liable Resident', 'Submitted By', 'Property', 'Dues Type', 'Submitted Amount', 'Reserved Amount', 'Method', 'Reference', 'Review Status', 'Review Notes', 'Submitted', 'Reviewed By', 'Record Scope'],
            'complaints' => ['Ticket', 'Resident', 'Subject', 'Category', 'Priority', 'Status', 'Handled By', 'Submitted', 'Resolved', 'Record Scope'],
            'requests' => ['Ticket', 'Resident', 'Request Type', 'Subject', 'Status', 'Handled By', 'Completed', 'Submitted', 'Record Scope'],
            'certificates' => ['Certificate Number', 'Resident', 'Type', 'Purpose', 'Status', 'Issued At', 'Issued By', 'Expires At', 'Record Scope'],
            'announcements' => ['Title', 'Category', 'Audience', 'Status', 'Published At', 'Expires At', 'Creator', 'Unique Reads', 'Current Active Resident Reach', 'Record Scope'],
            'contacts' => ['Reference', 'Name', 'Email', 'Phone', 'Subject', 'Status', 'First Response', 'Closed', 'Last Handler', 'Last Handled', 'Received', 'Record Scope'],
            'homeowners' => ['Resident', 'Email', 'Contact', 'Property', 'Occupancy', 'Residency Date', 'Resident Status', 'Account Status', 'Record Scope'],
            'audit-activity' => ['Timestamp', 'Actor', 'Panel', 'Action', 'Record Type', 'Record ID', 'IP Address'],
            default => [],
        };
    }

    /** @return list<mixed> */
    public function map(string $slug, mixed $record): array
    {
        $scope = method_exists($record, 'trashed') && $record->trashed() ? 'Archived' : 'Current';
        $values = match ($slug) {
            'payment-period' => [sprintf('%04d-%02d', $record->billing_year, $record->billing_month), $record->obligation_count, $record->total_assessed, $record->total_penalties, $record->total_paid, $record->total_outstanding],
            'payment-homeowner' => [$record->homeowner?->user?->full_name ?? 'Archived resident', $record->homeowner?->full_address, $record->obligation_count, $record->total_assessed, $record->total_penalties, $record->total_paid, $record->total_outstanding, $record->latest_due_date],
            'dues-settings' => [$record->name, $record->amount, $record->frequency, $record->due_day, $record->starts_on?->toDateString(), $record->ends_on?->toDateString(), $record->is_active ? 'Active' : 'Inactive', $record->obligations_count, $record->creator?->full_name ?? 'Legacy record'],
            'delinquency' => [$record->homeowner?->user?->full_name ?? 'Archived resident', $record->property_address, $record->duesSetting?->name, sprintf('%04d-%02d', $record->billing_year, $record->billing_month), $record->due_date?->toDateString(), $record->amount_due, $record->penalty_amount, $record->amount_paid, Money::fromMinor(Money::toMinor((string) $record->amount_due) + Money::toMinor((string) $record->penalty_amount) - Money::toMinor((string) $record->amount_paid))],
            'payment-history' => [$record->or_number, $record->homeowner?->user?->full_name ?? 'Archived resident', $record->payer?->user?->full_name ?? $record->homeowner?->user?->full_name, $record->property_address, $record->duesSetting?->name, $record->amount_paid, $record->penalty, $record->covered_period, $record->payment_method, $record->transaction_reference, $record->status, $record->review_status, $record->payment_date?->toDateString(), $record->recorder?->full_name ?? 'Archived recorder', $scope],
            'payment-review' => [$record->or_number, $record->homeowner?->user?->full_name ?? 'Archived resident', $record->payer?->user?->full_name ?? $record->homeowner?->user?->full_name ?? 'Archived payer', $record->property_address, $record->duesSetting?->name, $record->amount_paid, $record->review_status === 'Pending' ? $record->amount_paid : '0.00', $record->payment_method, $record->transaction_reference, $record->review_status, $record->review_notes, $record->created_at?->toDateTimeString(), $record->reviewer?->full_name, $scope],
            'complaints' => [$record->ticket_number, $record->homeowner?->user?->full_name ?? 'Archived resident', $record->subject, $record->category, $record->priority, $record->status, $record->handler?->full_name, $record->created_at?->toDateTimeString(), $record->resolved_at?->toDateTimeString(), $scope],
            'requests' => [$record->ticket_number, $record->homeowner?->user?->full_name ?? 'Archived resident', $record->request_type, $record->subject, $record->status, $record->handler?->full_name, $record->completed_at?->toDateTimeString(), $record->created_at?->toDateTimeString(), $scope],
            'certificates' => [$record->certificate_number, $record->homeowner?->user?->full_name ?? 'Archived resident', $record->type, $record->purpose, $record->status, $record->issued_at?->toDateTimeString(), $record->issuer?->full_name ?? 'Archived issuer', $record->expires_at?->toDateTimeString(), $scope],
            'announcements' => [$record->title, $record->category, $record->audience, $record->status, $record->published_at?->toDateTimeString(), $record->expires_at?->toDateTimeString(), $record->creator?->full_name ?? 'Archived creator', $record->readers_count, $this->residentReach($record->active_resident_readers_count), $scope],
            'contacts' => [$record->reference, $record->name, $record->email, $record->phone, $record->subject, $record->status, $record->first_response_at, $record->closed_at?->toDateTimeString(), $record->handler?->full_name, $record->handled_at?->toDateTimeString(), $record->created_at?->toDateTimeString(), $scope],
            'homeowners' => [$record->user?->full_name ?? 'Archived resident', $record->user?->email, $record->user?->contact_number, $record->full_address, $record->ownership_type, $record->residency_date?->toDateString(), $record->status, $record->user?->account_status, $scope],
            'audit-activity' => [$record->created_at?->toDateTimeString(), $record->user?->full_name ?? 'System', $record->panel, $record->action, class_basename((string) $record->auditable_type), $record->auditable_id, $record->ip_address],
            default => [],
        };

        return array_map(static fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value, $values);
    }

    /** @param array<string, mixed> $filters @return array<string, string|int> */
    public function summary(string $slug, array $filters): array
    {
        $query = $this->query($slug, $filters);
        $count = DB::query()->fromSub($query->toBase(), 'report_rows')->count();
        if (in_array($slug, ['payment-period', 'payment-homeowner'], true)) {
            $totals = DB::query()->fromSub($query->toBase(), 'report_rows')->selectRaw('COALESCE(SUM(total_assessed), 0) assessed, COALESCE(SUM(total_paid), 0) paid, COALESCE(SUM(total_outstanding), 0) outstanding')->first();

            return ['Rows' => $count, 'Assessed' => Money::fromMinor(Money::toMinor((string) $totals->assessed)), 'Paid' => Money::fromMinor(Money::toMinor((string) $totals->paid)), 'Outstanding' => Money::fromMinor(Money::toMinor((string) $totals->outstanding))];
        }
        if ($slug === 'payment-history') {
            $total = (clone $query)->reorder()->sum('amount_paid');

            return ['Transactions' => $count, 'Accepted collections' => Money::fromMinor(Money::toMinor((string) $total))];
        }
        if ($slug === 'payment-review') {
            $reserved = (clone $query)->reorder()->where('review_status', 'Pending')->sum('amount_paid');

            return ['Submissions' => $count, 'Pending reserved' => Money::fromMinor(Money::toMinor((string) $reserved))];
        }

        return ['Matching records' => $count];
    }

    private function applyArchiveScope(Builder $query, string $slug, string $scope): void
    {
        if (! ($this->definitions()[$slug]['archives'] ?? false)) {
            return;
        }
        if ($scope === 'archived') {
            $query->onlyTrashed();
        } elseif ($scope === 'all') {
            $query->withTrashed();
        }
    }

    private function applyDateRange(Builder $query, string $slug, ?string $from, ?string $to): void
    {
        $column = match ($slug) {
            'payment-period', 'payment-homeowner', 'delinquency' => 'dues_obligations.due_date',
            'dues-settings' => 'starts_on',
            'payment-history' => 'payment_date',
            'certificates' => 'issued_at',
            'announcements' => 'published_at',
            'homeowners' => 'residency_date',
            default => 'created_at',
        };
        $dateOnly = in_array($slug, ['payment-period', 'payment-homeowner', 'delinquency', 'dues-settings', 'payment-history', 'homeowners'], true);
        if ($from) {
            $query->where($column, '>=', $dateOnly ? $from : CarbonImmutable::parse($from, config('app.timezone'))->startOfDay());
        }
        if ($to) {
            $query->where($column, '<=', $dateOnly ? $to : CarbonImmutable::parse($to, config('app.timezone'))->endOfDay());
        }
    }

    private function applyStatus(Builder $query, string $slug, ?string $status): void
    {
        if (! $status) {
            return;
        }
        if ($slug === 'dues-settings') {
            $query->where('is_active', $status === 'Active');
        } elseif ($slug === 'payment-review') {
            $query->where('review_status', $status);
        } elseif (in_array($slug, ['payment-period', 'payment-homeowner', 'delinquency'], true)) {
            $query->where('dues_obligations.status', $status);
        } else {
            $query->where('status', $status);
        }
    }

    private function applySearch(Builder $query, string $slug, ?string $search): void
    {
        if (! $search) {
            return;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $resident = fn (Builder $query) => $query->whereHas('homeowner', fn (Builder $query) => $query
            ->where('house_number', 'like', $like)
            ->orWhere('street', 'like', $like)
            ->orWhere('block', 'like', $like)
            ->orWhere('lot', 'like', $like)
            ->orWhere('phase', 'like', $like)
            ->orWhereHas('user', fn (Builder $query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
        $query->where(function (Builder $query) use ($slug, $like, $resident): void {
            match ($slug) {
                'payment-period', 'payment-homeowner', 'delinquency' => $query->whereHas('duesSetting', fn (Builder $query) => $query->where('name', 'like', $like))->orWhere($resident),
                'dues-settings' => $query->where('name', 'like', $like)->orWhere('description', 'like', $like),
                'payment-history', 'payment-review' => $query->where('or_number', 'like', $like)->orWhere('transaction_reference', 'like', $like)->orWhere($resident)->orWhereHas('payer.user', fn (Builder $query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)),
                'complaints' => $query->where('ticket_number', 'like', $like)->orWhere('subject', 'like', $like)->orWhere($resident),
                'requests' => $query->where('ticket_number', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('request_type', 'like', $like)->orWhere($resident),
                'certificates' => $query->where('certificate_number', 'like', $like)->orWhere('type', 'like', $like)->orWhere($resident),
                'announcements' => $query->where('title', 'like', $like)->orWhere('category', 'like', $like),
                'contacts' => $query->where('reference', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('subject', 'like', $like),
                'homeowners' => $query->where('house_number', 'like', $like)->orWhere('street', 'like', $like)->orWhere('block', 'like', $like)->orWhere('lot', 'like', $like)->orWhere('phase', 'like', $like)->orWhereHas('user', fn (Builder $query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like)),
                'audit-activity' => $query->where('action', 'like', $like)->orWhereHas('user', fn (Builder $query) => $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)),
                default => null,
            };
        });
    }

    private function applySort(Builder $query, string $slug, string $sort, string $direction): void
    {
        if ($sort === 'resident' && in_array($slug, ['payment-homeowner', 'delinquency', 'homeowners'], true)) {
            if ($slug === 'homeowners') {
                $query->select('homeowners.*')->leftJoin('users as report_users', 'report_users.id', '=', 'homeowners.user_id');
            } else {
                if ($slug === 'delinquency') {
                    $query->select('dues_obligations.*');
                }
                $query->leftJoin('homeowners as report_homeowners', 'report_homeowners.id', '=', 'dues_obligations.homeowner_id')
                    ->leftJoin('users as report_users', 'report_users.id', '=', 'report_homeowners.user_id');
            }
            $tieBreaker = $slug === 'homeowners' ? 'homeowners.id' : 'dues_obligations.homeowner_id';
            $query->orderBy('report_users.last_name', $direction)->orderBy('report_users.first_name', $direction)->orderBy($tieBreaker, $direction);

            return;
        }

        $columns = match ($slug) {
            'payment-period' => ['period' => ['billing_year', 'billing_month'], 'assessed' => ['total_assessed']],
            'payment-homeowner' => ['resident' => ['homeowner_id'], 'outstanding' => ['total_outstanding']],
            'dues-settings' => ['name' => ['name'], 'start' => ['starts_on']],
            'delinquency' => ['due_date' => ['due_date'], 'resident' => ['homeowner_id'], 'outstanding' => [DB::raw('amount_due + penalty_amount - amount_paid')]],
            'payment-history' => ['date' => ['payment_date', 'id'], 'receipt' => ['or_number'], 'amount' => ['amount_paid']],
            'payment-review' => ['date' => ['created_at', 'id'], 'review' => ['review_status'], 'amount' => ['amount_paid']],
            'complaints', 'requests', 'certificates' => ['date' => [$slug === 'certificates' ? 'issued_at' : 'created_at', 'id'], 'ticket' => [$slug === 'certificates' ? 'certificate_number' : 'ticket_number'], 'number' => ['certificate_number'], 'status' => ['status']],
            'announcements' => ['date' => ['published_at', 'id'], 'title' => ['title'], 'reads' => ['readers_count']],
            'contacts' => ['date' => ['created_at', 'id'], 'subject' => ['subject'], 'status' => ['status']],
            'homeowners' => ['resident' => ['user_id'], 'date' => ['residency_date'], 'status' => ['status']],
            'audit-activity' => ['date' => ['created_at', 'id'], 'action' => ['action']],
            default => ['id' => ['id']],
        };
        foreach ($columns[$sort] ?? reset($columns) as $column) {
            $query->orderBy($column, $direction);
        }
    }

    private function residentReach(int $readers): string
    {
        $active = $this->activeResidentCount ??= User::query()->role('homeowner')->where('account_status', 'Active')->count();

        return $active > 0 ? number_format(($readers / $active) * 100, 1).'%' : 'N/A';
    }
}
