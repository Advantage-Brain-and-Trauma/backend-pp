@extends('layouts.app')

@section('title', 'Reports Overview')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Reports Overview</h1>
        <p class="page-subtitle">High-level summary of all form and funnel activity</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="{{ route('analytics.funnels') }}" class="btn btn-secondary">Funnel Analytics</a>
        <a href="{{ route('analytics.forms') }}" class="btn btn-secondary">Form Analytics</a>
    </div>
</div>

{{-- Date Range Filter --}}
<div class="card" style="margin-bottom:24px;padding:16px 20px;">
    <form method="GET" id="reports-filter-form" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div>
            <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">From Date</label>
            <input type="date" name="from" id="filter-from" value="{{ $from }}" class="form-control" style="height:38px;">
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">To Date</label>
            <input type="date" name="to" id="filter-to" value="{{ $to }}" class="form-control" style="height:38px;">
        </div>
        <button type="submit" class="btn btn-primary" style="height:38px;">Apply Filter</button>
        <a href="{{ route('analytics.reports') }}" class="btn btn-secondary" style="height:38px;line-height:38px;padding:0 16px;">Reset</a>
        <div style="margin-left:auto;display:flex;gap:8px;">
            <button type="button" onclick="setRange(7)"  class="btn btn-sm btn-secondary">Last 7 days</button>
            <button type="button" onclick="setRange(30)" class="btn btn-sm btn-secondary">Last 30 days</button>
            <button type="button" onclick="setRange(90)" class="btn btn-sm btn-secondary">Last 90 days</button>
        </div>
    </form>
</div>

{{-- Big Stats Row --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">

    {{-- Total Forms --}}
    <div class="stat-card" style="border-left:4px solid #3b82f6;">
        <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">
            <i class="fas fa-wpforms"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ number_format($stats['total_forms']) }}</div>
            <div class="stat-label">Total Forms</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $stats['active_forms'] }} active</div>
        </div>
    </div>

    {{-- Total Funnels --}}
    <div class="stat-card" style="border-left:4px solid #6366f1;">
        <div class="stat-icon" style="background:#f5f3ff;color:#6366f1;">
            <i class="fas fa-filter"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ number_format($stats['total_funnels']) }}</div>
            <div class="stat-label">Total Funnels</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $stats['active_funnels'] }} active</div>
        </div>
    </div>

    {{-- Total Submissions (all-time) with period change --}}
    <div class="stat-card" style="border-left:4px solid #22c55e;">
        <div class="stat-icon" style="background:#f0fdf4;color:#22c55e;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ number_format($stats['total_submissions']) }}</div>
            <div class="stat-label">Total Submissions</div>
            @php $change = $stats['period_change']; @endphp
            <div style="font-size:11px;margin-top:2px;color:{{ $change >= 0 ? '#22c55e' : '#ef4444' }};">
                {{ $change >= 0 ? '+' : '' }}{{ $change }} this period
            </div>
        </div>
    </div>

    {{-- Completion Rate --}}
    <div class="stat-card" style="border-left:4px solid #f59e0b;">
        <div class="stat-icon" style="background:#fffbeb;color:#f59e0b;">
            <i class="fas fa-chart-bar"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['completion_rate'] }}%</div>
            <div class="stat-label">Completion Rate</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Across all forms</div>
        </div>
    </div>
</div>

