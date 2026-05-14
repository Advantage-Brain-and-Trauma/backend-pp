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
            --primary-light: #fef2f2;
            --sidebar-width: 260px;
            --header-height: 64px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f6fa;
            color: #1a1a2e;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 20px 20px 16px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 38px;
            height: 38px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .logo-text {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .logo-text span { color: var(--primary); }

        .logo-badge {
            font-size: 10px;
            background: #f3f4f6;
            color: #6b7280;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
            margin-left: auto;
        }

        .sidebar-nav {
            padding: 12px 12px;
            flex: 1;
        }

        .nav-section-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            padding: 8px 8px 4px;
            margin-top: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
            margin-bottom: 2px;
            position: relative;
        }

        .nav-item:hover {
            background: #f9fafb;
            color: #374151;
        }

        .nav-item.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .nav-item i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid #f3f4f6;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            background: #f9fafb;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .user-info { flex: 1; min-width: 0; }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 11px;
            color: #9ca3af;
        }

        .signout-btn {
            color: #9ca3af;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.15s;
        }

        .signout-btn:hover { color: var(--primary); }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* ===== HEADER ===== */
        .page-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 32px;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .page-header-left h1 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .page-header-left p {
            font-size: 13px;
            color: #6b7280;
        }

        .page-header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* ===== PAGE BODY ===== */
        .page-body {
            padding: 28px 32px;
        }

        /* ===== ALERTS (kept for any legacy use) ===== */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }

        /* ===== GLOBAL TOAST ===== */
        #global-toast-container {
            position: fixed;
            top: var(--header-height);
            right: 0;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 0;
            pointer-events: none;
            width: auto;
        }
        .g-toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            min-width: 320px;
            max-width: 480px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            pointer-events: all;
            animation: gToastIn .3s ease;
            border-radius: 0 0 0 8px;
        }
        .g-toast.success { background: #16a34a; }
        .g-toast.error   { background: #dc2626; }
        .g-toast.warning { background: #d97706; }
        @keyframes gToastIn  { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes gToastOut { from { transform: translateY(0); opacity: 1; } to { transform: translateY(-100%); opacity: 0; } }
        .g-toast.hide { animation: gToastOut .3s ease forwards; }

        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .card-body {
            padding: 20px;
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 1400px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-icon.red { background: #fef2f2; color: var(--primary); }
        .stat-icon.green { background: #f0fdf4; color: #16a34a; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.yellow { background: #fffbeb; color: #d97706; }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        /* ===== TABLES ===== */
        .table-container { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody td {
            padding: 14px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #fafafa; }

        /* ===== BADGES ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success { background: #f0fdf4; color: #16a34a; }
        .badge-warning { background: #fffbeb; color: #d97706; }
        .badge-danger { background: #fef2f2; color: #dc2626; }
        .badge-secondary { background: #f3f4f6; color: #6b7280; }
        .badge-info { background: #eff6ff; color: #3b82f6; }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            border: none;
            font-family: inherit;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover { background: var(--primary-dark); }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .btn-secondary:hover { background: #f9fafb; }

        .btn-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover { background: #fee2e2; }

        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }

        /* ===== FORMS ===== */
        .form-group { margin-bottom: 18px; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            color: #1a1a2e;
            font-family: inherit;
            background: white;
            transition: border-color 0.15s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(200, 16, 46, 0.08);
        }

        textarea.form-control { resize: vertical; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ===== SEARCH ===== */
        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 16px;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
        }

        .search-input-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
        }

        .search-input-wrap input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            color: #1a1a2e;
            background: white;
            transition: border-color 0.15s;
        }

        .search-input-wrap input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(200, 16, 46, 0.08);
        }

        /* ===== PAGINATION ===== */
        .pagination {
            padding: 16px 20px;
            border-top: 1px solid #f3f4f6;
        }

        .pagination nav { display: flex; justify-content: flex-end; }
    </style>
    @stack('styles')
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">A</div>
        <div class="logo-text">Advantage<span>HCS</span></div>
        <span class="logo-badge">Admin</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <div class="nav-section-label">User Management</div>
        <a href="{{ route('user-management.index') }}" class="nav-item {{ request()->routeIs('user-management.*') ? 'active' : '' }}">
            <i class="fas fa-users-cog"></i> Users
        </a>

        <a href="{{ route('messages.index') }}" class="nav-item {{ request()->routeIs('messages.*') ? 'active' : '' }}">
            <i class="fas fa-envelope"></i> Messages
            @php $unread = \App\Models\Message::where('is_read', false)->count(); @endphp
            @if($unread > 0)
                <span class="nav-badge">{{ $unread }}</span>
            @endif
        </a>

        <div class="nav-section-label">Forms & Funnels</div>
        <a href="{{ route('forms.index') }}" class="nav-item {{ request()->routeIs('forms.*') ? 'active' : '' }}">
            <i class="fas fa-wpforms"></i> Forms
        </a>
        <a href="{{ route('funnels.index') }}" class="nav-item {{ request()->routeIs('funnels.*') ? 'active' : '' }}">
            <i class="fas fa-filter"></i> Funnels
        </a>
        <a href="{{ route('old-forms.index') }}" class="nav-item {{ request()->routeIs('old-forms.*') ? 'active' : '' }}">
            <i class="fas fa-file-alt"></i> Old Forms
        </a>
        <div class="nav-section-title">ANALYTICS</div>
        <a href="{{ route('analytics.reports') }}" class="nav-item {{ request()->is('analytics/reports') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i> Reports Overview
        </a>
        <a href="{{ route('analytics.funnels') }}" class="nav-item {{ request()->is('analytics/funnels') ? 'active' : '' }}">
            <i class="fas fa-filter"></i> Funnel Analytics
        </a>
        <a href="{{ route('analytics.forms') }}" class="nav-item {{ request()->is('analytics/forms') ? 'active' : '' }}">
            <i class="fas fa-poll"></i> Form Analytics
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
            <div class="user-info">
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">Administrator</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="signout-btn" title="Sign out" style="background:none; border:none; cursor:pointer; padding:0;">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<!-- Main Content -->
<div class="main-content">

    <!-- Page Header -->
    <header class="page-header">
        <div class="page-header-left">
            <h1>@yield('page-title', 'Dashboard')</h1>
            <p>@yield('page-subtitle', '')</p>
        </div>
        <div class="page-header-actions">
            @yield('header-actions')
        </div>
    </header>

    <!-- Page Body -->
    <main class="page-body">
        @yield('content')
    </main>
</div>

{{-- Global toast container --}}
<div id="global-toast-container"></div>

{{-- Hidden flash data for JS toast --}}
@if(session('success'))
<span id="g-flash-success" data-msg="{{ session('success') }}" style="display:none;"></span>
@endif
@if(session('toast_success'))
<span id="g-flash-toast-success" data-msg="{{ session('toast_success') }}" style="display:none;"></span>
@endif
@if(session('error'))
<span id="g-flash-error" data-msg="{{ session('error') }}" style="display:none;"></span>
@endif
@if(session('toast_error'))
<span id="g-flash-toast-error" data-msg="{{ session('toast_error') }}" style="display:none;"></span>
@endif
@if($errors->any())
<span id="g-flash-validation" data-msg="{{ implode(' | ', $errors->all()) }}" style="display:none;"></span>
@endif

<script>
function showGlobalToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('global-toast-container');
    var toast = document.createElement('div');
    toast.className = 'g-toast ' + type;
    var icon = type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle');
    toast.innerHTML = '<i class="fas fa-' + icon + '"></i> ' + message;
    container.appendChild(toast);
    setTimeout(function() {
        toast.classList.add('hide');
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
    }, 4500);
}
document.addEventListener('DOMContentLoaded', function() {
    var s  = document.getElementById('g-flash-success');
    var ts = document.getElementById('g-flash-toast-success');
    var e  = document.getElementById('g-flash-error');
    var te = document.getElementById('g-flash-toast-error');
    var v  = document.getElementById('g-flash-validation');
    if (s)  showGlobalToast(s.getAttribute('data-msg'),  'success');
    if (ts) showGlobalToast(ts.getAttribute('data-msg'), 'success');
    if (e)  showGlobalToast(e.getAttribute('data-msg'),  'error');
    if (te) showGlobalToast(te.getAttribute('data-msg'), 'error');
    if (v)  showGlobalToast(v.getAttribute('data-msg'),  'error');
});
</script>

</body>
</html>
