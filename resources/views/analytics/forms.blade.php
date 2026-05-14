@extends('layouts.app')

@section('title', 'Form Analytics')

@push('styles')
<style>
/* ── Form Analytics page ─────────────────────────────────── */
.fa-page { display:flex; flex-direction:column; gap:28px; }

/* Header */
.fa-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.fa-header-title { font-size:26px; font-weight:800; color:#0f172a; margin:0; letter-spacing:-.4px; }
.fa-header-sub   { font-size:13px; color:#64748b; margin:4px 0 0; }

/* Filter card */
.fa-filter-card {
    background:#fff; border:1px solid #e2e8f0; border-radius:16px;
    padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,.04);
}
.fa-filter-inner { display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap; }
.fa-filter-field label {
    font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;
    letter-spacing:.5px; display:block; margin-bottom:6px;
}
.fa-filter-field input[type="date"] {
    height:40px; border:1.5px solid #e2e8f0; border-radius:10px;
    padding:0 12px; font-size:13px; color:#1e293b; background:#f8fafc; outline:none;
    transition:border .2s;
}
.fa-filter-field input[type="date"]:focus { border-color:#3b82f6; background:#fff; }
.fa-shortcuts { margin-left:auto; display:flex; gap:8px; align-items:center; }
.fa-shortcut-btn {
    font-size:12px; font-weight:600; padding:8px 14px; border-radius:8px;
    border:1.5px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; transition:all .2s;
}
.fa-shortcut-btn:hover { border-color:#3b82f6; color:#3b82f6; background:#eff6ff; }

/* Summary stats */
.fa-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:18px; }
.fa-stat-card {
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    padding:20px 22px; display:flex; align-items:center; gap:16px;
    box-shadow:0 1px 4px rgba(0,0,0,.04); transition:box-shadow .2s, transform .2s;
    position:relative; overflow:hidden;
}
.fa-stat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.08); transform:translateY(-2px); }
.fa-stat-card::before {
    content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius:4px 0 0 4px;
}
.fa-stat-card.blue::before   { background:#3b82f6; }
.fa-stat-card.green::before  { background:#22c55e; }
.fa-stat-card.amber::before  { background:#f59e0b; }
.fa-stat-card.violet::before { background:#7c3aed; }
.fa-stat-card.rose::before   { background:#f43f5e; }
.fa-stat-icon {
    width:48px; height:48px; border-radius:12px; display:flex;
    align-items:center; justify-content:center; font-size:20px; flex-shrink:0;
}
.fa-stat-icon.blue   { background:#eff6ff; color:#3b82f6; }
.fa-stat-icon.green  { background:#f0fdf4; color:#22c55e; }
.fa-stat-icon.amber  { background:#fffbeb; color:#f59e0b; }
.fa-stat-icon.violet { background:#f5f3ff; color:#7c3aed; }
.fa-stat-icon.rose   { background:#fff1f2; color:#f43f5e; }
.fa-stat-val   { font-size:26px; font-weight:800; color:#0f172a; line-height:1; }
.fa-stat-label { font-size:12px; color:#64748b; margin-top:4px; font-weight:500; }

/* Form card */
.fa-form-card {
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    box-shadow:0 1px 4px rgba(0,0,0,.04); overflow:hidden;
    transition:box-shadow .2s;
}
.fa-form-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); }
.fa-form-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px; border-bottom:1px solid #f1f5f9;
}
.fa-form-icon {
    width:46px; height:46px; border-radius:12px; display:flex;
    align-items:center; justify-content:center; color:#fff; font-size:18px; flex-shrink:0;
}
.fa-form-name  { font-size:17px; font-weight:700; color:#0f172a; }
.fa-form-meta  { font-size:12px; color:#94a3b8; margin-top:3px; }

/* Stats row */
.fa-metrics { display:grid; grid-template-columns:repeat(4,1fr); border-bottom:1px solid #f1f5f9; }
.fa-metric-cell { padding:16px 20px; text-align:center; border-right:1px solid #f1f5f9; }
.fa-metric-cell:last-child { border-right:none; }
.fa-metric-val   { font-size:26px; font-weight:800; color:#0f172a; }
.fa-metric-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }

/* Mini trend bars */
.fa-mini-trend { display:flex; align-items:flex-end; gap:2px; height:40px; }
.fa-mini-bar   { flex:1; border-radius:2px 2px 0 0; min-height:0; }
.fa-mini-bar.active { background:linear-gradient(180deg,#60a5fa,#3b82f6); }
.fa-mini-bar.empty  { background:#e2e8f0; }

/* Breakdown bar */
.fa-breakdown-bar { height:10px; background:#f1f5f9; border-radius:5px; overflow:hidden; display:flex; }

/* Recent table */
.fa-recent-head {
    padding:12px 24px; background:#f8fafc; border-bottom:1px solid #f1f5f9;
    font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.6px;
}
.fa-sub-row {
    display:flex; align-items:center; padding:12px 24px; border-bottom:1px solid #f8fafc;
    transition:background .15s;
}
.fa-sub-row:last-child { border-bottom:none; }
.fa-sub-row:hover { background:#f9fafb; }
.fa-badge {
    font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; white-space:nowrap;
}
.fa-view-all {
    padding:12px 24px; text-align:center; border-top:1px solid #f1f5f9;
    font-size:13px; color:#3b82f6; font-weight:600; text-decoration:none; display:block;
    transition:background .15s;
}
.fa-view-all:hover { background:#eff6ff; }

/* Empty state */
.fa-empty {
    padding:48px; text-align:center; color:#94a3b8;
}
.fa-empty-icon { font-size:40px; margin-bottom:12px; opacity:.4; }

/* Pagination */
.fa-pagination { display:flex; justify-content:center; align-items:center; margin-top:8px; }
</style>
@endpush

@section('content')
<div class="fa-page">

    {{-- ── Header ──────────────────────────────────────────────────── --}}
    <div class="fa-header">
        <div>
            <h1 class="fa-header-title">Form Analytics</h1>
            <p class="fa-header-sub">Submission statistics for every form — track completions, drafts, and trends</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="{{ route('analytics.funnels') }}" class="btn btn-secondary">
                <i class="fas fa-filter" style="margin-right:6px;"></i>Funnel Analytics
            </a>
            <a href="{{ route('analytics.reports') }}" class="btn btn-primary">
                <i class="fas fa-chart-line" style="margin-right:6px;"></i>Reports Overview
            </a>
        </div>
    </div>

    {{-- ── Date Filter ─────────────────────────────────────────────── --}}
    <div class="fa-filter-card">
        <form method="GET" id="fa-filter-form">
            <div class="fa-filter-inner">
                <div class="fa-filter-field">
                    <label>From Date</label>
                    <input type="date" name="from" id="fa-from" value="{{ $from }}">
                </div>
                <div class="fa-filter-field">
                    <label>To Date</label>
                    <input type="date" name="to" id="fa-to" value="{{ $to }}">
                </div>
                <button type="submit" class="btn btn-primary" style="height:40px;border-radius:10px;">
                    <i class="fas fa-search" style="margin-right:6px;"></i>Apply Filter
                </button>
                <a href="{{ route('analytics.forms') }}" class="btn btn-secondary" style="height:40px;line-height:40px;padding:0 16px;border-radius:10px;">
                    Reset
                </a>
                <div class="fa-shortcuts">
                    <button type="button" onclick="faRange(7)"  class="fa-shortcut-btn">Last 7 days</button>
                    <button type="button" onclick="faRange(30)" class="fa-shortcut-btn">Last 30 days</button>
                    <button type="button" onclick="faRange(90)" class="fa-shortcut-btn">Last 90 days</button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Summary Stats ───────────────────────────────────────────── --}}
    <div class="fa-stats">
        <div class="fa-stat-card blue">
            <div class="fa-stat-icon blue"><i class="fas fa-wpforms"></i></div>
            <div>
                <div class="fa-stat-val">{{ $summary['total_forms'] }}</div>
                <div class="fa-stat-label">Total Forms</div>
            </div>
        </div>
        <div class="fa-stat-card green">
            <div class="fa-stat-icon green"><i class="fas fa-toggle-on"></i></div>
            <div>
                <div class="fa-stat-val">{{ $summary['active_forms'] }}</div>
                <div class="fa-stat-label">Active Forms</div>
            </div>
        </div>
        <div class="fa-stat-card violet">
            <div class="fa-stat-icon violet"><i class="fas fa-paper-plane"></i></div>
            <div>
                <div class="fa-stat-val">{{ $summary['total_submissions'] }}</div>
                <div class="fa-stat-label">All-Time Submissions</div>
            </div>
        </div>
        <div class="fa-stat-card amber">
            <div class="fa-stat-icon amber"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="fa-stat-val">{{ $summary['period_submissions'] }}</div>
                <div class="fa-stat-label">Period Submissions</div>
            </div>
        </div>
        <div class="fa-stat-card rose">
            <div class="fa-stat-icon rose"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="fa-stat-val">{{ $summary['avg_completion_rate'] }}%</div>
                <div class="fa-stat-label">Completion Rate</div>
            </div>
        </div>
    </div>

    {{-- ── Per-Form Cards ──────────────────────────────────────────── --}}
    @forelse($forms as $form)
    @php
        $total     = $form->stats['total_submissions'];
        $completed = $form->stats['completed'];
        $drafts    = $form->stats['drafts'];
        $rate      = $form->stats['rate'];
        $trend     = array_values($form->stats['daily_trend']);
        $maxTrend  = max(max($trend), 1);
        $rateColor = $rate >= 75 ? '#22c55e' : ($rate >= 40 ? '#f59e0b' : '#ef4444');
    @endphp
    <div class="fa-form-card">

        {{-- Header --}}
        <div class="fa-form-header">
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="fa-form-icon" style="background:linear-gradient(135deg,#3b82f6,#06b6d4);">
                    <i class="fas fa-wpforms"></i>
                </div>
                <div>
                    <div class="fa-form-name">{{ $form->name }}</div>
                    <div class="fa-form-meta">
                        {{ $form->field_count }} field{{ $form->field_count != 1 ? 's' : '' }}
                        &nbsp;&middot;&nbsp;
                        <span style="color:{{ $form->is_active ? '#22c55e' : '#f59e0b' }};">
                            {{ $form->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if($form->category)
                        &nbsp;&middot;&nbsp; {{ ucfirst($form->category) }}
                        @endif
                        &nbsp;&middot;&nbsp; Created {{ $form->created_at->format('M d, Y') }}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                {{-- Mini trend chart --}}
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;margin-right:8px;">
                    <div class="fa-mini-trend">
                        @foreach($trend as $cnt)
                        <div class="fa-mini-bar {{ $cnt > 0 ? 'active' : 'empty' }}"
                             style="height:{{ $maxTrend > 0 ? max(round(($cnt/$maxTrend)*100),($cnt>0?8:0)) : 0 }}%;"></div>
                        @endforeach
                    </div>
                    <div style="font-size:10px;color:#94a3b8;">{{ $form->stats['period_subs'] }} this period</div>
                </div>
                <a href="{{ route('forms.show', $form->id) }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-eye" style="margin-right:4px;"></i>Submissions
                </a>
                <a href="{{ route('forms.builder', $form->id) }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-edit" style="margin-right:4px;"></i>Edit
                </a>
            </div>
        </div>

        {{-- Metrics row --}}
        <div class="fa-metrics">
            <div class="fa-metric-cell">
                <div class="fa-metric-val">{{ $total }}</div>
                <div class="fa-metric-label">Total Submissions</div>
            </div>
            <div class="fa-metric-cell">
                <div class="fa-metric-val" style="color:#22c55e;">{{ $completed }}</div>
                <div class="fa-metric-label">Completed</div>
            </div>
            <div class="fa-metric-cell">
                <div class="fa-metric-val" style="color:#f59e0b;">{{ $drafts }}</div>
                <div class="fa-metric-label">Saved as Draft</div>
            </div>
            <div class="fa-metric-cell">
                <div class="fa-metric-val" style="color:{{ $rateColor }};">{{ $rate }}%</div>
                <div class="fa-metric-label">Completion Rate</div>
            </div>
        </div>

        @if($total > 0)
        {{-- Breakdown bar --}}
        <div style="padding:16px 24px;border-bottom:1px solid #f1f5f9;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:600;color:#64748b;">Submission Breakdown</span>
                <span style="font-size:12px;color:#94a3b8;">{{ $total }} total</span>
            </div>
            <div class="fa-breakdown-bar">
                @if($completed > 0)
                <div style="width:{{ ($completed/$total)*100 }}%;background:linear-gradient(90deg,#22c55e,#16a34a);height:100%;" title="{{ $completed }} Completed"></div>
                @endif
                @if($drafts > 0)
                <div style="width:{{ ($drafts/$total)*100 }}%;background:linear-gradient(90deg,#f59e0b,#d97706);height:100%;" title="{{ $drafts }} Drafts"></div>
                @endif
            </div>
            <div style="display:flex;gap:16px;margin-top:8px;">
                <span style="font-size:11px;color:#22c55e;display:flex;align-items:center;gap:5px;">
                    <span style="width:8px;height:8px;background:#22c55e;border-radius:2px;display:inline-block;"></span>
                    Completed ({{ $completed }})
                </span>
                <span style="font-size:11px;color:#f59e0b;display:flex;align-items:center;gap:5px;">
                    <span style="width:8px;height:8px;background:#f59e0b;border-radius:2px;display:inline-block;"></span>
                    Draft ({{ $drafts }})
                </span>
            </div>
        </div>

        {{-- Recent submissions --}}
        <div class="fa-recent-head">Recent Submissions</div>
        @forelse($form->recentSubmissions as $sub)
        @php
            $sc = match($sub->status) {
                'completed' => ['bg'=>'#f0fdf4','text'=>'#16a34a','label'=>'Completed'],
                'draft'     => ['bg'=>'#fffbeb','text'=>'#d97706','label'=>'Draft'],
                default     => ['bg'=>'#eff6ff','text'=>'#2563eb','label'=>ucfirst($sub->status)],
            };
        @endphp
        <div class="fa-sub-row">
            <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#1d4ed8;flex-shrink:0;">
                    {{ strtoupper(substr($sub->patient_name ?: 'A', 0, 1)) }}
                </div>
                <div style="min-width:0;">
                    <div style="font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $sub->patient_name ?: 'Anonymous' }}
                    </div>
                    @if($sub->patient_email)
                    <div style="font-size:11px;color:#94a3b8;">{{ $sub->patient_email }}</div>
                    @endif
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;flex-shrink:0;">
                <span class="fa-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">{{ $sc['label'] }}</span>
                <span style="font-size:12px;color:#94a3b8;">{{ $sub->created_at->format('M d, Y g:i A') }}</span>
                <a href="{{ route('forms.show', $form->id) }}" style="font-size:12px;color:#3b82f6;font-weight:600;">View →</a>
            </div>
        </div>
        @empty
        <div class="fa-empty">
            <div class="fa-empty-icon"><i class="fas fa-inbox"></i></div>
            <div style="font-size:14px;font-weight:500;">No submissions yet</div>
        </div>
        @endforelse

        @if($total > 5)
        <a href="{{ route('forms.show', $form->id) }}" class="fa-view-all">
            View all {{ $total }} submissions <i class="fas fa-arrow-right" style="font-size:11px;margin-left:4px;"></i>
        </a>
        @endif

        @else
        <div class="fa-empty">
            <div class="fa-empty-icon"><i class="fas fa-paper-plane"></i></div>
            <div style="font-size:14px;font-weight:500;color:#475569;">No submissions yet</div>
            <div style="font-size:12px;margin-top:4px;">Share this form's public link or add it to a funnel to start collecting submissions.</div>
        </div>
        @endif

    </div>
    @empty
    <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:64px;text-align:center;">
        <div style="font-size:48px;color:#cbd5e1;margin-bottom:16px;"><i class="fas fa-wpforms"></i></div>
        <div style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:8px;">No forms created yet</div>
        <p style="color:#94a3b8;margin-bottom:20px;">Create your first form to start collecting data.</p>
        <a href="{{ route('forms.create') }}" class="btn btn-primary">Create Form</a>
    </div>
    @endforelse

    @if($forms->hasPages())
    <div class="fa-pagination">
        {{ $forms->links('vendor.pagination.custom') }}
    </div>
    @endif

</div>

<script>
function faRange(days) {
    var to   = new Date();
    var from = new Date();
    from.setDate(from.getDate() - days);
    document.getElementById('fa-from').value = from.toISOString().split('T')[0];
    document.getElementById('fa-to').value   = to.toISOString().split('T')[0];
    document.getElementById('fa-filter-form').submit();
}
</script>
@endsection