{{-- Trend Chart + Status Breakdown --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

    {{-- Daily Submissions Trend --}}
    <div class="card">
        <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin:0;">Submissions Trend</h3>
            <p style="font-size:12px;color:#94a3b8;margin:4px 0 0;">Daily submissions for selected period</p>
        </div>
        <div style="padding:24px;">
            @php
                $trendDates  = array_keys($stats['daily_trend']);
                $trendCounts = array_values($stats['daily_trend']);
                $maxCount    = max(max($trendCounts), 1);
            @endphp
            @if(array_sum($trendCounts) === 0)
                <div style="text-align:center;padding:40px 0;color:#94a3b8;">
                    <i class="fas fa-chart-line" style="font-size:32px;display:block;margin-bottom:12px;"></i>
                    No submissions in this period
                </div>
            @else
                {{-- Simple bar chart --}}
                <div style="display:flex;align-items:flex-end;gap:3px;height:120px;padding-bottom:4px;">
                    @foreach($trendCounts as $i => $cnt)
                    @php $barH = round(($cnt / $maxCount) * 100); @endphp
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;" title="{{ $trendDates[$i] }}: {{ $cnt }}">
                        <div style="width:100%;background:{{ $cnt > 0 ? '#6366f1' : '#e2e8f0' }};border-radius:3px 3px 0 0;height:{{ max($barH, $cnt > 0 ? 4 : 0) }}%;transition:height .3s;"></div>
                    </div>
                    @endforeach
                </div>
                {{-- X-axis labels (show first, middle, last) --}}
                @php $tCount = count($trendDates); @endphp
                <div style="display:flex;justify-content:space-between;margin-top:6px;">
                    <span style="font-size:10px;color:#94a3b8;">{{ \Carbon\Carbon::parse($trendDates[0])->format('M d') }}</span>
                    @if($tCount > 2)
                    <span style="font-size:10px;color:#94a3b8;">{{ \Carbon\Carbon::parse($trendDates[intval($tCount/2)])->format('M d') }}</span>
                    @endif
                    <span style="font-size:10px;color:#94a3b8;">{{ \Carbon\Carbon::parse($trendDates[$tCount-1])->format('M d') }}</span>
                </div>
            @endif

            {{-- Period summary below chart --}}
            <div style="display:flex;gap:16px;margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9;">
                <div style="flex:1;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#1e293b;">{{ $stats['period_submissions'] }}</div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">This Period</div>
                </div>
                <div style="flex:1;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:{{ $stats['period_change'] >= 0 ? '#22c55e' : '#ef4444' }};">
                        {{ $stats['period_change'] >= 0 ? '+' : '' }}{{ $stats['period_change'] }}
                    </div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">vs Prev Period</div>
                </div>
                <div style="flex:1;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#1e293b;">{{ $stats['completion_rate'] }}%</div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Completion</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Form Submission Status Breakdown --}}
    <div class="card">
        <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin:0;">Form Submission Status</h3>
            <p style="font-size:12px;color:#94a3b8;margin:4px 0 0;">Breakdown by submission type for selected period</p>
        </div>
        <div style="padding:24px;">
            @php
                $sTotal     = max($stats['submissions_by_status']['total'], 1);
                $sCompleted = $stats['submissions_by_status']['completed'];
                $sDrafts    = $stats['submissions_by_status']['drafts'];
            @endphp

            {{-- Progress bar --}}
            <div style="display:flex;height:24px;border-radius:12px;overflow:hidden;margin-bottom:20px;">
                @if($sCompleted > 0)<div style="flex:{{ $sCompleted }};background:#22c55e;"></div>@endif
                @if($sDrafts > 0)<div style="flex:{{ $sDrafts }};background:#f59e0b;"></div>@endif
                @if($sCompleted === 0 && $sDrafts === 0)
                    <div style="flex:1;background:#e2e8f0;"></div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach([
                    ['label' => 'Completed Submissions', 'value' => $sCompleted, 'color' => '#22c55e'],
                    ['label' => 'Saved as Draft',        'value' => $sDrafts,    'color' => '#f59e0b'],
                ] as $row)
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:12px;height:12px;border-radius:3px;background:{{ $row['color'] }};flex-shrink:0;"></div>
                        <span style="font-size:14px;color:#374151;">{{ $row['label'] }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:100px;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                            <div style="height:100%;width:{{ $sTotal > 0 ? round(($row['value']/$sTotal)*100) : 0 }}%;background:{{ $row['color'] }};border-radius:3px;"></div>
                        </div>
                        <span style="font-size:14px;font-weight:700;color:#1e293b;min-width:24px;text-align:right;">{{ $row['value'] }}</span>
                        <span style="font-size:12px;color:#94a3b8;min-width:36px;text-align:right;">{{ $sTotal > 0 ? round(($row['value']/$sTotal)*100) : 0 }}%</span>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Top Forms by Submissions --}}
            <div style="margin-top:24px;padding-top:20px;border-top:1px solid #f1f5f9;">
                <div style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Top Forms by Submissions</div>
                @forelse($stats['top_forms'] as $tf)
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-size:13px;color:#374151;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">{{ $tf->name }}</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:80px;height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                            @php $maxF = $stats['top_forms']->first()->submissions_count ?? 1; @endphp
                            <div style="height:100%;width:{{ $maxF > 0 ? round(($tf->submissions_count/$maxF)*100) : 0 }}%;background:#3b82f6;border-radius:3px;"></div>
                        </div>
                        <span style="font-size:13px;font-weight:700;color:#1e293b;min-width:20px;text-align:right;">{{ $tf->submissions_count }}</span>
                    </div>
                </div>
                @empty
                <div style="font-size:13px;color:#94a3b8;text-align:center;padding:12px 0;">No submissions yet</div>
                @endforelse
            </div>
        </div>
        <div style="padding:12px 24px;border-top:1px solid #f1f5f9;text-align:right;">
            <a href="{{ route('analytics.forms') }}" style="font-size:13px;color:#6366f1;font-weight:600;">View Form Analytics →</a>
        </div>
    </div>
