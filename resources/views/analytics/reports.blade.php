@extends('layouts.app')

@section('title', 'Reports Overview')

@push('styles')
<style>
/* ── Reports Overview page styles ─────────────────────────────────────── */
.ro-page { display:flex; flex-direction:column; gap:28px; }

/* Header */
.ro-header {
    display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px;
}
.ro-header-title { font-size:26px; font-weight:800; color:#0f172a; margin:0; letter-spacing:-.4px; }
.ro-header-sub   { font-size:13px; color:#64748b; margin:4px 0 0; }
.ro-header-actions { display:flex; gap:10px; }

/* Date filter card */
.ro-filter-card {
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:20px 24px;
    box-shadow:0 1px 4px rgba(0,0,0,.04);
    margin-top:4px;
}
.ro-filter-inner {
    display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;
}
.ro-filter-field label {
    font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;
    letter-spacing:.5px; display:block; margin-bottom:6px;
}
.ro-filter-field input[type="date"] {
    height:40px; border:1.5px solid #e2e8f0; border-radius:10px;
    padding:0 12px; font-size:13px; color:#1e293b; background:#f8fafc;
    outline:none; transition:border .2s;
}
.ro-filter-field input[type="date"]:focus { border-color:#6366f1; background:#fff; }
.ro-filter-shortcuts { margin-left:auto; display:flex; gap:8px; align-items:center; }
.ro-shortcut-btn {
    font-size:12px; font-weight:600; padding:8px 14px; border-radius:8px;
    border:1.5px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer;
    transition:all .2s;
}
.ro-shortcut-btn:hover { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }

/* Stat cards */
.ro-stats-grid {
    display:grid; grid-template-columns:repeat(4,1fr); gap:20px;
}
.ro-stat-card {
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    padding:22px 24px; display:flex; align-items:center; gap:18px;
    box-shadow:0 1px 4px rgba(0,0,0,.04); transition:box-shadow .2s, transform .2s;
    position:relative; overflow:hidden;
}
.ro-stat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.08); transform:translateY(-2px); }
.ro-stat-card::before {
    content:''; position:absolute; top:0; left:0; width:4px; height:100%;
    border-radius:4px 0 0 4px;
}
.ro-stat-card.blue::before   { background:#3b82f6; }
.ro-stat-card.indigo::before { background:#6366f1; }
.ro-stat-card.green::before  { background:#22c55e; }
.ro-stat-card.amber::before  { background:#f59e0b; }
.ro-stat-icon {
    width:52px; height:52px; border-radius:14px; display:flex;
    align-items:center; justify-content:center; font-size:22px; flex-shrink:0;
}
.ro-stat-icon.blue   { background:#eff6ff; color:#3b82f6; }
.ro-stat-icon.indigo { background:#f5f3ff; color:#6366f1; }
.ro-stat-icon.green  { background:#f0fdf4; color:#22c55e; }
.ro-stat-icon.amber  { background:#fffbeb; color:#f59e0b; }
.ro-stat-value { font-size:28px; font-weight:800; color:#0f172a; line-height:1; }
.ro-stat-label { font-size:12px; color:#64748b; margin-top:4px; font-weight:500; }
.ro-stat-sub   { font-size:11px; margin-top:4px; }

/* Cards */
.ro-card {
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    box-shadow:0 1px 4px rgba(0,0,0,.04); overflow:hidden;
}
.ro-card-header { padding:20px 24px; border-bottom:1px solid #f1f5f9; }
.ro-card-title  { font-size:15px; font-weight:700; color:#0f172a; margin:0; }
.ro-card-sub    { font-size:12px; color:#94a3b8; margin:4px 0 0; }
.ro-card-body   { padding:24px; }
.ro-card-footer { padding:14px 24px; border-top:1px solid #f1f5f9; text-align:right; }

/* Trend chart bars */
.ro-trend-bars {
    display:flex; align-items:flex-end; gap:4px; height:130px; padding-bottom:4px;
}
.ro-trend-bar-wrap {
    flex:1; display:flex; flex-direction:column; align-items:center;
    justify-content:flex-end; height:100%;
}
.ro-trend-bar {
    width:100%; border-radius:4px 4px 0 0; transition:height .3s;
    min-height:0;
}
.ro-trend-bar.has-data { background:linear-gradient(180deg,#818cf8,#6366f1); }
.ro-trend-bar.no-data  { background:#e2e8f0; }
.ro-trend-labels {
    display:flex; justify-content:space-between; margin-top:8px;
}
.ro-trend-label { font-size:10px; color:#94a3b8; }

/* Period summary row */
.ro-period-row {
    display:flex; gap:0; margin-top:20px; padding-top:20px;
    border-top:1px solid #f1f5f9;
}
.ro-period-cell {
    flex:1; text-align:center; padding:0 8px;
    border-right:1px solid #f1f5f9;
}
.ro-period-cell:last-child { border-right:none; }
.ro-period-num   { font-size:22px; font-weight:800; color:#0f172a; }
.ro-period-label { font-size:11px; color:#94a3b8; margin-top:3px; }

/* Status bar */
.ro-status-bar {
    display:flex; height:20px; border-radius:10px; overflow:hidden; margin-bottom:20px;
}

/* Status rows */
.ro-status-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 0; border-bottom:1px solid #f8fafc;
}
.ro-status-row:last-child { border-bottom:none; }
.ro-status-dot {
    width:10px; height:10px; border-radius:3px; flex-shrink:0;
}
.ro-mini-bar-track {
    width:90px; height:6px; background:#f1f5f9; border-radius:3px; overflow:hidden;
}
.ro-mini-bar-fill { height:100%; border-radius:3px; }

/* Top items list */
.ro-top-item {
    display:flex; align-items:center; justify-content:space-between;
    padding:9px 0; border-bottom:1px solid #f8fafc;
}
.ro-top-item:last-child { border-bottom:none; }
.ro-top-icon {
    width:30px; height:30px; border-radius:8px; display:flex;
    align-items:center; justify-content:center; color:#fff; font-size:12px;
    flex-shrink:0;
}

/* Recent submissions */
.ro-sub-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:13px 24px; border-bottom:1px solid #f8fafc; transition:background .15s;
}
.ro-sub-row:last-child { border-bottom:none; }
.ro-sub-row:hover { background:#f9fafb; }
.ro-badge {
    font-size:11px; font-weight:600; padding:3px 10px;
    border-radius:20px; white-space:nowrap;
}

/* Quick link cards */
.ro-quick-card {
    display:flex; align-items:center; gap:18px; padding:22px 24px;
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    box-shadow:0 1px 4px rgba(0,0,0,.04); text-decoration:none;
    transition:box-shadow .2s, transform .2s;
}
.ro-quick-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.08); transform:translateY(-2px); }
.ro-quick-icon {
    width:52px; height:52px; border-radius:14px; display:flex;
    align-items:center; justify-content:center; color:#fff;
    font-size:20px; flex-shrink:0;
}
</style>
@endpush

@section('content')
<div class="ro-page">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="ro-header">
        <div>
            <h1 class="ro-header-title">Reports Overview</h1>
            <p class="ro-header-sub">High-level summary of all form and funnel activity</p>
        </div>
        <div class="ro-header-actions">
            <a href="{{ route('analytics.funnels') }}" class="btn btn-secondary">
                <i class="fas fa-filter" style="margin-right:6px;"></i>Funnel Analytics
            </a>
            <a href="{{ route('analytics.forms') }}" class="btn btn-secondary">
                <i class="fas fa-wpforms" style="margin-right:6px;"></i>Form Analytics
            </a>
        </div>
    </div>

    {{-- ── Date Filter ─────────────────────────────────────────────────── --}}
    <div class="ro-filter-card">
        <form method="GET" id="reports-filter-form">
            <div class="ro-filter-inner">
                <div class="ro-filter-field">
                    <label>From Date</label>
                    <input type="date" name="from" id="filter-from" value="{{ $from }}">
                </div>
                <div class="ro-filter-field">
                    <label>To Date</label>
                    <input type="date" name="to" id="filter-to" value="{{ $to }}">
                </div>
                <button type="submit" class="btn btn-primary" style="height:40px;border-radius:10px;">
                    <i class="fas fa-search" style="margin-right:6px;"></i>Apply Filter
                </button>
                <a href="{{ route('analytics.reports') }}" class="btn btn-secondary" style="height:40px;line-height:40px;padding:0 16px;border-radius:10px;">
                    Reset
                </a>
                <div class="ro-filter-shortcuts">
                    <button type="button" onclick="setRange(7)"  class="ro-shortcut-btn">Last 7 days</button>
                    <button type="button" onclick="setRange(30)" class="ro-shortcut-btn">Last 30 days</button>
                    <button type="button" onclick="setRange(90)" class="ro-shortcut-btn">Last 90 days</button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Stat Cards ──────────────────────────────────────────────────── --}}
    <div class="ro-stats-grid">

        <div class="ro-stat-card blue">
            <div class="ro-stat-icon blue"><i class="fas fa-wpforms"></i></div>
            <div>
                <div class="ro-stat-value">{{ number_format($stats['total_forms']) }}</div>
                <div class="ro-stat-label">Total Forms</div>
                <div class="ro-stat-sub" style="color:#94a3b8;">{{ $stats['active_forms'] }} active</div>
            </div>
        </div>

        <div class="ro-stat-card indigo">
            <div class="ro-stat-icon indigo"><i class="fas fa-filter"></i></div>
            <div>
                <div class="ro-stat-value">{{ number_format($stats['total_funnels']) }}</div>
                <div class="ro-stat-label">Total Funnels</div>
                <div class="ro-stat-sub" style="color:#94a3b8;">{{ $stats['active_funnels'] }} active</div>
            </div>
        </div>

        <div class="ro-stat-card green">
            <div class="ro-stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="ro-stat-value">{{ number_format($stats['total_submissions']) }}</div>
                <div class="ro-stat-label">Total Submissions</div>
                @php $ch = $stats['period_change']; @endphp
                <div class="ro-stat-sub" style="color:{{ $ch >= 0 ? '#22c55e' : '#ef4444' }};">
                    <i class="fas fa-arrow-{{ $ch >= 0 ? 'up' : 'down' }}" style="font-size:9px;"></i>
                    {{ $ch >= 0 ? '+' : '' }}{{ $ch }} this period
                </div>
            </div>
        </div>

        <div class="ro-stat-card amber">
            <div class="ro-stat-icon amber"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="ro-stat-value">{{ $stats['completion_rate'] }}%</div>
                <div class="ro-stat-label">Completion Rate</div>
                <div class="ro-stat-sub" style="color:#94a3b8;">Across all forms</div>
            </div>
        </div>

    </div>

    {{-- ── Trend + Status ──────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        {{-- Submissions Trend --}}
        <div class="ro-card">
            <div class="ro-card-header">
                <div class="ro-card-title">
                    <i class="fas fa-chart-line" style="color:#6366f1;margin-right:8px;"></i>
                    Submissions Trend
                </div>
                <div class="ro-card-sub">Daily submissions for selected period</div>
            </div>
            <div class="ro-card-body">
                @php
                    $trendDates  = array_keys($stats['daily_trend']);
                    $trendCounts = array_values($stats['daily_trend']);
                    $maxCount    = max(max($trendCounts), 1);
                    $tCount      = count($trendDates);
                @endphp

                @if(array_sum($trendCounts) === 0)
                    <div style="text-align:center;padding:40px 0;color:#94a3b8;">
                        <i class="fas fa-chart-line" style="font-size:36px;display:block;margin-bottom:12px;opacity:.4;"></i>
                        <div style="font-size:14px;font-weight:500;">No submissions in this period</div>
                        <div style="font-size:12px;margin-top:4px;">Try selecting a wider date range</div>
                    </div>
                @else
                    <div class="ro-trend-bars">
                        @foreach($trendCounts as $i => $cnt)
                        @php $barH = round(($cnt / $maxCount) * 100); @endphp
                        <div class="ro-trend-bar-wrap" title="{{ $trendDates[$i] }}: {{ $cnt }} submission{{ $cnt != 1 ? 's' : '' }}">
                            <div class="ro-trend-bar {{ $cnt > 0 ? 'has-data' : 'no-data' }}"
                                 style="height:{{ max($barH, $cnt > 0 ? 4 : 0) }}%;"></div>
                        </div>
                        @endforeach
                    </div>
                    <div class="ro-trend-labels">
                        <span class="ro-trend-label">{{ \Carbon\Carbon::parse($trendDates[0])->format('M d') }}</span>
                        @if($tCount > 2)
                        <span class="ro-trend-label">{{ \Carbon\Carbon::parse($trendDates[intval($tCount/2)])->format('M d') }}</span>
                        @endif
                        <span class="ro-trend-label">{{ \Carbon\Carbon::parse($trendDates[$tCount-1])->format('M d') }}</span>
                    </div>
                @endif

                <div class="ro-period-row">
                    <div class="ro-period-cell">
                        <div class="ro-period-num" style="color:#6366f1;">{{ $stats['period_submissions'] }}</div>
                        <div class="ro-period-label">This Period</div>
                    </div>
                    <div class="ro-period-cell">
                        @php $ch = $stats['period_change']; @endphp
                        <div class="ro-period-num" style="color:{{ $ch >= 0 ? '#22c55e' : '#ef4444' }};">
                            {{ $ch >= 0 ? '+' : '' }}{{ $ch }}
                        </div>
                        <div class="ro-period-label">vs Prev Period</div>
                    </div>
                    <div class="ro-period-cell">
                        <div class="ro-period-num" style="color:#f59e0b;">{{ $stats['completion_rate'] }}%</div>
                        <div class="ro-period-label">Completion</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Submission Status --}}
        <div class="ro-card">
            <div class="ro-card-header">
                <div class="ro-card-title">
                    <i class="fas fa-tasks" style="color:#22c55e;margin-right:8px;"></i>
                    Form Submission Status
                </div>
                <div class="ro-card-sub">Breakdown by submission type for selected period</div>
            </div>
            <div class="ro-card-body">
                @php
                    $sTotal     = max($stats['submissions_by_status']['total'], 1);
                    $sCompleted = $stats['submissions_by_status']['completed'];
                    $sDrafts    = $stats['submissions_by_status']['drafts'];
                @endphp

                <div class="ro-status-bar" style="margin-bottom:24px;">
                    @if($sCompleted > 0)<div style="flex:{{ $sCompleted }};background:linear-gradient(90deg,#22c55e,#16a34a);"></div>@endif
                    @if($sDrafts > 0)<div style="flex:{{ $sDrafts }};background:linear-gradient(90deg,#f59e0b,#d97706);"></div>@endif
                    @if($sCompleted === 0 && $sDrafts === 0)
                        <div style="flex:1;background:#e2e8f0;"></div>
                    @endif
                </div>

                @foreach([
                    ['label' => 'Completed Submissions', 'value' => $sCompleted, 'color' => '#22c55e', 'bg' => '#f0fdf4'],
                    ['label' => 'Saved as Draft',        'value' => $sDrafts,    'color' => '#f59e0b', 'bg' => '#fffbeb'],
                ] as $row)
                <div class="ro-status-row">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="ro-status-dot" style="background:{{ $row['color'] }};"></div>
                        <span style="font-size:13px;color:#374151;font-weight:500;">{{ $row['label'] }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="ro-mini-bar-track">
                            <div class="ro-mini-bar-fill" style="width:{{ $sTotal > 0 ? round(($row['value']/$sTotal)*100) : 0 }}%;background:{{ $row['color'] }};"></div>
                        </div>
                        <span style="font-size:14px;font-weight:700;color:#0f172a;min-width:24px;text-align:right;">{{ $row['value'] }}</span>
                        <span style="font-size:12px;color:#94a3b8;min-width:36px;text-align:right;">{{ $sTotal > 0 ? round(($row['value']/$sTotal)*100) : 0 }}%</span>
                    </div>
                </div>
                @endforeach

                {{-- Top Forms --}}
                <div style="margin-top:24px;padding-top:20px;border-top:1px solid #f1f5f9;">
                    <div style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;">
                        Top Forms by Submissions
                    </div>
                    @forelse($stats['top_forms'] as $tf)
                    <div class="ro-top-item">
                        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                            <div class="ro-top-icon" style="background:linear-gradient(135deg,#3b82f6,#06b6d4);">
                                <i class="fas fa-wpforms"></i>
                            </div>
                            <span style="font-size:13px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">{{ $tf->name }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px;">
                            @php $maxF = max($stats['top_forms']->first()->submissions_count ?? 1, 1); @endphp
                            <div class="ro-mini-bar-track" style="width:80px;">
                                <div class="ro-mini-bar-fill" style="width:{{ round(($tf->submissions_count/$maxF)*100) }}%;background:#3b82f6;"></div>
                            </div>
                            <span style="font-size:13px;font-weight:700;color:#0f172a;min-width:20px;text-align:right;">{{ $tf->submissions_count }}</span>
                        </div>
                    </div>
                    @empty
                    <div style="font-size:13px;color:#94a3b8;text-align:center;padding:16px 0;">No submissions yet</div>
                    @endforelse
                </div>
            </div>
            <div class="ro-card-footer">
                <a href="{{ route('analytics.forms') }}" style="font-size:13px;color:#6366f1;font-weight:600;text-decoration:none;">
                    View Form Analytics <i class="fas fa-arrow-right" style="font-size:11px;margin-left:4px;"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- ── Top Funnels + Recent Submissions ───────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        {{-- Top Funnels --}}
        <div class="ro-card">
            <div class="ro-card-header">
                <div class="ro-card-title">
                    <i class="fas fa-filter" style="color:#6366f1;margin-right:8px;"></i>
                    Top Funnels by Submissions
                </div>
                <div class="ro-card-sub">Most active funnels by submission count</div>
            </div>
            <div class="ro-card-body">
                @forelse($stats['top_funnels'] as $tf)
                <div class="ro-top-item">
                    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                        <div class="ro-top-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                            <i class="fas fa-filter"></i>
                        </div>
                        <span style="font-size:13px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tf->name }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px;">
                        @php $maxFn = max($stats['top_funnels']->first()->submissions_count ?? 1, 1); @endphp
                        <div class="ro-mini-bar-track" style="width:80px;">
                            <div class="ro-mini-bar-fill" style="width:{{ round(($tf->submissions_count/$maxFn)*100) }}%;background:#6366f1;"></div>
                        </div>
                        <span style="font-size:13px;font-weight:700;color:#0f172a;min-width:20px;text-align:right;">{{ $tf->submissions_count }}</span>
                    </div>
                </div>
                @empty
                <div style="text-align:center;padding:40px 0;color:#94a3b8;">
                    <i class="fas fa-filter" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3;"></i>
                    <div style="font-size:14px;font-weight:500;">No funnel submissions yet</div>
                </div>
                @endforelse
            </div>
            <div class="ro-card-footer">
                <a href="{{ route('analytics.funnels') }}" style="font-size:13px;color:#6366f1;font-weight:600;text-decoration:none;">
                    View Funnel Analytics <i class="fas fa-arrow-right" style="font-size:11px;margin-left:4px;"></i>
                </a>
            </div>
        </div>

        {{-- Recent Submissions --}}
        <div class="ro-card">
            <div class="ro-card-header">
                <div class="ro-card-title">
                    <i class="fas fa-clock" style="color:#f59e0b;margin-right:8px;"></i>
                    Recent Submissions
                </div>
                <div class="ro-card-sub">Latest form submissions in selected period</div>
            </div>
            <div>
                @forelse($stats['recent_submissions'] as $sub)
                <div class="ro-sub-row">
                    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                        <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;font-size:13px;color:#4f46e5;">
                            {{ strtoupper(substr($sub->patient_name ?: 'S', 0, 1)) }}
                        </div>
                        <div style="min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $sub->patient_name ?: ($sub->form->name ?? 'Unknown') }}
                            </div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                                {{ $sub->form->name ?? '—' }} &middot; {{ $sub->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    <div style="flex-shrink:0;margin-left:12px;">
                        @php
                            $sc = match($sub->status) {
                                'completed' => ['bg'=>'#f0fdf4','text'=>'#16a34a'],
                                'draft'     => ['bg'=>'#fffbeb','text'=>'#d97706'],
                                default     => ['bg'=>'#eff6ff','text'=>'#2563eb'],
                            };
                        @endphp
                        <span class="ro-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                            {{ ucfirst($sub->status) }}
                        </span>
                    </div>
                </div>
                @empty
                <div style="padding:48px;text-align:center;color:#94a3b8;">
                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:12px;opacity:.4;"></i>
                    <div style="font-size:14px;font-weight:500;">No submissions in this period</div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Quick Links ─────────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
        <a href="{{ route('analytics.funnels') }}" class="ro-quick-card">
            <div class="ro-quick-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                <i class="fas fa-filter"></i>
            </div>
            <div>
                <div style="font-size:15px;font-weight:700;color:#0f172a;">Funnel Analytics</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:3px;">Per-funnel completion breakdown and step analysis</div>
            </div>
            <i class="fas fa-chevron-right" style="color:#cbd5e1;margin-left:auto;font-size:13px;"></i>
        </a>
        <a href="{{ route('analytics.forms') }}" class="ro-quick-card">
            <div class="ro-quick-icon" style="background:linear-gradient(135deg,#3b82f6,#06b6d4);">
                <i class="fas fa-wpforms"></i>
            </div>
            <div>
                <div style="font-size:15px;font-weight:700;color:#0f172a;">Form Analytics</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:3px;">Per-form submission stats and field-level insights</div>
            </div>
            <i class="fas fa-chevron-right" style="color:#cbd5e1;margin-left:auto;font-size:13px;"></i>
        </a>
    </div>

</div>{{-- /ro-page --}}

<script>
function setRange(days) {
    var to   = new Date();
    var from = new Date();
    from.setDate(from.getDate() - days);
    document.getElementById('filter-from').value = from.toISOString().split('T')[0];
    document.getElementById('filter-to').value   = to.toISOString().split('T')[0];
    document.getElementById('reports-filter-form').submit();
}
</script>
@endsection
