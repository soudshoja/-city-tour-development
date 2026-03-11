<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — DOTW Docs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
        body { font-family: 'Inter', sans-serif; }

        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { transform: translateX(4px); }
        .sidebar-link.active {
            background-color: rgb(243 244 246);
            color: rgb(37 99 235);
        }
        .dark .sidebar-link.active {
            background-color: rgb(55 65 81);
            color: rgb(96 165 250);
        }

        pre {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        pre::-webkit-scrollbar { width: 8px; height: 8px; }
        pre::-webkit-scrollbar-track { background: transparent; }
        pre::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.5); border-radius: 4px; }

        /* Prose overrides for code blocks */
        .prose pre {
            background-color: #1e293b !important;
            border: 1px solid #334155;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.84rem;
        }
        .dark .prose pre {
            background-color: #0f172a !important;
            border-color: #1e293b;
        }
        .prose code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.84em;
        }
        .prose :not(pre) > code {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 2px 6px;
            color: #0f766e;
        }
        .dark .prose :not(pre) > code {
            background-color: #1e293b;
            border-color: #334155;
            color: #34d399;
        }
        .prose pre code {
            background: none !important;
            border: none !important;
            padding: 0 !important;
            color: #e2e8f0 !important;
        }
        .prose table {
            font-size: 0.9rem;
        }
        .prose thead th {
            background-color: #f8fafc;
        }
        .dark .prose thead th {
            background-color: #1e293b;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="{{ url('/docs/dotw') }}" class="flex items-center space-x-3 text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    <i class="fas fa-hotel text-xl text-primary-600 dark:text-primary-400"></i>
                    <span class="text-lg font-bold">DOTW Docs</span>
                </a>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 truncate max-w-xs">{{ $title }}</span>
            </div>
            <div class="flex items-center space-x-4">
                <button id="darkModeToggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 dark:hidden" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden dark:block text-yellow-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                </button>
                @foreach($docs as $slug => $meta)
                    <a href="{{ url('/docs/dotw/' . $slug) }}"
                       class="text-sm font-medium transition-colors hidden lg:inline
                              {{ $current === $slug
                                  ? 'text-primary-600 dark:text-primary-400'
                                  : 'text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                        {{ match($slug) {
                            'overview'     => 'Overview',
                            'api'          => 'API Ref',
                            'services'     => 'Services',
                            'integration'  => 'Integration',
                            'architecture' => 'Architecture',
                            default        => $meta['title'],
                        } }}
                    </a>
                @endforeach
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">

            <!-- Sidebar -->
            <div class="hidden lg:block lg:col-span-3">
                <nav id="sidebar-nav" class="sticky top-24 space-y-1 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm max-h-[calc(100vh-8rem)] overflow-y-auto">
                    <div class="pb-2 mb-3 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contents</h2>
                    </div>
                    <!-- Populated by JS -->
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">
                <div class="prose prose-blue max-w-none dark:prose-invert
                            prose-headings:scroll-mt-24
                            prose-h1:text-3xl prose-h1:font-extrabold prose-h1:text-gray-900 dark:prose-h1:text-white
                            prose-h2:text-2xl prose-h2:font-bold prose-h2:text-gray-900 dark:prose-h2:text-white prose-h2:border-b prose-h2:border-gray-200 dark:prose-h2:border-gray-700 prose-h2:pb-2
                            prose-h3:text-xl prose-h3:font-semibold prose-h3:text-primary-700 dark:prose-h3:text-primary-400
                            prose-h4:text-base prose-h4:font-semibold
                            prose-p:text-gray-700 dark:prose-p:text-gray-300
                            prose-li:text-gray-700 dark:prose-li:text-gray-300
                            prose-strong:text-gray-900 dark:prose-strong:text-white
                            prose-table:text-sm
                            prose-th:bg-gray-50 dark:prose-th:bg-gray-800 prose-th:text-gray-700 dark:prose-th:text-gray-300
                            prose-td:text-gray-700 dark:prose-td:text-gray-300
                            prose-blockquote:border-primary-400 prose-blockquote:bg-blue-50 dark:prose-blockquote:bg-blue-950/30">
                    {!! $content !!}
                </div>

                <!-- Prev/Next nav -->
                <div class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                    @php
                        $slugs = array_keys($docs);
                        $idx   = array_search($current, $slugs);
                        $prev  = $idx > 0 ? $slugs[$idx - 1] : null;
                        $next  = $idx < count($slugs) - 1 ? $slugs[$idx + 1] : null;
                    @endphp
                    <div>
                        @if($prev)
                            <a href="{{ url('/docs/dotw/' . $prev) }}"
                               class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500">
                                <i class="fas fa-arrow-left text-xs"></i>
                                {{ $docs[$prev]['title'] }}
                            </a>
                        @endif
                    </div>
                    <div>
                        @if($next)
                            <a href="{{ url('/docs/dotw/' . $next) }}"
                               class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500">
                                {{ $docs[$next]['title'] }}
                                <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark mode — uses 'theme' key to avoid conflicting with main app's 'darkMode' key
        const savedTheme = localStorage.getItem('docsTheme');
        if (savedTheme === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
        document.getElementById('darkModeToggle').addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('docsTheme', isDark ? 'dark' : 'light');
        });

        // Build sidebar from headings
        document.addEventListener('DOMContentLoaded', () => {
            const nav     = document.getElementById('sidebar-nav');
            const content = document.querySelector('.prose');
            const headings = content.querySelectorAll('h2, h3');

            const icons = {
                overview: 'fa-info-circle', quickstart: 'fa-rocket', architecture: 'fa-project-diagram',
                api: 'fa-code', service: 'fa-cogs', integration: 'fa-plug', admin: 'fa-shield-alt',
                model: 'fa-database', database: 'fa-database', booking: 'fa-calendar-check',
                error: 'fa-exclamation-triangle', security: 'fa-lock', config: 'fa-cog',
                troubleshoot: 'fa-tools', auth: 'fa-key', cache: 'fa-bolt', n8n: 'fa-cogs',
            };

            const getIcon = (text) => {
                const lower = text.toLowerCase();
                for (const [key, icon] of Object.entries(icons)) {
                    if (lower.includes(key)) return icon;
                }
                return 'fa-circle';
            };

            headings.forEach((h, i) => {
                if (!h.id) {
                    h.id = 'section-' + i + '-' + h.textContent.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                }
                const isH2 = h.tagName === 'H2';
                const link = document.createElement('a');
                link.href = '#' + h.id;
                link.className = 'sidebar-link flex items-center px-3 py-2 text-sm rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 '
                               + (isH2 ? 'font-medium' : 'font-normal pl-6 text-gray-600 dark:text-gray-400');
                link.innerHTML = isH2
                    ? `<i class="fas ${getIcon(h.textContent)} w-4 mr-3 text-gray-400 dark:text-gray-500 flex-shrink-0"></i>${h.textContent}`
                    : `<span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600 mr-3 flex-shrink-0"></span>${h.textContent}`;
                nav.appendChild(link);
            });

            // Active link on scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const id = entry.target.id;
                    const link = nav.querySelector(`a[href="#${id}"]`);
                    if (!link) return;
                    if (entry.isIntersecting) {
                        nav.querySelectorAll('a').forEach(a => a.classList.remove('active', 'bg-gray-100', 'dark:bg-gray-700', 'text-primary-600', 'dark:text-primary-400'));
                        link.classList.add('active', 'bg-gray-100', 'text-primary-600');
                    }
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            headings.forEach(h => observer.observe(h));
        });
    </script>
</body>
</html>
