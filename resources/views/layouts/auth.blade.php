<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AdvantageHCS Admin')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #C8102E;
            --primary-dark: #a00d24;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f6fa 0%, #e8eaf0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 56px;
            height: 56px;
            background: var(--primary);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .logo-text span { color: var(--primary); }

        .logo-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-top: 4px;
        }

        .auth-card {
            background: white;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        .auth-card h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
        }

        .auth-card p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 28px;
        }

        .form-group { margin-bottom: 18px; }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px 11px 42px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            color: #1a1a2e;
            font-family: inherit;
            transition: border-color 0.15s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(200, 16, 46, 0.1);
        }

        .form-control.no-icon { padding-left: 14px; }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .remember-row label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #374151;
            cursor: pointer;
        }

        .btn-auth {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s;
        }

        .btn-auth:hover { background: var(--primary-dark); }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6b7280;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover { text-decoration: underline; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <div class="logo-icon">A</div>
            <div class="logo-text">Advantage<span>HCS</span></div>
            <div class="logo-subtitle">Admin Panel</div>
        </div>

        @yield('content')
    </div>
</body>
</html>
