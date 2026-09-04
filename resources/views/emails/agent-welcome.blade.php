<p>Hello {{ $agentName }},</p>
<p>An account has been created for you.</p>
<p>
    Login: <a href="{{ $loginUrl }}">{{ $loginUrl }}</a><br>
    Email: {{ $email }}<br>
    Temporary password: <strong>{{ $tempPassword }}</strong>
</p>
<p>Please change your password after your first login.</p>
