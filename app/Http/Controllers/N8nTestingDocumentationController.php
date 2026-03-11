<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class N8nTestingDocumentationController extends Controller
{
    /**
     * Display the N8n testing documentation page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('docs.n8n-testing-documentation');
    }
}
