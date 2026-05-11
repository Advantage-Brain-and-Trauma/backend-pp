<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $funnel->name }} — Already Assigned</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; color: #111827; min-height: 100vh; display: flex; flex-direction: column; }

/* Header */
.page-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 16px 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.page-header-logo { width: 36px; height: 36px; background: #6366f1; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 15px; flex-shrink: 0; }
.page-header-title { font-size: 15px; font-weight: 700; color: #111827; }
.page-header-sub { font-size: 12px; color: #6b7280; margin-top: 1px; }

/* Main */
.main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
.error-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
.error-icon { width: 72px; height: 72px; border-radius: 50%; background: #fef2f2; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
.error-title { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 12px; }
.error-message { font-size: 15px; color: #6b7280; line-height: 1.6; margin-bottom: 8px; }
.funnel-name { font-size: 15px; font-weight: 600; color: #6366f1; margin-bottom: 28px; }
.btn-dashboard { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #6366f1; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; transition: background 0.15s; }
.btn-dashboard:hover { background: #4f46e5; }
</style>
</head>
<body>

<!-- Header -->
<div class="page-header">
  <div class="page-header-logo">A</div>
  <div>
    <div class="page-header-title">AdvantageHCS</div>
    <div class="page-header-sub">Patient Portal</div>
  </div>
</div>

<!-- Error Content -->
<div class="main">
  <div class="error-card">
    <!-- Icon -->
    <div class="error-icon">
      <svg width="32" height="32" fill="none" stroke="#ef4444" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    </div>

    <!-- Title -->
    <h1 class="error-title">Already Assigned</h1>

    <!-- Message -->
    <p class="error-message">This funnel has already been assigned to your account.</p>
    <p class="funnel-name">{{ $funnel->name }}</p>

    <p class="error-message" style="margin-bottom:32px;">
      You cannot be assigned to the same funnel more than once. Please contact your healthcare provider if you believe this is a mistake.
    </p>

    <!-- Action -->
    <a href="{{ route('dashboard') }}" class="btn-dashboard">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
      </svg>
      Go to Dashboard
    </a>
  </div>
</div>

</body>
</html>
