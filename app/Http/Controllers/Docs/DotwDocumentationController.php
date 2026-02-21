<?php

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;
use League\CommonMark\CommonMarkConverter;

class DotwDocumentationController extends Controller
{
    private CommonMarkConverter $converter;

    private array $docs = [
        'overview'   => ['file' => 'DOTW.md',                   'title' => 'DOTW v1.0 — Overview'],
        'api'        => ['file' => 'DOTW_API_REFERENCE.md',     'title' => 'GraphQL API Reference'],
        'services'   => ['file' => 'DOTW_SERVICES.md',          'title' => 'Services Documentation'],
        'integration'=> ['file' => 'DOTW_INTEGRATION_GUIDE.md', 'title' => 'Integration Guide (Admin UI + n8n)'],
        'architecture'=> ['file' => 'DOTW_ARCHITECTURE.md',     'title' => 'Architecture & Data Models'],
    ];

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function index(): View
    {
        return view('docs.dotw-hub', ['docs' => $this->docs]);
    }

    public function show(string $doc): View|Response
    {
        if (! isset($this->docs[$doc])) {
            abort(404, 'Documentation page not found.');
        }

        $meta     = $this->docs[$doc];
        $filePath = base_path('docs/' . $meta['file']);

        if (! file_exists($filePath)) {
            abort(404, 'Documentation file not found.');
        }

        $markdown = file_get_contents($filePath);
        $html     = $this->converter->convert($markdown)->getContent();

        return view('docs.dotw-page', [
            'title'   => $meta['title'],
            'content' => $html,
            'docs'    => $this->docs,
            'current' => $doc,
        ]);
    }
}