</div>

{{-- Top Funnels + Recent Submissions --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

    {{-- Top Funnels by Submissions --}}
    <div class="card">
        <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin:0;">Top Funnels by Submissions</h3>
            <p style="font-size:12px;color:#94a3b8;margin:4px 0 0;">Most active funnels by submission count</p>
        </div>
        <div style="padding:24px;">
            @forelse($stats['top_funnels'] as $tf)
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                    <div style="width:32px;height:32px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0;">
                        <i class="fas fa-filter"></i>
                    </div>
                    <span style="font-size:13px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tf->name }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px;">
                    <div style="width:80px;height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                        @php $maxFn = $stats['top_funnels']->first()->submissions_count ?? 1; @endphp
                        <div style="height:100%;width:{{ $maxFn > 0 ? round(($tf->submissions_count/$maxFn)*100) : 0 }}%;background:#6366f1;border-radius:3px;"></div>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:#1e293b;min-width:20px;text-align:right;">{{ $tf->submissions_count }}</span>
                </div>
            </div>
            @empty
            <div style="font-size:13px;color:#94a3b8;text-align:center;padding:24px 0;">No funnel submissions yet</div>
            @endforelse
        </div>
        <div style="padding:12px 24px;border-top:1px solid #f1f5f9;text-align:right;">
            <a href="{{ route('analytics.funnels') }}" style="font-size:13px;color:#6366f1;font-weight:600;">View Funnel Analytics →</a>
        </div>
    </div>

    {{-- Recent Submissions --}}
    <div class="card">
        <div style="padding:20px 24px;border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin:0;">Recent Submissions</h3>
            <p style="font-size:12px;color:#94a3b8;margin:4px 0 0;">Latest form submissions in selected period</p>
        </div>
        <div style="padding:0;">
            @forelse($stats['recent_submissions'] as $sub)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 24px;border-bottom:1px solid #f8fafc;">
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $sub->patient_name ?: ($sub->form->name ?? 'Unknown Form') }}
                    </div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                        {{ $sub->form->name ?? '—' }} &middot; {{ $sub->created_at->diffForHumans() }}
                    </div>
                </div>
                <div style="flex-shrink:0;margin-left:12px;">
                    @php
                        $statusColor = match($sub->status) {
                            'completed' => ['bg' => '#f0fdf4', 'text' => '#16a34a'],
                            'draft'     => ['bg' => '#fffbeb', 'text' => '#d97706'],
                            default     => ['bg' => '#eff6ff', 'text' => '#2563eb'],
                        };
                    @endphp
                    <span style="font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;background:{{ $statusColor['bg'] }};color:{{ $statusColor['text'] }};">
                        {{ ucfirst($sub->status) }}
                    </span>
                </div>
            </div>
            @empty
            <div style="padding:40px;text-align:center;color:#94a3b8;">
                <i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                No submissions in this period
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Quick Links --}}
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
    <a href="{{ route('analytics.funnels') }}" style="text-decoration:none;">
        <div class="card" style="padding:20px 24px;display:flex;align-items:center;gap:16px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;flex-shrink:0;">
                <i class="fas fa-filter"></i>
            </div>
            <div>
                <div style="font-size:15px;font-weight:700;color:#1e293b;">Funnel Analytics</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Per-funnel completion breakdown</div>
            </div>
        </div>
    </a>
    <a href="{{ route('analytics.forms') }}" style="text-decoration:none;">
        <div class="card" style="padding:20px 24px;display:flex;align-items:center;gap:16px;transition:box-shadow .2s;" onmouseover="this.style.boxShadow='0 4px 20px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#3b82f6,#06b6d4);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;flex-shrink:0;">
                <i class="fas fa-wpforms"></i>
            </div>
            <div>
                <div style="font-size:15px;font-weight:700;color:#1e293b;">Form Analytics</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Per-form submission stats</div>
            </div>
        </div>
    </a>
</div>

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
