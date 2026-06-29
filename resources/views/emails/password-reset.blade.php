<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Access Request</title>
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
            font-size: 20px;
            letter-spacing: 0.4px;
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
        .info-box {
            background-color: #f0f4ff;
            border-left: 4px solid #1a73e8;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #444444;
            margin-bottom: 20px;
        }
        .fallback-url {
            word-break: break-all;
            font-size: 12px;
            color: #888888;
            margin-top: 8px;
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
            <h1>MedHiWa Patient Portal</h1>
        </div>

        <div class="body">
            <p>Hello, <strong>{{ $patientName }}</strong>,</p>

            <p>We received a request to update the login credentials for your patient portal account.
               Please click the button below to proceed.</p>

            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn">Update My Credentials</a>
            </div>

            <div class="info-box">
                ℹ️ This link is valid for <strong>{{ $expiresInMinutes % 60 === 0 ? ($expiresInMinutes / 60) . ' hour' . ($expiresInMinutes / 60 === 1 ? '' : 's') : $expiresInMinutes . ' minutes' }}</strong>
            </div>

            <p class="fallback-url">
                If the button does not work, copy and paste this link into your browser:<br>
                {{ $resetUrl }}
            </p>

            <p>If you have any questions, please contact our support team.</p>

            <br>
            <p>Best Regards,<br><strong>MedHiWa Team</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} MedHiWa. All rights reserved.<br>
            This is an automated message — please do not reply to this email.
        </div>

    </div>
</body>
</html>
