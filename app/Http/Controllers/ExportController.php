<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
    private function downloadTemplate($templateName)
    {
        $filePath = public_path("templates/{$templateName}.xlsx"); // Path to your Excel template

        if (file_exists($filePath)) {
            return Response::download($filePath); // Initiates the file download
        } else {
            return abort(404); // Returns a 404 error if the file doesn't exist
        }
    }

    public function downloadCompany()
    {
        return $this->downloadTemplate('company');
    }

    public function downloadAgent()
    {
        return $this->downloadTemplate('agents');
    }

    public function downloadTask()
    {
        return $this->downloadTemplate('tasks');
    }

    public function downloadClient()
    {
        return $this->downloadTemplate('clients');
    }
}
