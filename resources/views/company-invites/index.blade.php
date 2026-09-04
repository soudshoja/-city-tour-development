<x-app-layout>
    <div class="py-6 px-4">
        <h2 class="text-lg font-semibold mb-4">Company Invites</h2>

        @if (session('success'))
            <div class="mb-4 p-3" style="background:#d1fae5;border:1px solid #059669;">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('company-invites.store') }}" class="mb-6">
            @csrf
            <div class="flex gap-2">
                <input type="email" name="email" placeholder="Company email" title="Company email — where the registration link is sent" required class="border rounded p-2">
                <input type="number" step="0.001" name="monthly_fee" placeholder="Monthly fee (KWD)" title="Monthly fee in KWD — agreed subscription price (stored for billing)" required class="border rounded p-2">
                <input type="number" name="validity_days" value="14" min="1" max="90" title="Validity days — how many days the invite link stays valid before it expires (1-90)" required class="border rounded p-2" style="width:90px">
                <input type="text" name="note" placeholder="Note (optional)" title="Private note for your team — the company never sees it" class="border rounded p-2">
                <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2">Invite Company</button>
            </div>
            @error('email')<p style="color:#b91c1c">{{ $message }}</p>@enderror
        </form>

        <table class="min-w-full border">
            <thead><tr>
                <th class="border p-2">Email</th><th class="border p-2">Fee</th>
                <th class="border p-2">Status</th><th class="border p-2">Expires</th>
                <th class="border p-2">Company</th><th class="border p-2">Link</th><th class="border p-2">Actions</th>
            </tr></thead>
            <tbody>
            @foreach ($invites as $invite)
                <tr>
                    <td class="border p-2">{{ $invite->email }}</td>
                    <td class="border p-2">{{ $invite->monthly_fee }}</td>
                    <td class="border p-2">{{ $invite->isUsable() ? 'pending' : $invite->status }}</td>
                    <td class="border p-2">{{ $invite->expires_at->format('d M Y') }}</td>
                    <td class="border p-2">{{ $invite->company?->name ?? '—' }}</td>
                    <td class="border p-2">
                        @if ($invite->isUsable())
                            <input type="text" readonly value="{{ url('/register/company/' . $invite->token) }}"
                                   onclick="this.select()" class="border p-1" style="width:220px">
                        @endif
                    </td>
                    <td class="border p-2">
                        @if ($invite->isUsable())
                            <form method="POST" action="{{ route('company-invites.resend', $invite) }}" style="display:inline">@csrf<button>Resend</button></form>
                            <form method="POST" action="{{ route('company-invites.cancel', $invite) }}" style="display:inline">@csrf<button>Cancel</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $invites->links() }}
    </div>
</x-app-layout>
