<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TermController extends Controller
{
    /**
     * Get all templates for the current company (JSON for Alpine.js)
     */
    public function index()
    {
        $companyId = $this->getCompanyId();

        $templates = Term::with('creator')
            ->where('company_id', $companyId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'title' => $template->title,
                    'content' => $template->content,
                    'language' => $template->language, // Make sure this is included
                    'is_default' => $template->is_default,
                    'is_active' => $template->is_active,
                    'created_by' => $template->created_by,
                    'created_by_name' => $template->creator?->name ?? 'System',
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Store a new template
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'language' => 'required|in:EN,ARB',
            'is_default' => 'nullable|boolean',
        ]);

        $companyId = $this->getCompanyId();

        $template = Term::create([
            'company_id' => $companyId,
            'title' => $request->title,
            'content' => $request->content,
            'language' => $request->language,
            'is_default' => $request->has('is_default'),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        // If set as default, unset others
        if ($template->is_default) {
            $template->setAsDefault();
        }

        // If this is the first template, make it default
        $templateCount = Term::where('company_id', $companyId)
            ->where('language', $request->language) // ADD THIS LINE
            ->count();
        if ($templateCount === 1) {
            $template->setAsDefault();
        }

        Log::info('Terms template created', ['template_id' => $template->id, 'company_id' => $companyId]);

        return redirect()->route('settings.index')->with('success', 'Template created successfully');
    }

    /**
     * Update a template
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'language' => 'required|in:EN,ARB',
        ]);

        $companyId = $this->getCompanyId();

        $template = Term::where('company_id', $companyId)
            ->findOrFail($id);

        $oldLanguage = $template->language;

        $template->update([
            'title' => $request->title,
            'content' => $request->content,
            'language' => $request->language, // ADD THIS LINE
        ]);

        // If language changed and was default, handle default status
        if ($oldLanguage !== $request->language && $template->is_default) {
            // Reset default status since language changed
            $template->update(['is_default' => false]);

            // Set as default for new language if no default exists
            $existingDefault = Term::where('company_id', $companyId)
                ->where('language', $request->language)
                ->where('is_default', true)
                ->exists();

            if (!$existingDefault) {
                $template->setAsDefault();
            }

            // Set new default for old language if needed
            $oldLangDefault = Term::where('company_id', $companyId)
                ->where('language', $oldLanguage)
                ->where('is_default', true)
                ->exists();

            if (!$oldLangDefault) {
                $newOldLangDefault = Term::where('company_id', $companyId)
                    ->where('language', $oldLanguage)
                    ->where('is_active', true)
                    ->first();

                if ($newOldLangDefault) {
                    $newOldLangDefault->setAsDefault();
                }
            }
        }

        Log::info('Terms template updated', ['template_id' => $template->id]);

        return redirect()->route('settings.index')->with('success', 'Template updated successfully');
    }

    /**
     * Delete a template
     */
    public function destroy($id)
    {
        $companyId = $this->getCompanyId();

        $template = Term::where('company_id', $companyId)
            ->findOrFail($id);

        $wasDefault = $template->is_default;
        $language = $template->language; // Store the language

        $template->delete();

        // If deleted template was default, set another one as default FOR SAME LANGUAGE
        if ($wasDefault) {
            $newDefault = Term::where('company_id', $companyId)
                ->where('language', $language) // ADD THIS LINE
                ->where('is_active', true)
                ->first();

            if ($newDefault) {
                $newDefault->setAsDefault();
            }
        }

        Log::info('Terms template deleted', ['template_id' => $id]);

        return redirect()->route('settings.index')->with('success', 'Template deleted successfully');
    }

    /**
     * Set a template as default
     */
    public function setDefault($id)
    {
        $companyId = $this->getCompanyId();

        $template = Term::where('company_id', $companyId)
            ->findOrFail($id);

        $template->setAsDefault();

        Log::info('Terms template set as default', ['template_id' => $id]);

        return redirect()->route('settings.index')->with('success', 'Template set as default');
    }

    /**
     * Toggle template active status
     */
    public function toggleActive($id)
    {
        $companyId = $this->getCompanyId();

        $template = Term::where('company_id', $companyId)
            ->findOrFail($id);

        $template->update(['is_active' => !$template->is_active]);

        $message = $template->is_active ? 'Template activated' : 'Template deactivated';

        return redirect()->route('settings.index')->with('success', $message);
    }

    /**
     * Get company ID based on user role
     */
    private function getCompanyId()
    {
        $user = Auth::user();

        if ($user->role->id == \App\Models\Role::ADMIN) {
            return request()->company_id ?? 1;
        } elseif ($user->role->id == \App\Models\Role::COMPANY) {
            return $user->company->id;
        } elseif ($user->role->id == \App\Models\Role::BRANCH) {
            return $user->branch->company_id;
        } elseif ($user->role->id == \App\Models\Role::ACCOUNTANT) {
            return $user->accountant->branch->company_id;
        }

        return null;
    }
}
