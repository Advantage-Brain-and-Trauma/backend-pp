@extends('layouts.app')

@section('title', 'Dashboard - AdvantageHCS Admin')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Welcome back, ' . auth()->user()->name . '. Here\'s what\'s happening.')

@section('content')
<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wpforms"></i></div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['total_forms'] }}</div>
            <div class="stat-label">Total Forms</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['active_forms'] }}</div>
            <div class="stat-label">Active Forms</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-filter"></i></div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['total_funnels'] }}</div>
            <div class="stat-label">Total Funnels</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-funnel-dollar"></i></div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['active_funnels'] }}</div>
            <div class="stat-label">Active Funnels</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-inbox"></i></div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['total_submissions'] }}</div>
            <div class="stat-label">Total Submissions</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
        <div class="stat-info">
            <div class="stat-value">{{ $stats['unread_messages'] }}</div>
            <div class="stat-label">Unread Messages</div>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">

    <!-- Recent Form Submissions -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Submissions</span>
            <a href="{{ route('forms.index') }}" class="btn btn-secondary btn-sm">View Forms</a>
        </div>
        @if($recentSubmissions->isEmpty())
            <div class="card-body" style="text-align:center; color:#9ca3af; padding:40px;">
                <i class="fas fa-inbox" style="font-size:32px; margin-bottom:12px; display:block;"></i>
                No submissions yet
            </div>
        @else
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Form</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentSubmissions as $submission)
                        <tr>
                            <td style="font-weight:500;">{{ $submission->form->name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $submission->status === 'reviewed' ? 'badge-success' : ($submission->status === 'pending' ? 'badge-warning' : 'badge-secondary') }}">
                                    {{ ucfirst($submission->status) }}
                                </span>
                            </td>
                            <td style="font-size:12px; color:#6b7280;">{{ $submission->created_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Recent Unread Messages -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Unread Messages</span>
            <a href="{{ route('messages.index') }}" class="btn btn-secondary btn-sm">View All</a>
        </div>
        @if($recentMessages->isEmpty())
            <div class="card-body" style="text-align:center; color:#9ca3af; padding:40px;">
                <i class="fas fa-envelope-open" style="font-size:32px; margin-bottom:12px; display:block;"></i>
                No unread messages
            </div>
        @else
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentMessages as $message)
                        <tr>
                            <td style="font-weight:500;">{{ $message->subject }}</td>
                            <td>
                                <span class="badge badge-warning">{{ ucfirst($message->status) }}</span>
                            </td>
                            <td style="font-size:12px; color:#6b7280;">{{ $message->created_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

<!-- Quick Actions -->
<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <span class="card-title">Quick Actions</span>
    </div>
    <div class="card-body">
        <div style="display:flex; gap:16px; flex-wrap:wrap;">
            <a href="{{ route('forms.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Form
            </a>
            <a href="{{ route('funnels.create') }}" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Create Funnel
            </a>
            <a href="{{ route('messages.index') }}" class="btn btn-secondary">
                <i class="fas fa-envelope"></i> View Messages
            </a>
        </div>
    </div>
</div>
@endsection
