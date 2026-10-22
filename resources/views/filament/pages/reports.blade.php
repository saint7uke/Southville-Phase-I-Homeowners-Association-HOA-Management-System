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
                <h2 id="reports-catalog-title" data-i18n="Reports and exports">{{ __('Reports and exports') }}</h2>
                <p data-i18n="Preview live database records or export the same filtered dataset. Financial reports use the current property ledger.">{{ __('Preview live database records or export the same filtered dataset. Financial reports use the current property ledger.') }}</p>
            </div>

            <div class="hoa-reports__controls" role="search" aria-label="Search and filter report catalog">
                <label class="hoa-reports__control" for="reports-search"><span data-i18n="Search reports">{{ __('Search reports') }}</span>
                    <input id="reports-search" type="search" wire:model.live.debounce.300ms="catalogSearch" data-i18n-placeholder="Search report names" placeholder="{{ __('Search report names') }}">
                </label>
                <label class="hoa-reports__control" for="reports-category"><span data-i18n="Category">{{ __('Category') }}</span>
                    <select id="reports-category" wire:model.live="selectedCategory">
                        @foreach($categories as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
                    </select>
                </label>
                <label class="hoa-reports__control" for="reports-per-page"><span data-i18n="Rows per page">{{ __('Rows per page') }}</span>
                    <select id="reports-per-page" wire:model.live="perPage">
                        @foreach([5, 10, 25, 50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                    </select>
                </label>
            </div>
        </header>

        @if($errors->any())
            <div class="hoa-reports__error" role="alert" tabindex="-1" data-report-error x-data x-init="$nextTick(() => $el.focus())">
                <strong data-i18n="Review the report filters.">{{ __('Review the report filters.') }}</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="hoa-reports__card" aria-labelledby="reports-catalog-title">
            <div class="hoa-reports__scroll" role="region" aria-label="Available HOA reports" tabindex="0">
                <table class="hoa-reports__table">
                    <caption class="sr-only"><span data-i18n="Available HOA reports, filters, and export actions">{{ __('Available HOA reports, filters, and export actions') }}</span></caption>
                    <thead><tr>
                        @foreach(['title' => 'Report', 'description' => 'Description', 'category' => 'Category'] as $column => $label)
                            <th scope="col" class="hoa-reports__column-{{ $column === 'title' ? 'report' : $column }}" aria-sort="{{ $sortColumn === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('{{ $column }}')" aria-label="Sort by {{ strtolower($column === 'title' ? 'report name' : $label) }} {{ $sortColumn === $column && $sortDirection === 'asc' ? 'descending' : 'ascending' }}" class="hoa-reports__sort">
                                    {{ __($label) }} @if($sortColumn === $column)<span aria-hidden="true">{{ $sortDirection === 'asc' ? '↓' : '↑' }}</span>@endif
                                </button>
                            </th>
                        @endforeach
                        <th scope="col" class="hoa-reports__column-filters" data-i18n="Dataset filters">{{ __('Dataset filters') }}</th>
                        <th scope="col" class="hoa-reports__column-actions" data-i18n="Actions">{{ __('Actions') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($reports as $report)
                        <tr wire:key="report-row-{{ $report['slug'] }}">
                            <td><div class="hoa-reports__report-title">{{ __($report['title']) }}</div><div class="hoa-reports__formats">{{ implode(' · ', array_map('strtoupper', $report['formats'])) }}</div></td>
                            <td><div class="hoa-reports__description">{{ __($report['description']) }}</div></td>
                            <td><span class="hoa-reports__badge">{{ __($report['category']) }}</span></td>
                            <td>
                                <div class="hoa-reports__filters">
                                    <label class="hoa-reports__filter hoa-reports__filter--wide" for="{{ $report['slug'] }}-q"><span data-i18n="Search records">{{ __('Search records') }}</span>
                                        <input id="{{ $report['slug'] }}-q" type="search" maxlength="120" wire:model="filters.{{ $report['slug'] }}.q" data-i18n-placeholder="Name, number, subject, or reference" placeholder="{{ __('Name, number, subject, or reference') }}">
                                    </label>
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-from">{{ __(':date from', ['date' => __($report['dateLabel'])]) }}
                                        <input id="{{ $report['slug'] }}-from" type="date" wire:model="filters.{{ $report['slug'] }}.from">
                                    </label>
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-to">{{ __(':date to', ['date' => __($report['dateLabel'])]) }}
                                        <input id="{{ $report['slug'] }}-to" type="date" wire:model="filters.{{ $report['slug'] }}.to">
                                    </label>
                                    @if($report['statuses'])
                                        <label class="hoa-reports__filter" for="{{ $report['slug'] }}-status"><span data-i18n="Status">{{ __('Status') }}</span>
                                            <select id="{{ $report['slug'] }}-status" wire:model="filters.{{ $report['slug'] }}.status">
                                                <option value="" data-i18n="All statuses">{{ __('All statuses') }}</option>
                                                @foreach($report['statuses'] as $status)<option value="{{ $status }}" data-i18n="{{ $status }}">{{ __($status) }}</option>@endforeach
                                            </select>
                                        </label>
                                    @endif
                                    @if($panel === 'admin' && $report['archives'])
                                        <label class="hoa-reports__filter" for="{{ $report['slug'] }}-scope"><span data-i18n="Record scope">{{ __('Record scope') }}</span>
                                            <select id="{{ $report['slug'] }}-scope" wire:model="filters.{{ $report['slug'] }}.scope">
                                                <option value="current" data-i18n="Current">{{ __('Current') }}</option><option value="archived" data-i18n="Archived">{{ __('Archived') }}</option><option value="all" data-i18n="All">{{ __('All') }}</option>
                                            </select>
                                        </label>
                                    @endif
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-sort"><span data-i18n="Sort by">{{ __('Sort by') }}</span>
                                        <select id="{{ $report['slug'] }}-sort" wire:model="filters.{{ $report['slug'] }}.sort">
                                            @foreach($report['sorts'] as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="hoa-reports__filter" for="{{ $report['slug'] }}-direction"><span data-i18n="Direction">{{ __('Direction') }}</span>
                                        <select id="{{ $report['slug'] }}-direction" wire:model="filters.{{ $report['slug'] }}.direction"><option value="asc" data-i18n="Ascending">{{ __('Ascending') }}</option><option value="desc" data-i18n="Descending">{{ __('Descending') }}</option></select>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="hoa-reports__actions">
                                    @foreach($report['formats'] as $format)
                                        <button type="button" wire:click="downloadReport('{{ $report['slug'] }}', '{{ $format }}')" wire:loading.attr="disabled" wire:target="downloadReport('{{ $report['slug'] }}', '{{ $format }}')" class="hoa-reports__button hoa-reports__button--{{ $format }}">{{ strtoupper($format) }}</button>
                                    @endforeach
                                    <button id="report-preview-{{ $report['slug'] }}" type="button" wire:click="previewReport('{{ $report['slug'] }}')" wire:loading.attr="disabled" wire:target="previewReport('{{ $report['slug'] }}')" class="hoa-reports__button hoa-reports__button--preview">
                                        <span wire:loading.remove wire:target="previewReport('{{ $report['slug'] }}')" data-i18n="Preview">{{ __('Preview') }}</span><span wire:loading wire:target="previewReport('{{ $report['slug'] }}')" data-i18n="Loading…">{{ __('Loading…') }}</span>
                                    </button>
                                    <button type="button" wire:click="queueReport('{{ $report['slug'] }}', 'csv')" wire:loading.attr="disabled" class="hoa-reports__button hoa-reports__button--queue" data-i18n="Queue CSV">{{ __('Queue CSV') }}</button>
                                    <button type="button" wire:click="queueReport('{{ $report['slug'] }}', 'xlsx')" wire:loading.attr="disabled" class="hoa-reports__button hoa-reports__button--queue" data-i18n="Queue XLSX">{{ __('Queue XLSX') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hoa-reports__empty"><span data-i18n="No reports match the selected catalog filters.">{{ __('No reports match the selected catalog filters.') }}</span></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($totalReports > 0)
                <div class="hoa-reports__pagination">
                    <div class="hoa-reports__pagination-summary" role="status" aria-live="polite">Showing {{ ($currentPage - 1) * $perPage + 1 }}–{{ min($currentPage * $perPage, $totalReports) }} of {{ $totalReports }} reports</div>
                    <nav aria-label="Reports pagination" class="hoa-reports__page-list">
                        <button type="button" wire:click="setPage({{ $currentPage - 1 }})" @disabled($currentPage <= 1) class="hoa-reports__page-button" data-i18n="Previous">{{ __('Previous') }}</button>
                        @for($i = 1; $i <= $totalPages; $i++)<button type="button" wire:click="setPage({{ $i }})" aria-label="Page {{ $i }}" @if($i === $currentPage) aria-current="page" @endif class="hoa-reports__page-button">{{ $i }}</button>@endfor
                        <button type="button" wire:click="setPage({{ $currentPage + 1 }})" @disabled($currentPage >= $totalPages) class="hoa-reports__page-button"><span data-i18n="Next">{{ __('Next') }}</span></button>
                    </nav>
                </div>
            @endif
        </section>

        <section class="hoa-reports__card" aria-labelledby="queued-exports-title" wire:poll.10s>
            <header class="hoa-reports__card-header"><h2 id="queued-exports-title" class="hoa-reports__section-heading" data-i18n="My queued exports">{{ __('My queued exports') }}</h2><p class="hoa-reports__section-copy" data-i18n="Private CSV and XLSX files expire seven days after completion.">{{ __('Private CSV and XLSX files expire seven days after completion.') }}</p></header>
            <div class="hoa-reports__scroll" role="region" aria-label="Recent queued exports" tabindex="0">
                <table class="hoa-reports__table hoa-reports__table--compact"><caption class="sr-only" data-i18n="Your ten most recent queued exports">{{ __('Your ten most recent queued exports') }}</caption>
                    <thead><tr><th data-i18n="Report">{{ __('Report') }}</th><th data-i18n="Format">{{ __('Format') }}</th><th data-i18n="Requested">{{ __('Requested') }}</th><th data-i18n="Status">{{ __('Status') }}</th><th data-i18n="File">{{ __('File') }}</th></tr></thead>
                    <tbody>@forelse($recentExports as $export)<tr>
                        <td>{{ app(\App\Services\Reporting\HoaReportCatalog::class)->definitions()[$export->report]['title'] ?? $export->report }}</td><td>{{ strtoupper($export->format) }}</td><td>{{ $export->created_at->format('M j, Y g:i A') }}</td><td>{{ $export->status }}</td>
                        <td>@if($export->status === 'Completed' && ! $export->expires_at?->isPast())<a class="hoa-reports__link" href="{{ route($panel === 'staff' ? 'staff.reports.queued.download' : 'admin.reports.queued.download', $export) }}">Download {{ strtoupper($export->format) }}</a>@elseif($export->status === 'Failed')<span title="{{ $export->failure_reason }}"><span data-i18n="Failed">{{ __('Failed') }}</span></span>@else<span data-i18n="Not ready">{{ __('Not ready') }}</span>@endif</td>
                    </tr>@empty<tr><td colspan="5" class="hoa-reports__empty"><span data-i18n="No queued exports yet.">{{ __('No queued exports yet.') }}</span></td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>
    </div>

    @if($previewSlug)
        <div class="hoa-report-dialog" role="dialog" aria-modal="true" aria-labelledby="report-preview-title" wire:keydown.escape.window="closePreview" x-data x-trap.inert.noscroll="true">
            <div class="hoa-report-dialog__panel">
                <div class="hoa-report-dialog__header"><h2 id="report-preview-title" class="hoa-report-dialog__title">{{ $previewTitle }} — Preview</h2><button type="button" wire:click="closePreview" data-close-preview aria-label="Close report preview" class="hoa-report-dialog__close">×</button></div>
                <div class="hoa-report-dialog__summary" aria-label="{{ __('Report summary') }}">@foreach($previewSummary as $label => $value)<div><span data-i18n="{{ $label }}">{{ __($label) }}</span><strong>{{ $value }}</strong></div>@endforeach</div>
                <div class="hoa-report-dialog__body"><table class="hoa-report-dialog__table"><caption class="sr-only">{{ $previewTitle }} preview page {{ $previewPage }}</caption><thead><tr>@foreach($previewHeadings as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                    @forelse($previewRows as $row)<tr>@foreach($row as $value)<td>{{ $value ?? '—' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($previewHeadings) }}" class="hoa-reports__empty"><span data-i18n="No records matched the selected filters.">{{ __('No records matched the selected filters.') }}</span></td></tr>@endforelse
                </tbody></table></div>
                <div class="hoa-report-dialog__footer"><span>Page {{ $previewPage }}</span><div><button type="button" wire:click="previewPage({{ $previewPage - 1 }})" @disabled($previewPage <= 1) class="hoa-reports__page-button" data-i18n="Previous">{{ __('Previous') }}</button><button type="button" wire:click="previewPage({{ $previewPage + 1 }})" @disabled(!$previewHasMore) class="hoa-reports__page-button" data-i18n="Next">{{ __('Next') }}</button></div></div>
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
