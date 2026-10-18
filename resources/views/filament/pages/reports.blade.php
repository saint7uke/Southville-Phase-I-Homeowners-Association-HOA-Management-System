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
        $reports = $allReports->forPage($currentPage, $perPage)->values();
    @endphp

    <div class="hoa-reports">
        <header class="hoa-reports__toolbar">
            <div class="hoa-reports__intro">
                <h2 id="reports-catalog-title">Reports and exports</h2>
                <p>Preview live database records or export the same filtered dataset. Financial reports use the current property ledger.</p>
            </div>

            <div class="hoa-reports__controls" role="search" aria-label="Search and filter report catalog">
                <label class="hoa-reports__control" for="reports-search">Search reports
                    <input id="reports-search" type="search" wire:model.live.debounce.300ms="catalogSearch" placeholder="Search report names">
                </label>
                <label class="hoa-reports__control" for="reports-category">Category
                    <select id="reports-category" wire:model.live="selectedCategory">
                        @foreach($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="hoa-reports__control" for="reports-per-page">Rows per page
                    <select id="reports-per-page" wire:model.live="perPage">
                        @foreach([5, 10, 25, 50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                    </select>
                </label>
            </div>
        </header>

        @if($errors->any())
            <div class="hoa-reports__error" role="alert" tabindex="-1" data-report-error x-data x-init="$nextTick(() => $el.focus())">
                <strong>Review the report filters.</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="hoa-reports__card" aria-labelledby="reports-catalog-title">
            <div class="hoa-reports__scroll" role="region" aria-label="Available HOA reports" tabindex="0">
                <table class="hoa-reports__table">
                    <caption class="sr-only">Available HOA reports, filters, and export actions</caption>
                    <thead><tr>
                        @foreach(['title' => 'Report', 'description' => 'Description', 'category' => 'Category'] as $column => $label)
                            <th scope="col" class="hoa-reports__column-{{ $column === 'title' ? 'report' : $column }}" aria-sort="{{ $sortColumn === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('{{ $column }}')" aria-label="Sort by {{ strtolower($column === 'title' ? 'report name' : $label) }} {{ $sortColumn === $column && $sortDirection === 'asc' ? 'descending' : 'ascending' }}" class="hoa-reports__sort">
                                    {{ $label }} @if($sortColumn === $column)<span aria-hidden="true">{{ $sortDirection === 'asc' ? '↓' : '↑' }}</span>@endif
                                </button>
                            </th>
                        @endforeach
                        <th scope="col" class="hoa-reports__column-filters">Dataset filters</th>
                        <th scope="col" class="hoa-reports__column-actions">Actions</th>
                    </tr></thead>
                    <tbody>
                    @forelse($reports as $report)
                        <tr wire:key="report-row-{{ $report['slug'] }}">
                            <td><div class="hoa-reports__report-title">{{ $report['title'] }}</div><div class="hoa-reports__formats">{{ implode(' · ', array_map('strtoupper', $report['formats'])) }}</div></td>
                            <td><div class="hoa-reports__description">{{ $report['description'] }}</div></td>
                            <td><span class="hoa-reports__badge">{{ $report['category'] }}</span></td>
                            <td>
                                <div class="hoa-reports__filters">
                                    <label class="hoa-reports__filter hoa-reports__filter--wide" for="{{ $report['slug'] }}-q">Search records
                                        <input id="{{ $report['slug'] }}-q" type="search" maxlength="120" wire:model="filters.{{ $report['slug'] }}.q" placeholder="Name, number, subject, or reference">
                                    </label>
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-from">{{ $report['dateLabel'] }} from
                                        <input id="{{ $report['slug'] }}-from" type="date" wire:model="filters.{{ $report['slug'] }}.from">
                                    </label>
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-to">{{ $report['dateLabel'] }} to
                                        <input id="{{ $report['slug'] }}-to" type="date" wire:model="filters.{{ $report['slug'] }}.to">
                                    </label>
                                    @if($report['statuses'])
                                        <label class="hoa-reports__filter" for="{{ $report['slug'] }}-status">Status
                                            <select id="{{ $report['slug'] }}-status" wire:model="filters.{{ $report['slug'] }}.status">
                                                <option value="">All statuses</option>
                                                @foreach($report['statuses'] as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach
                                            </select>
                                        </label>
                                    @endif
                                    @if($panel === 'admin' && $report['archives'])
                                        <label class="hoa-reports__filter" for="{{ $report['slug'] }}-scope">Record scope
                                            <select id="{{ $report['slug'] }}-scope" wire:model="filters.{{ $report['slug'] }}.scope">
                                                <option value="current">Current</option><option value="archived">Archived</option><option value="all">All</option>
                                            </select>
                                        </label>
                                    @endif
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-sort">Sort by
                                        <select id="{{ $report['slug'] }}-sort" wire:model="filters.{{ $report['slug'] }}.sort">
                                            @foreach($report['sorts'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-direction">Direction
                                        <select id="{{ $report['slug'] }}-direction" wire:model="filters.{{ $report['slug'] }}.direction"><option value="asc">Ascending</option><option value="desc">Descending</option></select>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="hoa-reports__actions">
                                    @foreach($report['formats'] as $format)
                                        <button type="button" wire:click="downloadReport('{{ $report['slug'] }}', '{{ $format }}')" wire:loading.attr="disabled" wire:target="downloadReport('{{ $report['slug'] }}', '{{ $format }}')" class="hoa-reports__button hoa-reports__button--{{ $format }}">{{ strtoupper($format) }}</button>
                                    @endforeach
                                    <button id="report-preview-{{ $report['slug'] }}" type="button" wire:click="previewReport('{{ $report['slug'] }}')" wire:loading.attr="disabled" wire:target="previewReport('{{ $report['slug'] }}')" class="hoa-reports__button hoa-reports__button--preview">
                                        <span wire:loading.remove wire:target="previewReport('{{ $report['slug'] }}')">Preview</span><span wire:loading wire:target="previewReport('{{ $report['slug'] }}')">Loading…</span>
                                    </button>
                                    <button type="button" wire:click="queueReport('{{ $report['slug'] }}', 'csv')" wire:loading.attr="disabled" class="hoa-reports__button hoa-reports__button--queue">Queue CSV</button>
                                    <button type="button" wire:click="queueReport('{{ $report['slug'] }}', 'xlsx')" wire:loading.attr="disabled" class="hoa-reports__button hoa-reports__button--queue">Queue XLSX</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hoa-reports__empty">No reports match the selected catalog filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($totalReports > 0)
                <div class="hoa-reports__pagination">
                    <div class="hoa-reports__pagination-summary" role="status" aria-live="polite">Showing {{ ($currentPage - 1) * $perPage + 1 }}–{{ min($currentPage * $perPage, $totalReports) }} of {{ $totalReports }} reports</div>
                    <nav aria-label="Reports pagination" class="hoa-reports__page-list">
                        <button type="button" wire:click="setPage({{ $currentPage - 1 }})" @disabled($currentPage <= 1) class="hoa-reports__page-button">Previous</button>
                        @for($i = 1; $i <= $totalPages; $i++)<button type="button" wire:click="setPage({{ $i }})" aria-label="Page {{ $i }}" @if($i === $currentPage) aria-current="page" @endif class="hoa-reports__page-button">{{ $i }}</button>@endfor
                        <button type="button" wire:click="setPage({{ $currentPage + 1 }})" @disabled($currentPage >= $totalPages) class="hoa-reports__page-button">Next</button>
                    </nav>
                </div>
            @endif
        </section>

        <section class="hoa-reports__card" aria-labelledby="queued-exports-title" wire:poll.10s>
            <header class="hoa-reports__card-header"><h2 id="queued-exports-title" class="hoa-reports__section-heading">My queued exports</h2><p class="hoa-reports__section-copy">Private CSV and XLSX files expire seven days after completion.</p></header>
            <div class="hoa-reports__scroll" role="region" aria-label="Recent queued exports" tabindex="0">
                <table class="hoa-reports__table hoa-reports__table--compact"><caption class="sr-only">Your ten most recent queued exports</caption>
                    <thead><tr><th>Report</th><th>Format</th><th>Requested</th><th>Status</th><th>File</th></tr></thead>
                    <tbody>@forelse($recentExports as $export)<tr>
                        <td>{{ app(\App\Services\Reporting\HoaReportCatalog::class)->definitions()[$export->report]['title'] ?? $export->report }}</td><td>{{ strtoupper($export->format) }}</td><td>{{ $export->created_at->format('M j, Y g:i A') }}</td><td>{{ $export->status }}</td>
                        <td>@if($export->status === 'Completed' && ! $export->expires_at?->isPast())<a class="hoa-reports__link" href="{{ route($panel === 'staff' ? 'staff.reports.queued.download' : 'admin.reports.queued.download', $export) }}">Download {{ strtoupper($export->format) }}</a>@elseif($export->status === 'Failed')<span title="{{ $export->failure_reason }}">Failed</span>@else<span>Not ready</span>@endif</td>
                    </tr>@empty<tr><td colspan="5" class="hoa-reports__empty">No queued exports yet.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>
    </div>

    @if($previewSlug)
        <div class="hoa-report-dialog" role="dialog" aria-modal="true" aria-labelledby="report-preview-title" wire:keydown.escape.window="closePreview" x-data x-trap.inert.noscroll="true">
            <div class="hoa-report-dialog__panel">
                <div class="hoa-report-dialog__header"><h2 id="report-preview-title" class="hoa-report-dialog__title">{{ $previewTitle }} — Preview</h2><button type="button" wire:click="closePreview" data-close-preview aria-label="Close report preview" class="hoa-report-dialog__close">×</button></div>
                <div class="hoa-report-dialog__summary" aria-label="Report summary">@foreach($previewSummary as $label => $value)<div><span>{{ $label }}</span><strong>{{ is_numeric($value) ? $value : $value }}</strong></div>@endforeach</div>
                <div class="hoa-report-dialog__body"><table class="hoa-report-dialog__table"><caption class="sr-only">{{ $previewTitle }} preview page {{ $previewPage }}</caption><thead><tr>@foreach($previewHeadings as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                    @forelse($previewRows as $row)<tr>@foreach($row as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($previewHeadings) }}" class="hoa-reports__empty">No records matched the selected filters.</td></tr>@endforelse
                </tbody></table></div>
                <div class="hoa-report-dialog__footer"><span>Page {{ $previewPage }}</span><div><button type="button" wire:click="previewPage({{ $previewPage - 1 }})" @disabled($previewPage <= 1) class="hoa-reports__page-button">Previous</button><button type="button" wire:click="previewPage({{ $previewPage + 1 }})" @disabled(!$previewHasMore) class="hoa-reports__page-button">Next</button></div></div>
            </div>
        </div>
    @endif

    @script
    <script>
        $wire.on('report-preview-opened', () => requestAnimationFrame(() => document.querySelector('[data-close-preview]')?.focus()));
        $wire.on('report-preview-closed', (event) => {
            const detail = Array.isArray(event) ? event[0] : event;
            requestAnimationFrame(() => document.getElementById(detail?.triggerId)?.focus());
        });
    </script>
    @endscript
</x-filament-panels::page>
