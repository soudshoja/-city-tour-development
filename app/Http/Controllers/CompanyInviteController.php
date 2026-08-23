<?php

namespace App\Http\Controllers;

use App\Mail\CompanyInviteMail;
use App\Models\CompanyInvite;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CompanyInviteController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless((int) Auth::user()?->role_id === Role::ADMIN, 403);
    }

    public function index()
    {
        $this->authorizeAdmin();
        $invites = CompanyInvite::with('company')->orderByDesc('id')->paginate(25);

        return view('company-invites.index', compact('invites'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'email' => 'required|email',
            'monthly_fee' => 'required|numeric|min:0',
            'validity_days' => 'required|integer|min:1|max:90',
            'note' => 'nullable|string|max:255',
        ]);

        $invite = CompanyInvite::create([
            'token' => Str::random(64),
            'email' => $validated['email'],
            'monthly_fee' => $validated['monthly_fee'],
            'note' => $validated['note'] ?? null,
            'status' => CompanyInvite::STATUS_PENDING,
            'expires_at' => now()->addDays((int) $validated['validity_days']),
            'created_by' => Auth::id(),
        ]);

        Mail::to($invite->email)->send(new CompanyInviteMail($invite));

        return redirect()->route('company-invites.index')
            ->with('success', 'Invite created and emailed to ' . $invite->email);
    }

    public function cancel(CompanyInvite $invite)
    {
        $this->authorizeAdmin();
        if ($invite->status === CompanyInvite::STATUS_PENDING) {
            $invite->update(['status' => CompanyInvite::STATUS_CANCELLED]);
        }

        return redirect()->route('company-invites.index')->with('success', 'Invite cancelled.');
    }

    public function resend(CompanyInvite $invite)
    {
        $this->authorizeAdmin();
        abort_unless($invite->isUsable(), 422);
        Mail::to($invite->email)->send(new CompanyInviteMail($invite));

        return redirect()->route('company-invites.index')->with('success', 'Invite re-sent.');
    }
}
