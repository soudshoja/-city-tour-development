@props(['data'])

<div class="dataTable-bottom w-full">
    <div class="flex flex-col items-center mt-4 px-4 py-3 bg-gray-50 dark:bg-gray-700 rounded-full w-full">
        <div class="text-sm text-gray-600 dark:text-gray-400 mb-3 text-center w-full">
            Showing {{ $data->firstItem() ?? 0 }} to {{ $data->lastItem() ?? 0 }} of {{ $data->total() ?? 0 }} results
        </div>

        @if ($data->hasPages())
        <nav class="dataTable-pagination w-full flex justify-center" role="navigation" aria-label="Pagination Navigation">
            <ul class="dataTable-pagination-list flex items-center gap-1 bg-white dark:bg-gray-800 rounded-full border border-gray-200 dark:border-gray-700 p-1">
                {{-- First Page Link - Better Design --}}
                @if ($data->currentPage() > 1)
                    <li>
                        <a href="{{ $data->appends(request()->query())->url(1) }}"
                            class="flex items-center justify-center w-9 h-9 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded-full border border-gray-200 dark:border-gray-600 transition-colors"
                            title="First Page">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 17L13 12L18 7M11 17L6 12L11 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                @endif

                {{-- Previous Page Link - Better Design --}}
                @if (!$data->onFirstPage())
                <li>
                    <a href="{{ $data->appends(request()->query())->previousPageUrl() }}"
                        class="flex items-center justify-center w-9 h-9 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded-full border border-gray-200 dark:border-gray-600 transition-colors"
                        title="Previous Page">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </li>
                @endif

                {{-- Show 2 pages: one inactive + active page (only active loads) --}}
                @php
                $current = $data->currentPage();
                $last = $data->lastPage();
                
                // Determine which 2 pages to show
                if ($current == 1) {
                    $prevPage = null;
                    $nextPage = $last > 1 ? 2 : null;
                } else {
                    $prevPage = $current - 1;
                    $nextPage = null;
                }
                @endphp

                {{-- Show previous page (clickable but lazy-loaded) --}}
                @if($prevPage)
                <li>
                    <a href="{{ $data->appends(request()->query())->url($prevPage) }}"
                        class="flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-gray-800 dark:hover:text-gray-100 rounded-full font-semibold transition-colors cursor-pointer"
                        title="Go to page {{ $prevPage }}">
                        {{ $prevPage }}
                    </a>
                </li>
                @endif

                {{-- Show active page (fully loaded) --}}
                <li>
                    <span class="flex items-center justify-center w-10 h-10 text-white bg-blue-600 dark:bg-blue-500 hover:bg-blue-700 dark:hover:bg-blue-600 rounded-full font-semibold transition-colors cursor-pointer">
                        {{ $current }}
                    </span>
                </li>

                {{-- Show next page (clickable but lazy-loaded) --}}
                @if($nextPage)
                <li>
                    <a href="{{ $data->appends(request()->query())->url($nextPage) }}"
                        class="flex items-center justify-center w-8 h-8 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-gray-800 dark:hover:text-gray-100 rounded-full font-semibold transition-colors cursor-pointer"
                        title="Go to page {{ $nextPage }}">
                        {{ $nextPage }}
                    </a>
                </li>
                @endif

                {{-- Next Page Link - Better Design --}}
                @if ($data->hasMorePages())
                    <li>
                        <a href="{{ $data->appends(request()->query())->nextPageUrl() }}"
                            class="flex items-center justify-center w-9 h-9 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded-full border border-gray-200 dark:border-gray-600 transition-colors"
                            title="Next Page">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                @endif

                {{-- Last Page Link - Better Design --}}
                @if ($data->currentPage() < $data->lastPage())
                    <li>
                        <a href="{{ $data->appends(request()->query())->url($data->lastPage()) }}"
                            class="flex items-center justify-center w-9 h-9 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded-full border border-gray-200 dark:border-gray-600 transition-colors"
                            title="Last Page">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 17L11 12L6 7M13 17L18 12L13 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </li>
                @endif
            </ul>
        </nav>
        @endif
    </div>
</div>