<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 15px;
            color: #333333;
            line-height: 1.7;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background-color: #1a73e8;
            padding: 28px 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 22px;
            letter-spacing: 0.5px;
        }
        .body {
            padding: 36px 40px;
        }
        .body p {
            margin: 0 0 16px;
        }
        .btn-wrap {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #1a73e8;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
        .fallback-url {
            word-break: break-all;
            font-size: 13px;
            color: #666666;
            margin-top: 8px;
        }
        .notice {
            background-color: #fff8e1;
            border-left: 4px solid #f9a825;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #555555;
            margin-bottom: 20px;
        }
        .footer {
            background-color: #f4f4f4;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #999999;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MedHiWa – Password Reset</h1>
        </div>
        <div class="body">
            <p>Hello, <strong>{{ $patientName }}</strong>,</p>

            <p>We received a request to reset the password for your MedHiWa patient portal account.
               Click the button below to create a new password.</p>

            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn">Reset My Password</a>
            </div>

            <p class="fallback-url">
                If the button above does not work, copy and paste this link into your browser:<br>
                {{ $resetUrl }}
            </p>

            <div class="notice">
                ⚠️ This link will expire in <strong>{{ $expiresInMinutes }} minutes</strong> and can only be used <strong>once</strong>.
                If you did not request a password reset, you can safely ignore this email — your account is not at risk.
            </div>

            <p>For your security, please do not share this link with anyone.</p>

            <p>If you have any questions, please contact our support team.</p>

            <br>
            <p>Best Regards,<br><strong>MedHiWa Team</strong></p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} MedHiWa. All rights reserved.<br>
            This is an automated message. Please do not reply to this email.
        </div>
    </div>
</body>
</html>
