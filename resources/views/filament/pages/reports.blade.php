<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 id="reports-and-exports-title" class="text-2xl font-bold text-gray-950 dark:text-white">{{ $title }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Generate and export HOA reports. Click a column header to sort.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <label for="reports-per-page" class="text-sm text-gray-600 dark:text-gray-400">Per page:</label>
                <select id="reports-per-page" wire:model.live="perPage" class="fi-select-input rounded-lg border-gray-300 text-sm">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>

        <section aria-labelledby="reports-and-exports-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="overflow-x-auto">
                @php
                    $totalReports = $allReports->count();
                    $totalPages = max(1, (int) ceil($totalReports / $perPage));
                    $currentPage = max(1, min($this->getPage(), $totalPages));
                    $paginatedReports = $allReports->forPage($currentPage, $perPage)->values();
                @endphp

                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Available HOA reports, filters, and export actions</caption>
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" aria-sort="{{ $sortColumn === 'title' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('title')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                    Report
                                    @if($sortColumn === 'title')
                                        <svg aria-hidden="true" focusable="false" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" aria-sort="{{ $sortColumn === 'description' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('description')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                    Description
                                    @if($sortColumn === 'description')
                                        <svg aria-hidden="true" focusable="false" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" aria-sort="{{ $sortColumn === 'category' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <button type="button" wire:click="sortBy('category')" class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                    Category
                                    @if($sortColumn === 'category')
                                        <svg aria-hidden="true" focusable="false" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            @if($sortDirection === 'asc')
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                            @endif
                                        </svg>
                                    @endif
                                </button>
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Filters</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @if($paginatedReports->isEmpty())
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No reports found in this category.
                                </td>
                            </tr>
                        @else
                            @foreach($paginatedReports as $report)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $report['title'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $report['formats'] ? implode(', ', array_map('strtoupper', $report['formats'])) : 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $report['description'] }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $report['category'] === 'Payments'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : ($report['category'] === 'Compliance'
                                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200') }}">
                                            {{ $report['category'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="grid min-w-64 gap-2 sm:grid-cols-2">
                                            <label class="text-xs text-gray-600 dark:text-gray-300">From
                                                <input type="date" wire:model="filters.{{ $report['slug'] }}.from" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-900">
                                            </label>
                                            <label class="text-xs text-gray-600 dark:text-gray-300">To
                                                <input type="date" wire:model="filters.{{ $report['slug'] }}.to" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-900">
                                            </label>
                                            <label class="text-xs text-gray-600 dark:text-gray-300 sm:col-span-2">Status
                                                <select wire:model="filters.{{ $report['slug'] }}.status" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-900">
                                                    <option value="">All statuses</option>
                                                    @foreach($report['statuses'] as $status)
                                                        <option value="{{ $status }}">{{ $status }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @foreach($report['formats'] as $format)
                                                <button
                                                    type="button"
                                                    wire:click="downloadReport('{{ $report['slug'] }}', '{{ $format }}')"
                                                    class="fi-btn rounded-lg px-3 py-1.5 text-xs font-semibold text-white whitespace-nowrap
                                                        {{ $format === 'csv'
                                                            ? 'bg-green-600 hover:bg-green-500'
                                                            : 'bg-red-600 hover:bg-red-500' }}"
                                                >
                                                    {{ strtoupper($format) }}
                                                </button>
                                            @endforeach
                                            <button
                                                type="button"
                                                wire:click="queueReport('{{ $report['slug'] }}')"
                                                class="fi-btn rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-semibold text-white whitespace-nowrap hover:bg-blue-600"
                                                title="Generate a private CSV in the queue for large datasets"
                                            >
                                                Queue CSV
                                            </button>
                                            <button
                                                id="report-preview-{{ $report['slug'] }}"
                                                type="button"
                                                wire:click="previewReport('{{ $report['slug'] }}')"
                                                class="fi-btn rounded-lg bg-gray-600 hover:bg-gray-500 px-3 py-1.5 text-xs font-semibold text-white whitespace-nowrap"
                                            >
                                                Preview
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            @if($totalReports > 0)
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ ($currentPage - 1) * $perPage + 1 }} to {{ min($currentPage * $perPage, $totalReports) }} of {{ $totalReports }} reports
                    </div>
                    <nav aria-label="Reports pagination" class="flex items-center gap-1">
                        <button
                            type="button"
                            wire:click="setPage({{ $currentPage - 1 }})"
                            @if($currentPage <= 1) disabled @endif
                            class="px-3 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            Previous
                        </button>

                        @for($i = 1; $i <= $totalPages; $i++)
                            <button
                                type="button"
                                wire:click="setPage({{ $i }})"
                                aria-label="Page {{ $i }}"
                                @if($i === $currentPage) aria-current="page" @endif
                                class="px-3 py-1 text-sm rounded-lg border {{ $i === $currentPage ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                            >
                                {{ $i }}
                            </button>
                        @endfor

                        <button
                            type="button"
                            wire:click="setPage({{ $currentPage + 1 }})"
                            @if($currentPage >= $totalPages) disabled @endif
                            class="px-3 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            Next
                        </button>
                    </nav>
                </div>
            @endif
        </section>

        <section aria-labelledby="queued-exports-title" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2 id="queued-exports-title" class="text-lg font-semibold text-gray-950 dark:text-white">My queued exports</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Private CSV files expire seven days after completion.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <caption class="sr-only">Your five most recent queued report exports</caption>
                    <thead><tr><th scope="col" class="px-3 py-2 text-left">Report</th><th scope="col" class="px-3 py-2 text-left">Requested</th><th scope="col" class="px-3 py-2 text-left">Status</th><th scope="col" class="px-3 py-2 text-right">File</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($recentExports as $export)
                            <tr wire:key="report-export-{{ $export->id }}">
                                <td class="px-3 py-2">{{ \App\Exports\HoaReportExport::TITLES[$export->report] ?? $export->report }}</td>
                                <td class="px-3 py-2">{{ $export->created_at->format('M j, Y g:i A') }}</td>
                                <td class="px-3 py-2">{{ $export->status }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if($export->status === 'Completed' && ! $export->expires_at?->isPast())
                                        <a class="font-semibold text-primary-600 underline" href="{{ route($panel === 'staff' ? 'staff.reports.queued.download' : 'admin.reports.queued.download', $export) }}">Download CSV</a>
                                    @elseif($export->status === 'Failed')
                                        <span class="text-danger-600" title="{{ $export->failure_reason }}">Failed</span>
                                    @else
                                        <span class="text-gray-500">Not ready</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-5 text-center text-gray-500">No queued exports yet.</td></tr>
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
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50';
                modal.dataset.reportPreview = '';
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-labelledby', 'report-preview-title');
                modal.innerHTML = `
                    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-6xl w-full max-h-[80vh] overflow-hidden">
                        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 id="report-preview-title" class="text-lg font-semibold text-gray-950 dark:text-white">${escapeHtml(data.title)} - Preview (First 10 rows)</h2>
                            <button type="button" data-close-preview aria-label="Close report preview" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="p-4 overflow-auto max-h-[calc(80vh-60px)]">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <caption class="sr-only">${escapeHtml(data.title)} report preview</caption>
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        ${data.headings.map(h => `<th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">${escapeHtml(h)}</th>`).join('')}
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    ${data.rows.map(row => `<tr>${row.map(v => `<td class="px-3 py-2 text-sm text-gray-950 dark:text-white">${escapeHtml(v)}</td>`).join('')}</tr>`).join('')}
                                    ${data.hasMore ? '<tr><td colspan="' + data.headings.length + '" class="px-3 py-2 text-center text-sm text-gray-500 dark:text-gray-400">... and more rows (apply filters or download for full data)</td></tr>' : ''}
                                    ${data.rows.length === 0 ? '<tr><td colspan="' + data.headings.length + '" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">No records matched the selected filters.</td></tr>' : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                const close = () => {
                    modal.remove();

                    if (previouslyFocused instanceof HTMLElement && previouslyFocused.isConnected) {
                        requestAnimationFrame(() => previouslyFocused.focus({ preventScroll: true }));
                    }
                };
                const closeButton = modal.querySelector('[data-close-preview]');
                closeButton.addEventListener('click', close);
                closeButton.focus();
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) close();
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
