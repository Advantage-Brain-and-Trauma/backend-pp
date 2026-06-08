<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Access Invitation</title>
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
        .body p { margin: 0 0 16px; }
        .badge {
            display: inline-block;
            background-color: #e8f0fe;
            color: #1a73e8;
            border-radius: 4px;
            padding: 2px 10px;
            font-size: 13px;
            font-weight: bold;
            text-transform: capitalize;
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
            <p>Hello,</p>

            <p>
                <strong>{{ $patientName }}</strong> has invited you as their
                <strong>{{ $relationship }}</strong> to access their health records on the MedHiWa Patient Portal.
            </p>

            <p>
                Access level granted: <span class="badge">{{ str_replace('_', ' ', $accessLevel) }}</span>
            </p>

            <p>Click the button below to accept the invitation and set up your access.</p>

            <div class="btn-wrap">
                <a href="{{ $acceptUrl }}" class="btn">Accept Invitation</a>
            </div>

            <div class="info-box">
                ⏰ This invitation link expires on <strong>{{ $expiresAt }}</strong>. Please accept before then.
            </div>

            <p class="fallback-url">
                If the button does not work, copy and paste this link into your browser:<br>
                {{ $acceptUrl }}
            </p>

            <p>If you did not expect this invitation or believe it was sent in error, you can safely ignore this email.</p>

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
