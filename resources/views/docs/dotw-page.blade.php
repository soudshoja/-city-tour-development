<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — DOTW Docs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
        body { font-family: 'Inter', sans-serif; }

        /* Markdown content styles */
        .md-content h1 { @apply text-3xl font-bold text-white mt-8 mb-4 border-b border-gray-800 pb-3; }
        .md-content h2 { @apply text-2xl font-semibold text-white mt-8 mb-3 border-b border-gray-800 pb-2; }
        .md-content h3 { @apply text-xl font-semibold text-blue-300 mt-6 mb-2; }
        .md-content h4 { @apply text-lg font-semibold text-gray-200 mt-4 mb-2; }
        .md-content p  { @apply text-gray-300 leading-relaxed mb-4; }
        .md-content ul { @apply list-disc list-inside text-gray-300 mb-4 space-y-1 pl-2; }
        .md-content ol { @apply list-decimal list-inside text-gray-300 mb-4 space-y-1 pl-2; }
        .md-content li { @apply leading-relaxed; }
        .md-content li > ul { @apply mt-1 ml-4; }
        .md-content a  { @apply text-blue-400 hover:text-blue-300 underline; }
        .md-content strong { @apply text-white font-semibold; }
        .md-content em { @apply text-gray-200 italic; }
        .md-content blockquote { @apply border-l-4 border-blue-600 pl-4 py-1 my-4 bg-blue-950/30 text-gray-300 rounded-r; }
        .md-content hr { @apply border-gray-800 my-8; }

        .md-content pre {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            overflow-x: auto;
            margin: 1rem 0 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            color: #e6edf3;
        }
        .md-content code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85em;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 4px;
            padding: 2px 6px;
            color: #f0883e;
        }
        .md-content pre code {
            background: none;
            border: none;
            padding: 0;
            color: #e6edf3;
            font-size: inherit;
        }
        .md-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0 1.5rem;
            font-size: 0.9rem;
        }
        .md-content table th {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 10px 14px;
            text-align: left;
            color: #f0f6fc;
            font-weight: 600;
        }
        .md-content table td {
            border: 1px solid #30363d;
            padding: 8px 14px;
            color: #c9d1d9;
            vertical-align: top;
        }
        .md-content table tr:nth-child(even) td { background: #0d1117; }
        .md-content table tr:hover td { background: #161b22; }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen">

    <!-- Top nav -->
    <nav class="sticky top-0 z-50 bg-gray-900/95 backdrop-blur border-b border-gray-800">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-4">
            <a href="{{ url('/docs/dotw') }}" class="text-gray-400 hover:text-white transition-colors text-sm flex items-center gap-2">
                <i class="fas fa-arrow-left text-xs"></i> DOTW Docs
            </a>
            <span class="text-gray-700">/</span>
            <span class="text-gray-200 text-sm font-medium truncate">{{ $title }}</span>
            <div class="ml-auto hidden md:flex items-center gap-1">
                @foreach($docs as $slug => $meta)
                    <a href="{{ url('/docs/dotw/' . $slug) }}"
                       class="px-3 py-1 rounded text-xs font-medium transition-colors
                              {{ $current === $slug ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
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
    </nav>

    <!-- Content -->
    <main class="max-w-4xl mx-auto px-6 py-10">
        <div class="md-content">
            {!! $content !!}
        </div>
        <div class="mt-16 pt-8 border-t border-gray-800 text-center text-gray-600 text-sm">
            DOTW v1.0 · Soud Laravel Platform · <a href="{{ url('/docs/dotw') }}" class="hover:text-gray-400">Back to docs hub</a>
        </div>
    </main>

</body>
</html>
