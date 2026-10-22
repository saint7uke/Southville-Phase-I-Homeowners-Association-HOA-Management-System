<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\GenerateHoaReport;
use App\Models\AuditLog;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class Reports extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Reports & Exports';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports';

    public string $selectedCategory = 'all';

    public string $catalogSearch = '';

    /** @var array<string, array<string, mixed>> */
    public array $filters = [];

    public string $sortColumn = 'title';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public ?string $previewSlug = null;

    public string $previewTitle = '';

    /** @var list<string> */
    public array $previewHeadings = [];

    /** @var list<array<int, mixed>> */
    public array $previewRows = [];

    /** @var array<string, string|int> */
    public array $previewSummary = [];

    public int $previewPage = 1;

    public int $previewPerPage = 10;

    public bool $previewHasMore = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('export_reports') ?? false;
    }

    public function mount(): void
    {
        $this->selectedCategory = request()->query('category', 'all');
    }

    public function getCategories(): array
    {
        return collect($this->availableDefinitions())
            ->pluck('category')->unique()->sort()->mapWithKeys(fn (string $label) => [$label => $label])
            ->prepend('All', 'all')->all();
    }

    public function getReportsForCategory(string $category): Collection
    {
        $search = mb_strtolower(trim($this->catalogSearch));

        return collect($this->availableDefinitions())
            ->filter(fn (array $definition) => $category === 'all' || $definition['category'] === $category)
            ->filter(fn (array $definition) => $search === '' || str_contains(mb_strtolower($definition['title'].' '.$definition['description']), $search))
            ->map(fn (array $definition, string $slug): array => ['slug' => $slug, ...$definition])
            ->sortBy(in_array($this->sortColumn, ['title', 'category', 'description'], true) ? $this->sortColumn : 'title', SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();
    }

    public function getPaginatedReports(): Collection
    {
        return $this->getReportsForCategory($this->selectedCategory)->forPage($this->getPage(), $this->perPage)->values();
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

    /** @return array<string, mixed> */
    public function getReportFilters(string $slug): array
    {
        return $this->filters[$slug] ?? [];
    }

    public function updatedSelectedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedCatalogSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [5, 10, 25, 50], true)) {
            $this->perPage = 10;
        }
        $this->resetPage();
    }

    /** @param array<string, mixed>|null $filters */
    public function downloadReport(string $slug, string $format, ?array $filters = null): void
    {
        $context = $this->context();
        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters($slug, $filters ?? $this->getReportFilters($slug), $context['panel'] === 'admin');
        $catalog->authorize($context['user'], $context['panel'], $slug, $format, $filters['scope']);
        if ($format === 'pdf') {
            $summary = $catalog->summary($slug, $filters);
            if ((int) reset($summary) > 2000) {
                Notification::make()->warning()->title(__('PDF row limit exceeded'))
                    ->body(__('This PDF exceeds 2,000 rows. Narrow the filters or use a queued CSV/XLSX export.'))->send();

                return;
            }
        }
        $route = $context['panel'] === 'staff' ? 'staff.reports.download' : 'admin.reports.download';
        $this->redirect(route($route, ['report' => $slug, 'format' => $format, ...$filters]));
    }

    /** @param string|array<string, mixed>|null $format */
    public function queueReport(string $slug, string|array|null $format = 'csv'): void
    {
        if (is_array($format)) {
            $this->filters[$slug] = $format;
            $format = 'csv';
        }
        $format ??= 'csv';
        $context = $this->context();
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters($slug, $this->getReportFilters($slug), $context['panel'] === 'admin');
        $catalog->authorize($context['user'], $context['panel'], $slug, $format, $filters['scope']);

        $export = ReportExport::query()->create([
            'user_id' => $context['user']->id,
            'panel' => $context['panel'],
            'report' => $slug,
            'format' => $format,
            'filters' => array_filter($filters, fn ($value): bool => filled($value)),
            'status' => 'Pending',
        ]);
        GenerateHoaReport::dispatch($export->id);
        AuditLog::query()->create([
            'user_id' => $context['user']->id,
            'panel' => $context['panel'],
            'action' => 'Report.'.$slug.'_queued',
            'new_values' => ['report_export_id' => $export->id, 'format' => $format, ...$filters],
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
        ]);
        Notification::make()->success()->title(strtoupper($format).' export queued')->body(__('The private export will appear under My queued exports when complete.'))->send();
    }

    /** @param array<string, mixed>|null $filters */
    public function previewReport(string $slug, ?array $filters = null): void
    {
        if ($filters !== null) {
            $this->filters[$slug] = $filters;
        }
        $context = $this->context();
        $catalog = app(HoaReportCatalog::class);
        $validated = $catalog->validateFilters($slug, $this->getReportFilters($slug), $context['panel'] === 'admin');
        $catalog->authorize($context['user'], $context['panel'], $slug, null, $validated['scope']);
        $this->previewSlug = $slug;
        $this->previewPage = 1;
        $this->loadPreview();
    }

    public function previewPage(int $page): void
    {
        $this->previewPage = max(1, $page);
        $this->loadPreview();
    }

    public function closePreview(): void
    {
        $triggerId = 'report-preview-'.$this->previewSlug;
        $this->reset(['previewSlug', 'previewTitle', 'previewHeadings', 'previewRows', 'previewSummary', 'previewHasMore']);
        $this->previewPage = 1;
        $this->dispatch('report-preview-closed', triggerId: $triggerId);
    }

    public function updatedPreviewPerPage(): void
    {
        $this->previewPage = 1;
        $this->loadPreview();
    }

    private function loadPreview(): void
    {
        if ($this->previewSlug === null) {
            return;
        }
        try {
            $context = $this->context();
            $catalog = app(HoaReportCatalog::class);
            $filters = $catalog->validateFilters($this->previewSlug, $this->getReportFilters($this->previewSlug), $context['panel'] === 'admin');
            $catalog->authorize($context['user'], $context['panel'], $this->previewSlug, null, $filters['scope']);
            $this->previewPerPage = in_array($this->previewPerPage, [10, 25, 50], true) ? $this->previewPerPage : 10;
            $this->previewSummary = $catalog->summary($this->previewSlug, $filters);
            $rowCount = (int) reset($this->previewSummary);
            $lastPage = max(1, (int) ceil($rowCount / $this->previewPerPage));
            $this->previewPage = min(max(1, $this->previewPage), $lastPage);
            $records = $catalog->query($this->previewSlug, $filters)
                ->offset(($this->previewPage - 1) * $this->previewPerPage)
                ->limit($this->previewPerPage + 1)->get();
            $definition = $catalog->definitions()[$this->previewSlug];
            $this->previewTitle = $definition['title'];
            $this->previewHeadings = $catalog->headings($this->previewSlug);
            $this->previewRows = $records->take($this->previewPerPage)->map(fn ($record): array => $catalog->map($this->previewSlug, $record))->all();
            $this->previewHasMore = $records->count() > $this->previewPerPage;
            AuditLog::query()->create([
                'user_id' => $context['user']->id,
                'panel' => $context['panel'],
                'action' => 'Report.'.$this->previewSlug.'_previewed',
                'new_values' => [...$filters, 'page' => $this->previewPage],
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            ]);
            // Preserve the previous event contract for integrations while the
            // dialog itself uses server-side Livewire pagination.
            $this->dispatch('report-preview',
                title: $this->previewTitle,
                headings: $this->previewHeadings,
                rows: $this->previewRows,
                hasMore: $this->previewHasMore,
                summary: $this->previewSummary,
                triggerId: 'report-preview-'.$this->previewSlug,
            );
            $this->dispatch('report-preview-opened');
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('Preview failed'))->body(__('The report could not be loaded. Please review the filters and try again.'))->send();
        }
    }

    /** @return array{user: User, panel: string} */
    private function context(): array
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return ['user' => $user, 'panel' => Filament::getCurrentPanel()?->getId() ?? 'admin'];
    }

    /** @return array<string, array<string, mixed>> */
    private function availableDefinitions(): array
    {
        $context = $this->context();

        return app(HoaReportCatalog::class)->availableFor($context['user'], $context['panel']);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $context = $this->context();
        $reports = $this->getReportsForCategory($this->selectedCategory);

        return [
            'title' => self::$title ?? 'Reports & Exports',
            'panel' => $context['panel'],
            'categories' => $this->getCategories(),
            'allReports' => $reports,
            'selectedCategory' => $this->selectedCategory,
            'sortColumn' => $this->sortColumn,
            'sortDirection' => $this->sortDirection,
            'recentExports' => ReportExport::query()->where('user_id', $context['user']->id)->latest()->limit(10)->get(),
        ];
    }
}
