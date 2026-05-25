@extends('layouts.app')

@section('title', $form->name . ' - Submissions')

@push('styles')
<style>
.fsi-page { display:flex; flex-direction:column; gap:18px; }
.fsi-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.fsi-title { margin:0; font-size:26px; font-weight:800; color:#0f172a; }
.fsi-sub { margin:4px 0 0; color:#64748b; font-size:13px; }
.fsi-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 1px 4px rgba(15,23,42,.04); }
.fsi-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid #f1f5f9; }
.fsi-row:last-child { border-bottom:none; }
.fsi-name { font-size:14px; font-weight:700; color:#0f172a; }
.fsi-email { font-size:12px; color:#94a3b8; margin-top:2px; }
.fsi-meta { display:flex; align-items:center; gap:12px; font-size:12px; color:#64748b; }
.fsi-badge { font-size:11px; font-weight:700; border-radius:999px; padding:4px 10px; }
.fsi-badge.completed { background:#f0fdf4; color:#16a34a; }
.fsi-badge.draft { background:#fffbeb; color:#d97706; }
.fsi-badge.default { background:#eff6ff; color:#2563eb; }
.fsi-empty { padding:46px 20px; text-align:center; color:#94a3b8; }
.fsi-footer { padding:14px 18px; background:#f8fafc; border-top:1px solid #f1f5f9; }
</style>
@endpush

@section('content')
<div class="fsi-page">
    <div class="fsi-head">
        <div>
            <h1 class="fsi-title">Form Submissions</h1>
            <p class="fsi-sub"><strong>{{ $form->name }}</strong> - {{ $submissions->total() }} total submission{{ $submissions->total() === 1 ? '' : 's' }}</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('analytics.forms') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left" style="margin-right:6px;"></i>Back to Form Analytics
            </a>
            <a href="{{ route('forms.show', $form->id) }}" class="btn btn-secondary">
                <i class="fas fa-eye" style="margin-right:6px;"></i>Form Preview
            </a>
        </div>
    </div>

    <div class="fsi-card">
        @forelse($submissions as $sub)
            @php
                $name = $sub->patient_name ?: optional($sub->user)->name ?: 'Anonymous';
                $email = $sub->patient_email ?: optional($sub->user)->email;
                $statusClass = $sub->status === 'completed' ? 'completed' : ($sub->status === 'draft' ? 'draft' : 'default');
            @endphp
            <div class="fsi-row">
                <div style="min-width:0;">
                    <div class="fsi-name">{{ $name }}</div>
                    @if($email)
                        <div class="fsi-email">{{ $email }}</div>
                    @endif
                </div>
                <div class="fsi-meta">
                    <span class="fsi-badge {{ $statusClass }}">{{ ucfirst($sub->status ?? 'submitted') }}</span>
                    <span>{{ optional($sub->created_at)->format('M d, Y g:i A') }}</span>
                    <a href="{{ route('forms.submissions.show', [$form->id, $sub->id]) }}" style="font-weight:700;color:#2563eb;">View</a>
                </div>
            </div>
        @empty
            <div class="fsi-empty">
                <div style="font-size:34px;margin-bottom:8px;"><i class="fas fa-inbox"></i></div>
                <div style="font-size:15px;font-weight:700;">No submissions found for this form.</div>
            </div>
        @endforelse

        @if($submissions->hasPages())
            <div class="fsi-footer">
                {{ $submissions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
