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
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .logo {
            display: block;
            margin: 0 auto 20px auto;
            max-height: 80px;
        }

        .brand-name {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #1d4ed8;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h2 {
            font-size: 32px;
            text-align: center;
            background-color: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            color: #1d4ed8;
            letter-spacing: 2px;
            margin: 30px 0;
        }

        p {
            font-size: 15px;
            line-height: 1.6;
            margin: 15px 0;
        }

        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        {{-- 
        <img src="{!! url('images/City0logo.svg') !!}" alt="City Tour Logo" class="logo"> --}}

        <div class="brand-name">City Tour</div>

        <p>Hello,</p>

        <p>You requested to change your password. Use the verification code below to proceed:</p>

        <h2>{{ $code }}</h2>

        <p>This code will expire in 10 minutes.</p>

        <p>If you did not request a password change, please ignore this email.</p>

        <p>Regards,<br>City Tour App</p>

        <div class="footer">
            &copy; {{ date('Y') }} City Tour. All rights reserved.
        </div>
    </div>
</body>

</html>
