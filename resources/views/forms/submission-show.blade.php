@extends('layouts.app')

@section('title', $form->name . ' — Submission')

@push('styles')
<style>
.submission-page { display:flex; flex-direction:column; gap:22px; }
.submission-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.submission-title { font-size:26px; font-weight:800; color:#0f172a; margin:0; letter-spacing:-.4px; }
.submission-subtitle { margin:4px 0 0; font-size:13px; color:#64748b; }
.submission-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 1px 4px rgba(15,23,42,.04); overflow:hidden; }
.submission-card-body { padding:22px 24px; }
.submission-meta-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.submission-meta-item { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; }
.submission-meta-label { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
.submission-meta-value { font-size:14px; font-weight:700; color:#0f172a; word-break:break-word; }
.submission-status { display:inline-flex; align-items:center; padding:4px 11px; border-radius:999px; font-size:12px; font-weight:700; }
.submission-status.completed { background:#f0fdf4; color:#16a34a; }
.submission-status.draft { background:#fffbeb; color:#d97706; }
.submission-status.default { background:#eff6ff; color:#2563eb; }
.submission-form-shell { max-width:860px; margin:0 auto 28px; background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; box-shadow:0 8px 24px rgba(15,23,42,.06); }
.submission-form-title { background:linear-gradient(135deg,#991b1b,#b91c1c); color:#fff; padding:24px 30px; }
.submission-form-title h2 { margin:0; font-size:22px; font-weight:800; }
.submission-fields { padding:24px; background:#f8fafc; display:flex; flex-direction:column; gap:14px; }
.submission-field { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:16px 18px; }
.submission-field-label { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:800; color:#334155; margin-bottom:9px; }
.submission-required { color:#dc2626; }
.submission-field-value { min-height:42px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; padding:11px 13px; font-size:14px; line-height:1.55; color:#0f172a; white-space:pre-line; word-break:break-word; }
.submission-field-empty { color:#94a3b8; font-style:italic; }
.submission-files { display:flex; flex-direction:column; gap:10px; }
.submission-file-link { color:#2563eb; font-weight:700; text-decoration:none; }
.submission-file-link:hover { text-decoration:underline; }
.submission-image-preview { display:block; max-width:320px; max-height:180px; border-radius:10px; border:1px solid #e2e8f0; margin-top:8px; object-fit:contain; background:#fff; }
.submission-empty-state { text-align:center; padding:44px 20px; color:#94a3b8; }
@media (max-width: 900px) { .submission-meta-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width: 640px) { .submission-meta-grid { grid-template-columns:1fr; } .submission-card-body { padding:18px; } .submission-fields { padding:16px; } }
</style>
@endpush

@section('content')
@php
    $patientName = $submission->patient_name ?: optional($submission->user)->name;
    $patientEmail = $submission->patient_email ?: optional($submission->user)->email;
    if (!$patientName && is_array($submission->data)) {
        foreach ($submission->data as $value) {
            if (is_array($value) && (isset($value['first']) || isset($value['last']))) {
                $patientName = trim(($value['first'] ?? '') . ' ' . ($value['middle'] ?? '') . ' ' . ($value['last'] ?? ''));
                break;
            }
        }
    }
    $statusClass = $submission->status === 'completed' ? 'completed' : ($submission->status === 'draft' ? 'draft' : 'default');
@endphp

<div class="submission-page">
    <div class="submission-header">
        <div>
            <h1 class="submission-title">Patient Submission</h1>
            <p class="submission-subtitle">Submitted data for <strong>{{ $form->name }}</strong></p>
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

    <div class="submission-card">
        <div class="submission-card-body">
            <div class="submission-meta-grid">
                <div class="submission-meta-item">
                    <div class="submission-meta-label">Patient</div>
                    <div class="submission-meta-value">{{ $patientName ?: 'Anonymous' }}</div>
                </div>
                <div class="submission-meta-item">
                    <div class="submission-meta-label">Email</div>
                    <div class="submission-meta-value">{{ $patientEmail ?: '—' }}</div>
                </div>
                <div class="submission-meta-item">
                    <div class="submission-meta-label">Status</div>
                    <div class="submission-meta-value">
                        <span class="submission-status {{ $statusClass }}">{{ ucfirst($submission->status ?? 'submitted') }}</span>
                    </div>
                </div>
                <div class="submission-meta-item">
                    <div class="submission-meta-label">Submitted At</div>
                    <div class="submission-meta-value">{{ optional($submission->created_at)->format('M d, Y g:i A') ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="submission-form-shell">
        <div class="submission-form-title">
            <h2>{{ $form->name }}</h2>
        </div>

        <div class="submission-fields">
            @forelse($fields as $field)
                <div class="submission-field">
                    <div class="submission-field-label">
                        <span>{{ $field['label'] }}</span>
                        @if($field['required'])
                            <span class="submission-required">*</span>
                        @endif
                    </div>

                    @php($value = $field['value'])
                    @if(($value['kind'] ?? '') === 'files')
                        <div class="submission-field-value submission-files">
                            @forelse($value['files'] as $file)
                                <div>
                                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="submission-file-link">
                                        <i class="fas fa-paperclip" style="margin-right:6px;"></i>{{ $file['name'] }}
                                    </a>
                                    @if($file['is_image'])
                                        <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="submission-image-preview">
                                    @endif
                                </div>
                            @empty
                                <span class="submission-field-empty">—</span>
                            @endforelse
                        </div>
                    @elseif(($value['kind'] ?? '') === 'image')
                        <div class="submission-field-value">
                            <img src="{{ $value['url'] }}" alt="{{ $value['text'] ?? $field['label'] }}" class="submission-image-preview">
                        </div>
                    @else
                        <div class="submission-field-value {{ ($value['kind'] ?? '') === 'empty' ? 'submission-field-empty' : '' }}">{{ $value['text'] ?? '—' }}</div>
                    @endif
                </div>
            @empty
                <div class="submission-empty-state">
                    <div style="font-size:36px;margin-bottom:10px;"><i class="fas fa-inbox"></i></div>
                    <div style="font-size:15px;font-weight:700;color:#64748b;">No submitted field data found</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
