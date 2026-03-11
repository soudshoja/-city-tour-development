<?php

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class N8nDocumentationController extends Controller
{
    /**
     * Display the N8n document processing documentation.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        return view('docs.n8n-processing');
    }

    /**
     * Display the complete N8n documentation (all phases).
     *
     * @return \Illuminate\View\View
     */
    public function complete(): View
    {
        return view('docs.n8n-complete-documentation');
    }

    /**
     * Display the N8n integration changelog.
     *
     * @return \Illuminate\View\View
     */
    public function changelog(): View
    {
        return view('docs.n8n-changelog');
    }

    /**
     * Display the N8n documentation hub (main index).
     *
     * @return \Illuminate\View\View
     */
    public function hub(): View
    {
        return view('docs.n8n-hub');
    }

    /**
     * Display the N8n user guide (admin + developer).
     *
     * @return \Illuminate\View\View
     */
    public function userGuide(): View
    {
        return view('docs.n8n-user-guide');
    }
}
