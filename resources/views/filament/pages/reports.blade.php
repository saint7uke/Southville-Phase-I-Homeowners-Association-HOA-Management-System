@push('styles')
    @once
        <link rel="stylesheet" href="{{ asset('css/hoa-reports.css') }}">
    @endonce
@endpush

<x-filament-panels::page>
    @php
        $totalReports = $allReports->count();
        $totalPages = max(1, (int) ceil($totalReports / $perPage));
        $currentPage = max(1, min($this->getPage(), $totalPages));
        $paginatedReports = $allReports->forPage($currentPage, $perPage)->values();
    @endphp

    <div class="hoa-reports">
        <header class="hoa-reports__toolbar">
            <div class="hoa-reports__intro">
                <h2 id="reports-catalog-title"></h2>
                <p>Filter the catalog, preview current records, or export a file. Activate a column heading to change the sort order.</p>
            </div>

            <div class="hoa-reports__controls" role="group" aria-label="Report catalog controls">
                <label class="hoa-reports__control" for="reports-category">
                    Category
                    <select id="reports-category" wire:model.live="selectedCategory">
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="hoa-reports__control" for="reports-per-page">
                    Rows per page
                    <select id="reports-per-page" wire:model.live="perPage">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
            </div>
        </header>

        <section class="hoa-reports__card" aria-labelledby="reports-catalog-title">
            <div class="hoa-reports__scroll" role="region" aria-label="Available HOA reports" aria-describedby="reports-table-help" tabindex="0">
                <p id="reports-table-help" class="sr-only">This table can be scrolled horizontally on small screens.</p>
                <table class="hoa-reports__table">
                    <caption class="sr-only">Available HOA reports, filters, and export actions</caption>
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="{{ $sortColumn === 'title' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="hoa-reports__column-report">
                                <button type="button" wire:click="sortBy('title')" class="hoa-reports__sort" aria-label="Sort by report name {{ $sortColumn === 'title' && $sortDirection === 'asc' ? 'descending' : 'ascending' }}">
                                    Report
                                    @if($sortColumn === 'title')
                                        <span class="hoa-reports__sort-indicator" aria-hidden="true">{{ $sortDirection === 'asc' ? '↓' : '↑' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" aria-sort="{{ $sortColumn === 'description' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="hoa-reports__column-description">
                                <button type="button" wire:click="sortBy('description')" class="hoa-reports__sort" aria-label="Sort by description {{ $sortColumn === 'description' && $sortDirection === 'asc' ? 'descending' : 'ascending' }}">
                                    Description
                                    @if($sortColumn === 'description')
                                        <span class="hoa-reports__sort-indicator" aria-hidden="true">{{ $sortDirection === 'asc' ? '↓' : '↑' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" aria-sort="{{ $sortColumn === 'category' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="hoa-reports__column-category">
                                <button type="button" wire:click="sortBy('category')" class="hoa-reports__sort" aria-label="Sort by category {{ $sortColumn === 'category' && $sortDirection === 'asc' ? 'descending' : 'ascending' }}">
                                    Category
                                    @if($sortColumn === 'category')
                                        <span class="hoa-reports__sort-indicator" aria-hidden="true">{{ $sortDirection === 'asc' ? '↓' : '↑' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="hoa-reports__column-filters">Filters</th>
                            <th scope="col" class="hoa-reports__column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedReports as $report)
                            <tr wire:key="report-row-{{ $report['slug'] }}">
                                <td>
                                    <div class="hoa-reports__report-title">{{ $report['title'] }}</div>
                                    <div class="hoa-reports__formats">{{ $report['formats'] ? implode(' · ', array_map('strtoupper', $report['formats'])) : 'No download format' }}</div>
                                </td>
                                <td><div class="hoa-reports__description">{{ $report['description'] }}</div></td>
                                <td>
                                    <span class="hoa-reports__badge {{ $report['category'] === 'Payments' ? 'hoa-reports__badge--payments' : ($report['category'] === 'Compliance' ? 'hoa-reports__badge--compliance' : '') }}">
                                        {{ $report['category'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="hoa-reports__filters">
                                        <label class="hoa-reports__filter" for="report-{{ $report['slug'] }}-from">
                                            From
                                            <input id="report-{{ $report['slug'] }}-from" type="date" wire:model="filters.{{ $report['slug'] }}.from">
                                        </label>
                                        <label class="hoa-reports__filter" for="report-{{ $report['slug'] }}-to">
                                            To
                                            <input id="report-{{ $report['slug'] }}-to" type="date" wire:model="filters.{{ $report['slug'] }}.to">
                                        </label>
                                        <label class="hoa-reports__filter hoa-reports__filter--wide" for="report-{{ $report['slug'] }}-status">
                                            Status
                                            <select id="report-{{ $report['slug'] }}-status" wire:model="filters.{{ $report['slug'] }}.status">
                                                <option value="">All statuses</option>
                                                @foreach($report['statuses'] as $status)
                                                    <option value="{{ $status }}">{{ $status }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <div class="hoa-reports__actions">
                                        @foreach($report['formats'] as $format)
                                            <button type="button" wire:click="downloadReport('{{ $report['slug'] }}', '{{ $format }}')" class="hoa-reports__button hoa-reports__button--{{ $format }}">
                                                {{ strtoupper($format) }}
                                            </button>
                                        @endforeach
                                        <button type="button" wire:click="queueReport('{{ $report['slug'] }}')" class="hoa-reports__button hoa-reports__button--queue" title="Generate a private CSV in the queue for large datasets">
                                            Queue CSV
                                        </button>
                                        <button id="report-preview-{{ $report['slug'] }}" type="button" wire:click="previewReport('{{ $report['slug'] }}')" class="hoa-reports__button hoa-reports__button--preview">
                                            Preview
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="hoa-reports__empty">No reports found in this category.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($totalReports > 0)
                <div class="hoa-reports__pagination">
                    <div class="hoa-reports__pagination-summary" role="status" aria-live="polite">
                        Showing {{ ($currentPage - 1) * $perPage + 1 }}–{{ min($currentPage * $perPage, $totalReports) }} of {{ $totalReports }} reports
                    </div>
                    <nav aria-label="Reports pagination" class="hoa-reports__page-list">
                        <button type="button" wire:click="setPage({{ $currentPage - 1 }})" @disabled($currentPage <= 1) class="hoa-reports__page-button">Previous</button>

                        @for($i = 1; $i <= $totalPages; $i++)
                            <button type="button" wire:click="setPage({{ $i }})" aria-label="Page {{ $i }}" @if($i === $currentPage) aria-current="page" @endif class="hoa-reports__page-button">
                                {{ $i }}
                            </button>
                        @endfor

                        <button type="button" wire:click="setPage({{ $currentPage + 1 }})" @disabled($currentPage >= $totalPages) class="hoa-reports__page-button">Next</button>
                    </nav>
                </div>
            @endif
        </section>

        <section class="hoa-reports__card" aria-labelledby="queued-exports-title">
            <header class="hoa-reports__card-header">
                <h2 id="queued-exports-title" class="hoa-reports__section-heading">My queued exports</h2>
                <p class="hoa-reports__section-copy">Private CSV files expire seven days after completion.</p>
            </header>
            <div class="hoa-reports__scroll" role="region" aria-label="Recent queued exports" tabindex="0">
                <table class="hoa-reports__table hoa-reports__table--compact">
                    <caption class="sr-only">Your five most recent queued report exports</caption>
                    <thead><tr><th scope="col">Report</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col">File</th></tr></thead>
                    <tbody>
                        @forelse($recentExports as $export)
                            <tr wire:key="report-export-{{ $export->id }}">
                                <td>{{ \App\Exports\HoaReportExport::TITLES[$export->report] ?? $export->report }}</td>
                                <td>{{ $export->created_at->format('M j, Y g:i A') }}</td>
                                <td>{{ $export->status }}</td>
                                <td>
                                    @if($export->status === 'Completed' && ! $export->expires_at?->isPast())
                                        <a class="hoa-reports__link" href="{{ route($panel === 'staff' ? 'staff.reports.queued.download' : 'admin.reports.queued.download', $export) }}">Download CSV</a>
                                    @elseif($export->status === 'Failed')
                                        <span title="{{ $export->failure_reason }}">Failed</span>
                                    @else
                                        <span>Not ready</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="hoa-reports__empty">No queued exports yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('report-preview', (data) => {
                document.querySelector('[data-report-preview]')?.remove();

                const escapeHtml = (value) => String(value ?? '-').replace(/[&<>'"]/g, (character) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
                })[character]);
                const previouslyFocused = document.getElementById(data.triggerId) ?? document.activeElement;
                const modal = document.createElement('div');
                modal.className = 'hoa-report-dialog';
                modal.dataset.reportPreview = '';
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-labelledby', 'report-preview-title');
                modal.innerHTML = `
                    <div class="hoa-report-dialog__panel">
                        <div class="hoa-report-dialog__header">
                            <h2 id="report-preview-title" class="hoa-report-dialog__title">${escapeHtml(data.title)} — Preview (first 10 rows)</h2>
                            <button type="button" data-close-preview aria-label="Close report preview" class="hoa-report-dialog__close">×</button>
                        </div>
                        <div class="hoa-report-dialog__body">
                            <table class="hoa-report-dialog__table">
                                <caption class="sr-only">${escapeHtml(data.title)} report preview</caption>
                                <thead><tr>${data.headings.map((heading) => `<th scope="col">${escapeHtml(heading)}</th>`).join('')}</tr></thead>
                                <tbody>
                                    ${data.rows.map((row) => `<tr>${row.map((value) => `<td>${escapeHtml(value)}</td>`).join('')}</tr>`).join('')}
                                    ${data.hasMore ? `<tr><td colspan="${data.headings.length}" class="hoa-reports__empty">More rows are available. Apply filters or download the full report.</td></tr>` : ''}
                                    ${data.rows.length === 0 ? `<tr><td colspan="${data.headings.length}" class="hoa-reports__empty">No records matched the selected filters.</td></tr>` : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);

                const closeButton = modal.querySelector('[data-close-preview]');
                const close = () => {
                    modal.remove();

                    if (previouslyFocused instanceof HTMLElement && previouslyFocused.isConnected) {
                        requestAnimationFrame(() => previouslyFocused.focus({ preventScroll: true }));
                    }
                };

                closeButton.addEventListener('click', close);
                closeButton.focus();
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) close();
                });
                modal.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        close();
                    }

                    if (event.key === 'Tab') {
                        event.preventDefault();
                        closeButton.focus();
                    }
                });
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
