@php
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();

    match ($routeName) {
        'docs.user-documentation' => [
            $title = __('doc.header.title'),
            $headerTitle = __('doc.header.title'),
            $backLabel = __('doc.header.backToApp'),
            $navTitle = __('doc.header.navigation'),
            $searchPlaceholder = __('doc.header.searchPlaceholder'),
            $defaultSection = 'welcome',
            $navPartial = 'docs.partials.user-nav',
        ],
        'docs.api-documentation' => [
            $title = __('apidoc.header.title'),
            $headerTitle = __('apidoc.header.title'),
            $backLabel = __('apidoc.header.backToApp'),
            $navTitle = __('apidoc.header.navigation'),
            $searchPlaceholder = __('apidoc.header.searchPlaceholder'),
            $defaultSection = 'overview',
            $navPartial = 'docs.partials.api-nav',
        ],
        default => [
            $title = __('devdoc.header.title'),
            $headerTitle = __('devdoc.header.title'),
            $backLabel = __('devdoc.header.backToApp'),
            $navTitle = __('devdoc.header.navigation'),
            $searchPlaceholder = __('devdoc.header.searchPlaceholder'),
            $defaultSection = 'welcome',
            $navPartial = 'docs.partials.developer-nav',
        ],
    };
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>{{ $title }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef1fb', 100: '#d4daf4', 200: '#a9b5e9', 300: '#7e90de',
                            400: '#536bd3', 500: '#2945a2', 600: '#213882', 700: '#192a61',
                            800: '#111d41', 900: '#080f20',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        html { scroll-behavior: smooth; }
        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { transform: translateX(4px); }
        #progress-bar { transition: width 0.1s linear; }
        .step-number {
            width: 28px; height: 28px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
            background-color: #2945a2; color: #fff; flex-shrink: 0;
        }
        .dark .step-number { background-color: #536bd3; }
        .info-box {
            background-color: #eef1fb; border-left: 4px solid #2945a2;
            padding: 1rem 1.25rem; border-radius: 0 0.5rem 0.5rem 0; margin: 1rem 0;
        }
        .dark .info-box { background-color: #111d41; border-left-color: #536bd3; }
        .warn-box {
            background-color: #fef3c7; border-left: 4px solid #f59e0b;
            padding: 1rem 1.25rem; border-radius: 0 0.5rem 0.5rem 0; margin: 1rem 0;
        }
        .dark .warn-box { background-color: #78350f; border-left-color: #fbbf24; }
        .doc-gif-wrap { position: relative; margin: 1rem 0; }
        .doc-gif-wrap .gif-badge {
            position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.6);
            color: #fff; font-size: 11px; font-weight: 600; padding: 3px 10px;
            border-radius: 20px; pointer-events: none; z-index: 1; letter-spacing: 0.5px;
        }
        .doc-gif { border-radius: 0.5rem; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.12); width: 100%; max-width: 100%; height: auto; }
        .dark .doc-gif { border-color: #374151; }
        .code-block { position: relative; }
        .code-block pre {
            scrollbar-width: thin; scrollbar-color: rgba(156,163,175,0.5) transparent;
            border-radius: 0.5rem; padding: 1rem; overflow-x: auto; font-size: 0.85rem;
        }
        .copy-btn {
            position: absolute; top: 8px; right: 8px; opacity: 0.7; transition: opacity 0.2s;
            background: rgba(255,255,255,0.1); border: none; color: #9ca3af; padding: 4px 8px;
            border-radius: 4px; cursor: pointer; font-size: 12px;
        }
        .copy-btn:hover { opacity: 1; }
        @media (max-width: 639px) {
            .info-box, .warn-box { padding: 0.75rem 1rem; }
            section h2 { font-size: 1.25rem; }
            section h3 { font-size: 1rem; }
            .doc-gif-wrap .gif-badge { top: 8px; right: 8px; font-size: 10px; padding: 2px 8px; }
        }
        /* RTL Support */
        [dir="rtl"] body { font-family: 'Noto Sans Arabic', 'Inter', sans-serif; }
        [dir="rtl"] .info-box { border-left: none; border-right: 4px solid #2945a2; border-radius: 0.5rem 0 0 0.5rem; }
        [dir="rtl"] .dark .info-box { border-left-color: transparent; border-right-color: #536bd3; }
        [dir="rtl"] .warn-box { border-left: none; border-right: 4px solid #f59e0b; border-radius: 0.5rem 0 0 0.5rem; }
        [dir="rtl"] .dark .warn-box { border-left-color: transparent; border-right-color: #fbbf24; }
        [dir="rtl"] .sidebar-link:hover { transform: translateX(-4px); }
        [dir="rtl"] .doc-gif-wrap .gif-badge { right: auto; left: 12px; }
        [dir="rtl"] .copy-btn { right: auto; left: 8px; }
        @media (max-width: 639px) {
            [dir="rtl"] .doc-gif-wrap .gif-badge { right: auto; left: 8px; }
        }
        @stack('styles')
    </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200"
      x-data="docsApp()" x-init="init()">

    <div class="fixed top-0 start-0 w-full h-1 z-50 bg-gray-200 dark:bg-gray-700">
        <div id="progress-bar" class="h-full bg-primary-500" :style="'width:' + scrollProgress + '%'"></div>
    </div>

    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-1 z-40">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 sm:py-4 flex justify-between items-center gap-2">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 flex-shrink-0">
                    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                </button>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 sm:h-8 sm:w-8 text-primary-500 flex-shrink-0 hidden sm:block" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                </svg>
                <h1 class="text-base sm:text-xl font-bold text-gray-900 dark:text-white truncate">{{ $headerTitle }}</h1>
                @stack('header-badge')
            </div>
            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 whitespace-nowrap">
                    <i class="fas fa-arrow-left me-1"></i> {{ $backLabel }}
                </a>
                <a href="{{ route('locale.switch', ['lang' => app()->getLocale() === 'en' ? 'ar' : 'en']) }}?redirect={{ urlencode(request()->url()) }}"
                   class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                   title="{{ app()->getLocale() === 'en' ? 'Switch to Arabic' : 'Switch to English' }}">
                    <span class="text-sm font-bold text-gray-600 dark:text-gray-300">
                        {{ app()->getLocale() === 'en' ? 'AR' : 'EN' }}
                    </span>
                </a>
                <button @click="toggleDarkMode()" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 dark:hidden" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden dark:block text-yellow-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    {{-- Mobile sidebar overlay --}}
    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false" class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

    {{-- Mobile sidebar --}}
    <div x-show="sidebarOpen"
         class="fixed inset-y-0 z-50 w-72 bg-white dark:bg-gray-800 shadow-xl lg:hidden overflow-y-auto transition-transform duration-200 {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }}"
         x-transition:enter="transition ease-in-out duration-200 transform"
         x-transition:enter-start="{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}">
        <div class="p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $navTitle }}</h2>
                <button @click="sidebarOpen = false" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="relative mb-4">
                <input type="text" x-model="searchQuery" @input="filterSections()" placeholder="{{ $searchPlaceholder }}"
                       class="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <i class="fas fa-search absolute start-3 top-2.5 text-gray-400"></i>
            </div>
            <div x-show="searchQuery.length > 0 && searchResults.length > 0" class="mb-4 bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                <template x-for="result in searchResults" :key="result.id">
                    <a :href="'#' + result.id" @click="scrollToSection(result.id)" class="block px-3 py-2 text-sm rounded hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                        <span x-text="result.title" class="font-medium"></span>
                    </a>
                </template>
            </div>
            <template x-for="group in navGroups" :key="group.label">
                <div class="mb-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1 px-3" x-text="group.label"></p>
                    <template x-for="link in group.links" :key="link.id">
                        <a :href="'#' + link.id" @click="scrollToSection(link.id)"
                           class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md"
                           :class="activeSection === link.id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                            <i :class="link.icon + ' w-4 me-3'"></i>
                            <span x-text="link.title"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">

            {{-- Desktop sidebar --}}
            <div class="hidden lg:block lg:col-span-3">
                <nav class="sticky top-24 space-y-1 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm max-h-[calc(100vh-8rem)] overflow-y-auto">
                    <div class="relative mb-4">
                        <input type="text" x-model="searchQuery" @input="filterSections()" placeholder="{{ $searchPlaceholder }}"
                               class="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <i class="fas fa-search absolute start-3 top-2.5 text-gray-400"></i>
                    </div>
                    <div x-show="searchQuery.length > 0 && searchResults.length > 0" class="mb-4 bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                        <template x-for="result in searchResults" :key="result.id">
                            <a :href="'#' + result.id" @click="scrollToSection(result.id)" class="block px-3 py-2 text-sm rounded hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                <span x-text="result.title" class="font-medium"></span>
                            </a>
                        </template>
                    </div>
                    <div x-show="searchQuery.length === 0">
                        <template x-for="group in navGroups" :key="group.label">
                            <div class="mb-3">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1 px-3" x-text="group.label"></p>
                                <template x-for="link in group.links" :key="link.id">
                                    <a :href="'#' + link.id" @click.prevent="scrollToSection(link.id)"
                                       class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md"
                                       :class="activeSection === link.id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                        <i :class="link.icon + ' w-4 me-3'" :style="activeSection === link.id ? 'color: #2945a2' : ''"></i>
                                        <span x-text="link.title"></span>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </nav>
            </div>

            {{-- Main content area --}}
            <div class="mt-4 sm:mt-8 lg:mt-0 lg:col-span-9 min-w-0">
                {{ $slot }}
            </div>

        </div>
    </div>

    {{-- Scroll to top button --}}
    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            x-show="scrollProgress > 10" x-transition
            class="fixed bottom-8 end-8 bg-primary-500 text-white w-10 h-10 rounded-full shadow-lg flex items-center justify-center hover:bg-primary-600 transition-colors z-30">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script>
        function docsApp() {
            @include($navPartial)

            return {
                sidebarOpen: false,
                activeSection: '{{ $defaultSection }}',
                scrollProgress: 0,
                searchQuery: '',
                searchResults: [],
                navGroups: groups,
                sections: sections,
                init() {
                    if (localStorage.getItem('docs-theme') === 'dark' || (!localStorage.getItem('docs-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                    window.addEventListener('scroll', () => {
                        const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                        this.scrollProgress = height > 0 ? (winScroll / height) * 100 : 0;
                    });
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) this.activeSection = entry.target.id;
                        });
                    }, { rootMargin: '-100px 0px -66% 0px' });
                    document.querySelectorAll('section[id]').forEach(section => observer.observe(section));
                },
                toggleDarkMode() {
                    document.documentElement.classList.toggle('dark');
                    localStorage.setItem('docs-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                },
                scrollToSection(id) {
                    this.sidebarOpen = false;
                    this.searchQuery = '';
                    this.searchResults = [];
                    const el = document.getElementById(id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
                filterSections() {
                    if (this.searchQuery.length === 0) { this.searchResults = []; return; }
                    const q = this.searchQuery.toLowerCase();
                    this.searchResults = this.sections.filter(s =>
                        s.title.toLowerCase().includes(q) || s.keywords.toLowerCase().includes(q)
                    ).slice(0, 8);
                },
            };
        }
    </script>
    @stack('scripts')
</body>
</html>
