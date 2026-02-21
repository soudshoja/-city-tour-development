<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — DOTW Docs</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
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

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background-color: rgba(156,163,175,0.5); border-radius: 4px; }
        * { scrollbar-width: thin; scrollbar-color: rgba(156,163,175,0.5) transparent; }

        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { transform: translateX(4px); }

        /* ── Markdown content styling ── */
        .md-content h1 {
            font-size: 2rem; font-weight: 800; margin-top: 0; margin-bottom: 1rem;
            color: #111827; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem;
        }
        .dark .md-content h1 { color: #f9fafb; border-color: #374151; }

        .md-content h2 {
            font-size: 1.5rem; font-weight: 700; margin-top: 2.5rem; margin-bottom: 0.75rem;
            color: #111827; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.4rem;
            scroll-margin-top: 5rem;
        }
        .dark .md-content h2 { color: #f9fafb; border-color: #374151; }

        .md-content h3 {
            font-size: 1.2rem; font-weight: 600; margin-top: 2rem; margin-bottom: 0.5rem;
            color: #1d4ed8; scroll-margin-top: 5rem;
        }
        .dark .md-content h3 { color: #60a5fa; }

        .md-content h4 {
            font-size: 1rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.4rem;
            color: #374151;
        }
        .dark .md-content h4 { color: #d1d5db; }

        .md-content p {
            margin-bottom: 1rem; line-height: 1.75;
            color: #374151;
        }
        .dark .md-content p { color: #d1d5db; }

        .md-content ul, .md-content ol {
            margin-bottom: 1rem; padding-left: 1.5rem; color: #374151;
        }
        .dark .md-content ul, .dark .md-content ol { color: #d1d5db; }
        .md-content ul { list-style-type: disc; }
        .md-content ol { list-style-type: decimal; }
        .md-content li { margin-bottom: 0.25rem; line-height: 1.7; }
        .md-content li > ul, .md-content li > ol { margin-top: 0.25rem; }

        .md-content a { color: #2563eb; text-decoration: underline; }
        .dark .md-content a { color: #60a5fa; }
        .md-content a:hover { color: #1d4ed8; }
        .dark .md-content a:hover { color: #93c5fd; }

        .md-content strong { font-weight: 700; color: #111827; }
        .dark .md-content strong { color: #f9fafb; }

        .md-content em { font-style: italic; }

        .md-content blockquote {
            border-left: 4px solid #3b82f6;
            padding: 0.75rem 1rem;
            margin: 1.25rem 0;
            background: #eff6ff;
            border-radius: 0 0.375rem 0.375rem 0;
            color: #1e40af;
        }
        .dark .md-content blockquote {
            background: rgba(59,130,246,0.1);
            color: #93c5fd;
        }

        .md-content hr {
            border: none; border-top: 1px solid #e5e7eb; margin: 2rem 0;
        }
        .dark .md-content hr { border-color: #374151; }

        /* Code — inline */
        .md-content :not(pre) > code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82em;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 2px 6px;
            color: #0f766e;
        }
        .dark .md-content :not(pre) > code {
            background: #1e293b;
            border-color: #334155;
            color: #34d399;
        }

        /* Code — block */
        .md-content pre {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            overflow-x: auto;
            margin: 0.5rem 0 1.5rem;
            position: relative;
        }
        .dark .md-content pre {
            background: #0f172a;
            border-color: #1e293b;
        }
        .md-content pre code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.84rem;
            line-height: 1.6;
            color: #e2e8f0;
            background: none;
            border: none;
            padding: 0;
        }

        /* Tables */
        .md-content table {
            width: 100%; border-collapse: collapse;
            margin: 0.75rem 0 1.5rem; font-size: 0.9rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem; overflow: hidden;
        }
        .dark .md-content table { border-color: #374151; }
        .md-content thead th {
            background: #f8fafc; border-bottom: 2px solid #e5e7eb;
            padding: 10px 14px; text-align: left;
            font-weight: 600; color: #111827; font-size: 0.85rem;
        }
        .dark .md-content thead th {
            background: #1e293b; border-color: #374151; color: #f9fafb;
        }
        .md-content tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 9px 14px; color: #374151; vertical-align: top;
        }
        .dark .md-content tbody td { border-color: #374151; color: #d1d5db; }
        .md-content tbody tr:last-child td { border-bottom: none; }
        .md-content tbody tr:nth-child(even) td { background: #f8fafc; }
        .dark .md-content tbody tr:nth-child(even) td { background: #0f172a; }
        .md-content tbody tr:hover td { background: #eff6ff; }
        .dark .md-content tbody tr:hover td { background: rgba(59,130,246,0.05); }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 min-h-screen">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="{{ url('/docs/dotw') }}" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-600">
                        <i class="fas fa-hotel text-white text-sm"></i>
                    </div>
                    <span class="text-base font-bold text-gray-900 dark:text-white">DOTW Docs</span>
                </a>
                <span class="text-gray-300 dark:text-gray-600 hidden sm:block">/</span>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate max-w-xs hidden sm:block">{{ $title }}</span>
            </div>
            <div class="flex items-center space-x-3">
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
                       class="hidden lg:inline text-sm font-medium transition-colors
                              {{ $current === $slug
                                  ? 'text-primary-600 dark:text-primary-400'
                                  : 'text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
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
                    <!-- Populated by JS from headings -->
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">

                <!-- Hero banner -->
                <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-10 text-white">
                    <h1 class="text-3xl font-extrabold mb-2">{{ $title }}</h1>
                    <p class="text-base opacity-90">DOTW v1.0 · B2B Hotel Booking API Documentation</p>
                </div>

                <!-- Markdown content -->
                <div class="md-content">
                    {!! $content !!}
                </div>

                <!-- Prev / Next -->
                <div class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
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
                                <i class="fas fa-arrow-left text-xs"></i> {{ $docs[$prev]['title'] }}
                            </a>
                        @endif
                    </div>
                    <a href="{{ url('/docs/dotw') }}" class="text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-th-large mr-1 text-xs"></i> All Docs
                    </a>
                    <div>
                        @if($next)
                            <a href="{{ url('/docs/dotw/' . $next) }}"
                               class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500">
                                {{ $docs[$next]['title'] }} <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Dark mode
        if (localStorage.getItem('darkMode') === 'false') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
        document.getElementById('darkModeToggle').addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        });

        // Build sidebar dynamically from h2/h3 headings
        document.addEventListener('DOMContentLoaded', () => {
            const nav      = document.getElementById('sidebar-nav');
            const content  = document.querySelector('.md-content');
            const headings = content.querySelectorAll('h2, h3');

            const iconMap = {
                overview: 'fa-info-circle', quick: 'fa-rocket', start: 'fa-rocket',
                architecture: 'fa-project-diagram', api: 'fa-code', service: 'fa-cogs',
                integration: 'fa-plug', admin: 'fa-shield-alt', model: 'fa-database',
                database: 'fa-database', booking: 'fa-calendar-check', flow: 'fa-stream',
                error: 'fa-exclamation-triangle', security: 'fa-lock', config: 'fa-cog',
                troubleshoot: 'fa-tools', auth: 'fa-key', cache: 'fa-bolt',
                n8n: 'fa-cogs', credential: 'fa-key', token: 'fa-ticket-alt',
            };
            const getIcon = (text) => {
                const lower = text.toLowerCase();
                for (const [k, v] of Object.entries(iconMap)) if (lower.includes(k)) return v;
                return 'fa-circle';
            };

            headings.forEach((h, i) => {
                if (!h.id) {
                    h.id = 'h-' + i + '-' + h.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 40);
                }
                const isH2 = h.tagName === 'H2';
                const a    = document.createElement('a');
                a.href = '#' + h.id;
                a.dataset.id = h.id;
                a.className = 'sidebar-link flex items-center px-3 py-1.5 text-sm rounded-md transition-colors '
                    + (isH2
                        ? 'font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700'
                        : 'font-normal pl-7 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700');
                a.innerHTML = isH2
                    ? `<i class="fas ${getIcon(h.textContent)} w-4 mr-2.5 text-gray-400 dark:text-gray-500 flex-shrink-0 text-xs"></i><span class="truncate">${h.textContent.trim()}</span>`
                    : `<span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600 mr-2.5 flex-shrink-0"></span><span class="truncate">${h.textContent.trim()}</span>`;
                nav.appendChild(a);
            });

            // Highlight active section on scroll
            const allLinks = () => nav.querySelectorAll('a[data-id]');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    allLinks().forEach(a => {
                        const active = a.dataset.id === entry.target.id;
                        a.classList.toggle('bg-primary-50',   active);
                        a.classList.toggle('dark:bg-primary-900/20', active);
                        a.classList.toggle('text-primary-600', active);
                        a.classList.toggle('dark:text-primary-400', active);
                        a.classList.toggle('font-semibold',   active && a.querySelector('i') !== null);
                    });
                });
            }, { rootMargin: '-10% 0px -75% 0px' });

            headings.forEach(h => observer.observe(h));
        });
    </script>
</body>
</html>
