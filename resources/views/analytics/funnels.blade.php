@extends('layouts.app')

@section('title', 'Funnel Analytics')

@push('styles')
<style>
/* ── Funnel Analytics page ─────────────────────────────────── */
.fna-page { display:flex; flex-direction:column; gap:28px; }

/* Header */
.fna-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.fna-header-title { font-size:26px; font-weight:800; color:#0f172a; margin:0; letter-spacing:-.4px; }
.fna-header-sub   { font-size:13px; color:#64748b; margin:4px 0 0; }

/* Filter card */
.fna-filter-card {
    background:#fff; border:1px solid #e2e8f0; border-radius:16px;
    padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,.04);
}
.fna-filter-inner { display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap; }
.fna-filter-field label {
    font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;
    letter-spacing:.5px; display:block; margin-bottom:6px;
}
.fna-filter-field input[type="date"] {
    height:40px; border:1.5px solid #e2e8f0; border-radius:10px;
    padding:0 12px; font-size:13px; color:#1e293b; background:#f8fafc; outline:none; transition:border .2s;
}
.fna-filter-field input[type="date"]:focus { border-color:#6366f1; background:#fff; }
.fna-shortcuts { margin-left:auto; display:flex; gap:8px; align-items:center; }
.fna-shortcut-btn {
    font-size:12px; font-weight:600; padding:8px 14px; border-radius:8px;
    border:1.5px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; transition:all .2s;
}
.fna-shortcut-btn:hover { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }

/* Summary stats */
.fna-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:18px; }
.fna-stat-card {
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    padding:20px 22px; display:flex; align-items:center; gap:16px;
    box-shadow:0 1px 4px rgba(0,0,0,.04); transition:box-shadow .2s, transform .2s;
    position:relative; overflow:hidden;
}
.fna-stat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.08); transform:translateY(-2px); }
.fna-stat-card::before {
    content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius:4px 0 0 4px;
}
.fna-stat-card.indigo::before { background:#6366f1; }
.fna-stat-card.green::before  { background:#22c55e; }
.fna-stat-card.blue::before   { background:#3b82f6; }
.fna-stat-card.amber::before  { background:#f59e0b; }
.fna-stat-card.violet::before { background:#8b5cf6; }
.fna-stat-icon {
    width:48px; height:48px; border-radius:12px; display:flex;
    align-items:center; justify-content:center; font-size:20px; flex-shrink:0;
}
.fna-stat-icon.indigo { background:#f5f3ff; color:#6366f1; }
.fna-stat-icon.green  { background:#f0fdf4; color:#22c55e; }
.fna-stat-icon.blue   { background:#eff6ff; color:#3b82f6; }
.fna-stat-icon.amber  { background:#fffbeb; color:#f59e0b; }
.fna-stat-icon.violet { background:#f5f3ff; color:#8b5cf6; }
.fna-stat-val   { font-size:26px; font-weight:800; color:#0f172a; line-height:1; }
.fna-stat-label { font-size:12px; color:#64748b; margin-top:4px; font-weight:500; }

/* Funnel card */
.fna-funnel-card {
    background:#fff; border-radius:16px; border:1px solid #e2e8f0;
    box-shadow:0 1px 4px rgba(0,0,0,.04); overflow:hidden; transition:box-shadow .2s;
}
.fna-funnel-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); }
.fna-funnel-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px; border-bottom:1px solid #f1f5f9;
}
.fna-funnel-icon {
    width:46px; height:46px; border-radius:12px; display:flex;
    align-items:center; justify-content:center; color:#fff; font-size:18px; flex-shrink:0;
}
.fna-funnel-name { font-size:17px; font-weight:700; color:#0f172a; }
.fna-funnel-meta { font-size:12px; color:#94a3b8; margin-top:3px; }

/* Metrics row */
.fna-metrics { display:grid; grid-template-columns:repeat(4,1fr); border-bottom:1px solid #f1f5f9; }
.fna-metric-cell { padding:16px 20px; text-align:center; border-right:1px solid #f1f5f9; }
.fna-metric-cell:last-child { border-right:none; }
.fna-metric-val   { font-size:26px; font-weight:800; color:#0f172a; }
.fna-metric-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }

/* Mini trend */
.fna-mini-trend { display:flex; align-items:flex-end; gap:2px; height:40px; }
.fna-mini-bar   { flex:1; border-radius:2px 2px 0 0; min-height:0; }
.fna-mini-bar.active { background:linear-gradient(180deg,#a78bfa,#6366f1); }
.fna-mini-bar.empty  { background:#e2e8f0; }

/* Breakdown bar */
.fna-breakdown-bar { height:10px; background:#f1f5f9; border-radius:5px; overflow:hidden; display:flex; }

/* Steps pipeline */
.fna-steps { display:flex; align-items:center; gap:0; padding:16px 24px; border-bottom:1px solid #f1f5f9; flex-wrap:wrap; }
.fna-step {
    display:flex; align-items:center; gap:8px; padding:8px 14px;
    background:#f8fafc; border-radius:10px; font-size:12px; font-weight:600; color:#475569;
    border:1px solid #e2e8f0;
}
.fna-step-arrow { color:#cbd5e1; font-size:12px; margin:0 4px; }

/* Recent table */
.fna-recent-head {
    padding:12px 24px; background:#f8fafc; border-bottom:1px solid #f1f5f9;
    font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.6px;
}
.fna-sub-row {
    display:flex; align-items:center; padding:12px 24px; border-bottom:1px solid #f8fafc; transition:background .15s;
}
.fna-sub-row:last-child { border-bottom:none; }
.fna-sub-row:hover { background:#f9fafb; }
.fna-badge { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; white-space:nowrap; }

/* Empty */
.fna-empty { padding:48px; text-align:center; color:#94a3b8; }
.fna-empty-icon { font-size:40px; margin-bottom:12px; opacity:.4; }

/* Pagination */
.fna-pagination { display:flex; justify-content:center; align-items:center; margin-top:8px; }
</style>
@endpush

@section('content')
<div class="fna-page">

    {{-- ── Header ──────────────────────────────────────────────────── --}}
    <div class="fna-header">
        <div>
            <h1 class="fna-header-title">Funnel Analytics</h1>
            <p class="fna-header-sub">Completion statistics for every funnel — submissions, drafts, and trends</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="{{ route('analytics.forms') }}" class="btn btn-secondary">
                <i class="fas fa-wpforms" style="margin-right:6px;"></i>Form Analytics
            </a>
            <a href="{{ route('analytics.reports') }}" class="btn btn-primary">
                <i class="fas fa-chart-line" style="margin-right:6px;"></i>Reports Overview
            </a>
        </div>
    </div>

    {{-- ── Date Filter ─────────────────────────────────────────────── --}}
    <div class="fna-filter-card">
        <form method="GET" id="fna-filter-form">
            <div class="fna-filter-inner">
                <div class="fna-filter-field">
                    <label>Search Funnel</label>
                    <input type="text" name="search" id="fna-search" value="{{ $search }}" placeholder="Funnel name..." style="height:40px;border:1.5px solid #e2e8f0;border-radius:10px;padding:0 12px;font-size:13px;color:#1e293b;background:#f8fafc;outline:none;transition:border .2s;width:180px;">
                </div>
                <div class="fna-filter-field">
                    <label>From Date</label>
                    <input type="date" name="from" id="fna-from" value="{{ $from }}">
                </div>
                <div class="fna-filter-field">
                    <label>To Date</label>
                    <input type="date" name="to" id="fna-to" value="{{ $to }}">
                </div>
                <button type="submit" class="btn btn-primary" style="height:40px;border-radius:10px;">
                    <i class="fas fa-search" style="margin-right:6px;"></i>Apply Filter
                </button>
                <a href="{{ route('analytics.funnels') }}" class="btn btn-secondary" style="height:40px;line-height:40px;padding:0 16px;border-radius:10px;">
                    Reset
                </a>
                <div class="fna-shortcuts">
                    <button type="button" onclick="fnaRange(7)"  class="fna-shortcut-btn">Last 7 days</button>
                    <button type="button" onclick="fnaRange(30)" class="fna-shortcut-btn">Last 30 days</button>
                    <button type="button" onclick="fnaRange(90)" class="fna-shortcut-btn">Last 90 days</button>
                </div>
            </div>
        </form>
    </div>

    {{-- ── Summary Stats ───────────────────────────────────────────── --}}
    <div class="fna-stats">
        <div class="fna-stat-card indigo">
            <div class="fna-stat-icon indigo"><i class="fas fa-filter"></i></div>
            <div>
                <div class="fna-stat-val">{{ $summary['total_funnels'] }}</div>
                <div class="fna-stat-label">Total Funnels</div>
            </div>
        </div>
        <div class="fna-stat-card green">
            <div class="fna-stat-icon green"><i class="fas fa-toggle-on"></i></div>
            <div>
                <div class="fna-stat-val">{{ $summary['active_funnels'] }}</div>
                <div class="fna-stat-label">Active Funnels</div>
            </div>
        </div>
        <div class="fna-stat-card violet">
            <div class="fna-stat-icon violet"><i class="fas fa-paper-plane"></i></div>
            <div>
                <div class="fna-stat-val">{{ $summary['total_submissions'] }}</div>
                <div class="fna-stat-label">All-Time Submissions</div>
            </div>
        </div>
        <div class="fna-stat-card amber">
            <div class="fna-stat-icon amber"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="fna-stat-val">{{ $summary['period_submissions'] }}</div>
                <div class="fna-stat-label">Period Submissions</div>
            </div>
        </div>
        <div class="fna-stat-card blue">
            <div class="fna-stat-icon blue"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="fna-stat-val">{{ $summary['completion_rate'] }}%</div>
                <div class="fna-stat-label">Completion Rate</div>
            </div>
        </div>
    </div>

    {{-- ── Per-Funnel Cards ────────────────────────────────────────── --}}
    <div id="fna-cards-container">
    @forelse($funnels as $funnel)
    @php
        $total      = $funnel->stats['total'];
        $completed  = $funnel->stats['completed'];
        $inProgress = $funnel->stats['in_progress'];
        $rate       = $funnel->stats['rate'];
        $trend      = array_values($funnel->stats['daily_trend']);
        $maxTrend   = max(max($trend), 1);
        $rateColor  = $rate >= 75 ? '#22c55e' : ($rate >= 40 ? '#f59e0b' : '#ef4444');
        $steps      = is_array($funnel->steps) ? $funnel->steps : (json_decode($funnel->steps ?? '[]', true) ?: []);
    @endphp
    <div class="fna-funnel-card">

        {{-- Header --}}
        <div class="fna-funnel-header">
            <div style="display:flex;align-items:center;gap:14px;">
                <div class="fna-funnel-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <div class="fna-funnel-name">{{ $funnel->name }}</div>
                    <div class="fna-funnel-meta">
                        {{ $funnel->form_count }} form{{ $funnel->form_count != 1 ? 's' : '' }} in funnel
                        &nbsp;&middot;&nbsp;
                        <span style="color:{{ $funnel->status === 'active' ? '#22c55e' : '#f59e0b' }};">
                            {{ ucfirst($funnel->status) }}
                        </span>
                        &nbsp;&middot;&nbsp; Created {{ $funnel->created_at->format('M d, Y') }}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                {{-- Mini trend chart --}}
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;margin-right:8px;">
                    <div class="fna-mini-trend">
                        @foreach($trend as $cnt)
                        <div class="fna-mini-bar {{ $cnt > 0 ? 'active' : 'empty' }}"
                             style="height:{{ $maxTrend > 0 ? max(round(($cnt/$maxTrend)*100),($cnt>0?8:0)) : 0 }}%;"></div>
                        @endforeach
                    </div>
                    <div style="font-size:10px;color:#94a3b8;">{{ $funnel->stats['period_subs'] }} this period</div>
                </div>
                <a href="{{ route('funnels.edit', $funnel->id) }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-edit" style="margin-right:4px;"></i>Edit Funnel
                </a>
            </div>
        </div>

        {{-- Metrics row --}}
        <div class="fna-metrics">
            <div class="fna-metric-cell">
                <div class="fna-metric-val">{{ $total }}</div>
                <div class="fna-metric-label">Total Submissions</div>
            </div>
            <div class="fna-metric-cell">
                <div class="fna-metric-val" style="color:#22c55e;">{{ $completed }}</div>
                <div class="fna-metric-label">Completed</div>
            </div>
            <div class="fna-metric-cell">
                <div class="fna-metric-val" style="color:#3b82f6;">{{ $inProgress }}</div>
                <div class="fna-metric-label">In Progress</div>
            </div>
            <div class="fna-metric-cell">
                <div class="fna-metric-val" style="color:{{ $rateColor }};">{{ $rate }}%</div>
                <div class="fna-metric-label">Completion Rate</div>
            </div>
        </div>

        @if($total > 0)
        {{-- Breakdown bar --}}
        <div style="padding:16px 24px;border-bottom:1px solid #f1f5f9;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:600;color:#64748b;">Submission Distribution</span>
                <span style="font-size:12px;color:#94a3b8;">{{ $total }} total</span>
            </div>
            <div class="fna-breakdown-bar">
                @if($completed > 0)
                <div style="width:{{ ($completed/$total)*100 }}%;background:linear-gradient(90deg,#22c55e,#16a34a);height:100%;" title="{{ $completed }} Completed"></div>
                @endif
                @if($inProgress > 0)
                <div style="width:{{ ($inProgress/$total)*100 }}%;background:linear-gradient(90deg,#3b82f6,#1d4ed8);height:100%;" title="{{ $inProgress }} In Progress"></div>
                @endif
            </div>
            <div style="display:flex;gap:16px;margin-top:8px;">
                <span style="font-size:11px;color:#22c55e;display:flex;align-items:center;gap:5px;">
                    <span style="width:8px;height:8px;background:#22c55e;border-radius:2px;display:inline-block;"></span>
                    Completed ({{ $completed }})
                </span>
                <span style="font-size:11px;color:#3b82f6;display:flex;align-items:center;gap:5px;">
                    <span style="width:8px;height:8px;background:#3b82f6;border-radius:2px;display:inline-block;"></span>
                    In Progress ({{ $inProgress }})
                </span>
            </div>
        </div>

        {{-- Steps pipeline (if steps defined) --}}
        @if(count($steps) > 0)
        <div style="padding:14px 24px;border-bottom:1px solid #f1f5f9;">
            <div style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px;">Funnel Steps</div>
            <div class="fna-steps">
                @foreach($steps as $i => $step)
                <div class="fna-step">
                    <span style="width:20px;height:20px;background:#6366f1;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;">{{ $i+1 }}</span>
                    {{ $step['title'] ?? $step['name'] ?? ('Step ' . ($i+1)) }}
                </div>
                @if(!$loop->last)
                <span class="fna-step-arrow"><i class="fas fa-chevron-right"></i></span>
                @endif
                @endforeach
            </div>
        </div>
        @endif

        {{-- Recent submissions --}}
        <div class="fna-recent-head">Recent Submissions</div>
        @forelse($funnel->recentSubmissions as $sub)
        @php
            $isDraft = $sub->status === 'draft';
            $sc = $isDraft
                ? ['bg'=>'#fffbeb','text'=>'#d97706','label'=>'In Progress']
                : ['bg'=>'#f0fdf4','text'=>'#16a34a','label'=>'Completed'];
        @endphp
        <div class="fna-sub-row">
            <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#5b21b6;flex-shrink:0;">
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
                <span class="fna-badge" style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">{{ $sc['label'] }}</span>
                <span style="font-size:12px;color:#94a3b8;">{{ $sub->created_at->diffForHumans() }}</span>
            </div>
        </div>
        @empty
        <div class="fna-empty">
            <div class="fna-empty-icon"><i class="fas fa-inbox"></i></div>
            <div style="font-size:14px;font-weight:500;">No submissions yet</div>
        </div>
        @endforelse

        @else
        <div class="fna-empty">
            <div class="fna-empty-icon"><i class="fas fa-paper-plane"></i></div>
            <div style="font-size:14px;font-weight:500;color:#475569;">No submissions yet</div>
            <div style="font-size:12px;margin-top:4px;">Share this funnel's public link to start collecting submissions.</div>
        </div>
        @endif

    </div>
    @empty
    <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:64px;text-align:center;">
        <div style="font-size:48px;color:#cbd5e1;margin-bottom:16px;"><i class="fas fa-filter"></i></div>
        <div style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:8px;">No funnels created yet</div>
        <p style="color:#94a3b8;margin-bottom:20px;">Create your first funnel to start tracking submissions.</p>
        <a href="{{ route('funnels.create') }}" class="btn btn-primary">Create Funnel</a>
    </div>
    @endforelse

    @if($funnels->hasPages())
    <div class="fna-pagination">
        {{ $funnels->links('vendor.pagination.custom') }}
    </div>
    @endif
    </div>

</div>

<script>
function fnaRange(days) {
    var to   = new Date();
    var from = new Date();
    from.setDate(from.getDate() - days);
    document.getElementById('fna-from').value = from.toISOString().split('T')[0];
    document.getElementById('fna-to').value   = to.toISOString().split('T')[0];
    document.getElementById('fna-filter-form').submit();
}
var _fnaTimer;
document.getElementById('fna-search').addEventListener('input', function() {
    clearTimeout(_fnaTimer);
    var searchEl = this;
    _fnaTimer = setTimeout(function() {
        var form = document.getElementById('fna-filter-form');
        var params = new URLSearchParams(new FormData(form)).toString();
        var url = window.location.pathname + '?' + params;
        history.replaceState(null, '', url);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newCards = doc.getElementById('fna-cards-container');
                if (newCards) {
                    document.getElementById('fna-cards-container').innerHTML = newCards.innerHTML;
                }
                searchEl.focus();
                var val = searchEl.value;
                searchEl.value = '';
                searchEl.value = val;
            });
    }, 500);
});
</script>
@endsection
