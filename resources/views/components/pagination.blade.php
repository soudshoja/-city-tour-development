@props(['data'])

<div class="dataTable-bottom justify-center">
    <div class="flex flex-col gap-2 sm:flex-row justify-between items-center mt-4 px-4 py-3 bg-gray-50 dark:bg-gray-700 rounded-full">
        <!-- Showing results info -->
        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2 sm:mb-0">
            Showing {{ $data->firstItem() ?? 0 }} to {{ $data->lastItem() ?? 0 }} of {{ $data->total() ?? 0 }} results
        </div>

        <!-- Custom pagination -->
        @if ($data->hasPages())
        <nav class="dataTable-pagination">
            <ul class="dataTable-pagination-list flex gap-1">
                {{-- Previous Page Link --}}
                @if ($data->onFirstPage())
                <li class="pager disabled">
                    <span class="flex items-center justify-center w-10 h-10 text-gray-400 cursor-not-allowed bg-gray-200 dark:bg-gray-600 rounded-full">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg" class="w-4 h-4">
                            <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                            <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </span>
                </li>
                @else
                <li class="pager">
                    <a href="{{ $data->appends(request()->query())->previousPageUrl() }}"
                        class="flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-full transition-colors duration-200">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg" class="w-4 h-4">
                            <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                            <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </li>
                @endif

                {{-- Pagination Elements --}}
                @php
                $start = max(1, $data->currentPage() - 2);
                $end = min($data->lastPage(), $data->currentPage() + 2);
                @endphp

                @if ($start > 1)
                <li class="pager">
                    <a href="{{ $data->appends(request()->query())->url(1) }}"
                        class="flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-full transition-colors duration-200 font-medium">
                        1
                    </a>
                </li>
                @if ($start > 2)
                <li class="pager">
                    <span class="flex items-center justify-center w-10 h-10 text-gray-500">...</span>
                </li>
                @endif
                @endif

                @for ($page = $start; $page <= $end; $page++)
                    @if ($page==$data->currentPage())
                    <li class="pager active">
                        <span class="flex items-center justify-center w-10 h-10 bg-blue-600 text-white rounded-full font-semibold border border-blue-600">
                            {{ $page }}
                        </span>
                    </li>
                    @else
                    <li class="pager">
                        <a href="{{ $data->appends(request()->query())->url($page) }}"
                            class="flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-full transition-colors duration-200 font-medium">
                            {{ $page }}
                        </a>
                    </li>
                    @endif
                    @endfor

                    @if ($end < $data->lastPage())
                        @if ($end < $data->lastPage() - 1)
                            <li class="pager">
                                <span class="flex items-center justify-center w-10 h-10 text-gray-500">...</span>
                            </li>
                            @endif
                            <li class="pager">
                                <a href="{{ $data->appends(request()->query())->url($data->lastPage()) }}"
                                    class="flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-full transition-colors duration-200 font-medium">
                                    {{ $data->lastPage() }}
                                </a>
                            </li>
                            @endif

                            {{-- Next Page Link --}}
                            @if ($data->hasMorePages())
                            <li class="pager">
                                <a href="{{ $data->appends(request()->query())->nextPageUrl() }}"
                                    class="flex items-center justify-center w-10 h-10 text-gray-600 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-full transition-colors duration-200">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-4 h-4">
                                        <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </a>
                            </li>
                            @else
                            <li class="pager disabled">
                                <span class="flex items-center justify-center w-10 h-10 text-gray-400 cursor-not-allowed bg-gray-200 dark:bg-gray-600 rounded-full">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-4 h-4">
                                        <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </span>
                            </li>
                            @endif
            </ul>
        </nav>
        @endif
    </div>
</div>