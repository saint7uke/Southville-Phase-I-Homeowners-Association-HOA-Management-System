<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exports\HoaReportExport;
use App\Jobs\GenerateHoaReport;
use App\Models\AuditLog;
use App\Models\ReportExport;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\WithPagination;

final class Reports extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Reports & Exports';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports';

    public string $selectedCategory = 'all';

    public array $filters = [];

    public string $sortColumn = 'title';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('export_reports') ?? false;
    }

    public function mount(): void
    {
        $this->selectedCategory = request()->query('category', 'all');
        $this->filters = [];
    }

    public function getCategories(): array
    {
        $categories = collect(HoaReportExport::CATEGORIES)
            ->unique()
            ->mapWithKeys(fn (string $label) => [$label => $label])
            ->prepend('All', 'all')
            ->toArray();

        return $categories;
    }

    public function getReportsForCategory(string $category): Collection
    {
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';

        $reports = collect(HoaReportExport::TITLES)
            ->when($panel === 'staff', fn ($items) => $items->except('payment-history'))
            ->filter(fn (string $title, string $slug) => $category === 'all' || (HoaReportExport::CATEGORIES[$slug] ?? '') === $category)
            ->map(fn (string $title, string $slug): array => [
                'slug' => $slug,
                'title' => $title,
                'description' => HoaReportExport::DESCRIPTIONS[$slug] ?? '',
                'category' => HoaReportExport::CATEGORIES[$slug] ?? '',
                'formats' => HoaReportExport::FORMATS[$slug] ?? ['csv'],
                'statuses' => HoaReportExport::STATUS_FILTERS[$slug] ?? [],
            ])
            ->values();

        return $reports
            ->sortBy(in_array($this->sortColumn, ['title', 'category', 'description'], true) ? $this->sortColumn : 'title', SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();
    }

    public function getPaginatedReports(): Collection
    {
        $reports = $this->getReportsForCategory($this->selectedCategory);

        return $reports->forPage($this->getPage(), $this->perPage);
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['title', 'category', 'description'], true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function getReportFilters(string $slug): array
    {
        return $this->filters[$slug] ?? ['from' => null, 'to' => null, 'status' => null];
    }

    public function updatedSelectedCategory(): void
    {
        $this->filters = [];
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [5, 10, 25, 50], true)) {
            $this->perPage = 10;
        }
        $this->resetPage();
    }

    public function downloadReport(string $slug, string $format, ?array $filters = null): void
    {
        $filters ??= $this->getReportFilters($slug);
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';
        $routeName = $panel === 'staff' ? 'staff.reports.download' : 'admin.reports.download';

        $url = route($routeName, [
            'report' => $slug,
            'format' => $format,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'status' => $filters['status'] ?? null,
        ]);

        $this->redirect($url);
    }

    public function queueReport(string $slug, ?array $filters = null): void
    {
        $filters ??= $this->getReportFilters($slug);
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';
        $user = Filament::auth()->user();
        abort_unless($user?->can('export_reports'), 403);
        abort_unless(isset(HoaReportExport::TITLES[$slug]) && in_array('csv', HoaReportExport::FORMATS[$slug], true), 404);
        abort_if($panel === 'staff' && $slug === 'payment-history', 403);

        $validated = Validator::make($filters, [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(HoaReportExport::STATUS_FILTERS[$slug] ?? [])],
        ])->validate();

        $reportExport = ReportExport::query()->create([
            'user_id' => $user->id,
            'panel' => $panel,
            'report' => $slug,
            'format' => 'csv',
            'filters' => array_filter($validated, fn ($value): bool => filled($value)),
            'status' => 'Pending',
        ]);

        GenerateHoaReport::dispatch($reportExport->id);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'panel' => $panel,
            'action' => 'Report.'.$slug.'_queued',
            'new_values' => ['report_export_id' => $reportExport->id, ...($reportExport->filters ?? [])],
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ]);

        Notification::make()
            ->title('CSV export queued')
            ->body('Refresh this page shortly to download the completed private export.')
            ->success()
            ->send();
    }

    public function previewReport(string $slug, ?array $filters = null): void
    {
        $filters ??= $this->getReportFilters($slug);
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';
        abort_unless(isset(HoaReportExport::TITLES[$slug]), 404);
        abort_if($panel === 'staff' && $slug === 'payment-history', 403);

        try {
            $validated = Validator::make($filters, [
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
                'status' => ['nullable', Rule::in(HoaReportExport::STATUS_FILTERS[$slug] ?? [])],
            ])->validate();

            $export = new HoaReportExport(
                $slug,
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $validated['status'] ?? null
            );

            $records = $export->query()->limit(11)->get();
            $rows = $records->take(10)->map(fn ($record): array => $export->map($record))->all();

            $this->dispatch('report-preview',
                title: $export->title(),
                headings: $export->headings(),
                rows: $rows,
                hasMore: $records->count() > 10,
                triggerId: 'report-preview-'.$slug,
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Preview failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';
        $reports = $this->getReportsForCategory($this->selectedCategory);
        $paginatedReports = $reports->forPage($this->getPage(), $this->perPage);

        return [
            'title' => self::$title ?? 'Reports & Exports',
            'panel' => $panel,
            'categories' => $this->getCategories(),
            'reports' => $paginatedReports,
            'allReports' => $reports,
            'selectedCategory' => $this->selectedCategory,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
            'recentExports' => ReportExport::query()
                ->where('user_id', Filament::auth()->id())
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }
}
