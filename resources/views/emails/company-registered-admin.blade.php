@if ($succeeded)
<p>Company '{{ $companyName }}' registered via invite {{ $inviteEmail }}.</p>
@else
<p>Company registration FAILED for invite {{ $inviteEmail }} (company name submitted: '{{ $companyName }}').</p>
@if ($errorMessage)
<p>Error: {{ $errorMessage }}</p>
@endif
@endif
