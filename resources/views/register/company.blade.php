<x-guest-layout>
{{-- The guest layout (resources/views/layouts/guest.blade.php) does not load Alpine.js —
     it only pulls in guest.css via @vite and the jQuery/Chart.js/etc bundle from
     layouts.links. This page collects a password + PII, so it must not depend on a
     third-party CDN being reachable (steps 2-7 are x-cloak'd and would stay hidden
     forever if the CDN were blocked) or unpinned (no SRI on a bare CDN <script> tag
     is a supply-chain risk). Alpine is self-hosted as a plain static asset instead:
     public/js/alpine.min.js, copied verbatim from node_modules/alpinejs/dist/cdn.min.js
     (the alpinejs@3.14.1 package.json dependency already declared in this repo). --}}
<script defer src="{{ asset('js/alpine.min.js') }}"></script>
<div class="p-6" x-data="{ step: 1, agents: [{}], gateways: [{name:'MyFatoorah', api_key:''}], gds_pccs: [{}] }" style="max-width:760px;margin:0 auto;">
    <h1 class="text-lg font-semibold mb-4">Register your company</h1>

    @if ($errors->any())
        <div class="mb-4 p-3" style="background:#fee2e2;border:1px solid #b91c1c;">
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- novalidate: fields on hidden (x-show-toggled) steps carry the `required`
         attribute, and Chrome/Firefox refuse to submit a form with a hidden
         required field — the whole submit silently no-ops with no visible error.
         Server-side validation (CompanyRegistrationRequest) is complete and
         tested, so client-side HTML5 validation is redundant here. --}}
    <form method="POST" action="{{ route('company-register.store', $invite->token) }}" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- Step 1: Company details --}}
        <div x-show="step === 1">
            <h2 class="font-semibold mb-2">Company details</h2>
            <input name="company_name" value="{{ old('company_name') }}" placeholder="Company name" required class="border rounded p-2 w-full mb-2">
            <input name="company_code" value="{{ old('company_code') }}" placeholder="Short code (e.g. NOVA)" required class="border rounded p-2 w-full mb-2">
            <select name="country_id" required class="border rounded p-2 w-full mb-2">
                <option value="">Country…</option>
                @foreach ($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <input name="company_email" type="email" value="{{ old('company_email', $invite->email) }}" placeholder="Company email" required class="border rounded p-2 w-full mb-2">
            <input name="phone" value="{{ old('phone') }}" placeholder="Phone" class="border rounded p-2 w-full mb-2">
            <input name="address" value="{{ old('address') }}" placeholder="Address" class="border rounded p-2 w-full mb-2">
            <label class="block mb-2">Logo (optional): <input type="file" name="logo" accept="image/*"></label>
        </div>

        {{-- Step 2: Owner account --}}
        <div x-show="step === 2" x-cloak>
            <h2 class="font-semibold mb-2">Administrator account</h2>
            <input name="owner_name" value="{{ old('owner_name') }}" placeholder="Full name" required class="border rounded p-2 w-full mb-2">
            {{-- Must match the email this invite was sent to (server-enforced
                 in CompanyRegistrationRequest::withValidator()) — prefilled
                 so that's the normal path, not a surprise validation error. --}}
            <input name="owner_email" type="email" value="{{ old('owner_email', $invite->email) }}" placeholder="Login email" required class="border rounded p-2 w-full mb-2">
            <p class="text-xs text-gray-500 mb-2" style="margin-top:-0.5rem;">Must match the email this invitation was sent to.</p>
            <input name="owner_password" type="password" placeholder="Password (min 8)" required class="border rounded p-2 w-full mb-2">
            <input name="owner_password_confirmation" type="password" placeholder="Confirm password" required class="border rounded p-2 w-full mb-2">
        </div>

        {{-- Step 3: IATA & currency --}}
        <div x-show="step === 3" x-cloak>
            <h2 class="font-semibold mb-2">IATA &amp; currency</h2>
            <p class="mb-2">Base currency: <strong>KWD</strong> (fixed)</p>
            <input name="iata_code" value="{{ old('iata_code') }}" placeholder="IATA number (optional)" class="border rounded p-2 w-full mb-2">
            <input name="gds_office_id" value="{{ old('gds_office_id') }}" placeholder="GDS office ID (optional)" class="border rounded p-2 w-full mb-2">
            <input name="iata_client_id" value="{{ old('iata_client_id') }}" placeholder="IATA API client id (optional)" class="border rounded p-2 w-full mb-2">
            {{-- No old('iata_client_secret') echo: a secret must never be re-flashed
                 into the page (the controller also strips it from withInput() on
                 the failure path) — the user simply retypes it. --}}
            <input name="iata_client_secret" value="" placeholder="IATA API client secret (optional)" class="border rounded p-2 w-full mb-2">

            <h3 class="font-semibold mt-4 mb-2">GDS systems &amp; PCCs (optional)</h3>
            <template x-for="(row, i) in gds_pccs" :key="i">
                <div class="flex gap-2 mb-2">
                    <select :name="`gds_pccs[${i}][gds]`" class="border rounded p-2">
                        <option value="">GDS…</option>
                        <option>Amadeus</option><option>Galileo</option><option>Sabre</option>
                    </select>
                    <input :name="`gds_pccs[${i}][pcc]`" placeholder="PCC / Office ID" class="border rounded p-2" style="flex:1">
                </div>
            </template>
            <button type="button" @click="gds_pccs.push({})" class="border rounded px-3 py-1">+ Add PCC</button>
        </div>

        {{-- Step 4: Agents --}}
        <div x-show="step === 4" x-cloak>
            <h2 class="font-semibold mb-2">Agents / employees (optional)</h2>
            <template x-for="(agent, i) in agents" :key="i">
                <div class="flex gap-2 mb-2">
                    <input :name="`agents[${i}][name]`" placeholder="Name" class="border rounded p-2" style="flex:1">
                    <input :name="`agents[${i}][email]`" type="email" placeholder="Email" class="border rounded p-2" style="flex:1">
                    <input :name="`agents[${i}][phone]`" placeholder="Phone" class="border rounded p-2" style="width:130px">
                    <input :name="`agents[${i}][amadeus_id]`" placeholder="Amadeus sign" class="border rounded p-2" style="width:120px">
                </div>
            </template>
            <button type="button" @click="agents.push({})" class="border rounded px-3 py-1">+ Add agent</button>

            <p class="mt-4 mb-2">
                <a href="{{ route('company-register.agents-template', $invite->token) }}" target="_blank">Download Excel template</a>
            </p>
            <label class="block mb-2">Or upload a filled template: <input type="file" name="agents_file" accept=".xlsx,.csv"></label>
        </div>

        {{-- Step 5: Suppliers --}}
        <div x-show="step === 5" x-cloak>
            <h2 class="font-semibold mb-2">Suppliers you work with</h2>
            <label class="block mb-2">
                <input type="checkbox" x-on:change="$root.querySelectorAll('input[name=\'supplier_ids[]\']').forEach(cb => cb.checked = $event.target.checked)">
                Select all
            </label>
            @foreach ($suppliers as $s)
                <label class="block"><input type="checkbox" name="supplier_ids[]" value="{{ $s->id }}"> {{ $s->name }}</label>
            @endforeach
        </div>

        {{-- Step 6: Payment gateways --}}
        <div x-show="step === 6" x-cloak>
            <h2 class="font-semibold mb-2">Payment gateways (optional)</h2>
            <template x-for="(gw, i) in gateways" :key="i">
                <div class="flex gap-2 mb-2">
                    <select :name="`gateways[${i}][name]`" class="border rounded p-2">
                        <option>MyFatoorah</option><option>Tap</option><option>Hesabe</option><option>uPayment</option>
                    </select>
                    <input :name="`gateways[${i}][api_key]`" placeholder="API key" class="border rounded p-2" style="flex:1">
                </div>
            </template>
            <button type="button" @click="gateways.push({})" class="border rounded px-3 py-1">+ Add gateway</button>
        </div>

        {{-- Step 7: Review & submit --}}
        <div x-show="step === 7" x-cloak>
            <h2 class="font-semibold mb-2">Review &amp; submit</h2>
            <p>Submitting creates your company and admin login. You can adjust everything later in Settings.</p>
            @if (!app()->environment('local'))
                {!! RecaptchaV3::field('company_register') !!}
            @endif
            <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 mt-3">Create my company</button>
        </div>

        <div class="flex justify-between mt-6">
            <button type="button" x-show="step > 1" @click="step--" class="border rounded px-4 py-2">Back</button>
            <button type="button" x-show="step < 7" @click="step++" class="border rounded px-4 py-2" style="margin-left:auto">Next</button>
        </div>
    </form>
</div>
</x-guest-layout>
