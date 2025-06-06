<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Change Password Verification Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #333;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .logo {
            display: block;
            margin: 0 auto 30px auto;
            max-height: 80px;
        }

        h2 {
            font-size: 32px;
            text-align: center;
            background-color: #f0f4ff;
            padding: 15px;
            border-radius: 6px;
            color: #1d4ed8;
        }

        p {
            font-size: 14px;
            line-height: 1.6;
        }

        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <img src="{{ asset('images/City0logo.svg') }}" alt="City Tour Logo" class="logo">

        <p>Hello,</p>

        <p>You requested to change your password. Use the verification code below to proceed:</p>

        <h2>{{ $code }}</h2>

        <p>This code will expire in 10 minutes.</p>

        <p>If you did not request a password change, please ignore this email.</p>

        <p>Regards,<br>City Tour App</p>

        <div class="footer">
            &copy; {{ date('Y') }} City Tour App. All rights reserved.
        </div>
    </div>
</body>

</html>
