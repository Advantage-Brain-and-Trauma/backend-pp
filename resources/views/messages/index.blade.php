@extends('layouts.app')

@section('title', 'Messages - AdvantageHCS Admin')
@section('page-title', 'Messages')
@section('page-subtitle', 'Secure messaging center')

@section('header-actions')
    <a href="{{ route('messages.create') }}" class="btn btn-primary">
        <i class="fas fa-pen"></i> New Message
    </a>
@endsection

@section('content')
<div style="display:grid; grid-template-columns:340px 1fr; gap:0; background:white; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden; min-height:600px;">

    <!-- Message List -->
    <div style="border-right:1px solid #e5e7eb; overflow-y:auto;">
        <div style="padding:16px; border-bottom:1px solid #e5e7eb;">
            <form id="messages-filter-form" method="GET" action="{{ route('messages.index') }}" style="margin:0;">
                <div class="search-input-wrap" style="margin:0;">
                    <i class="fas fa-search"></i>
                    <input type="text" id="messages-search-input" name="search" value="{{ $search ?? '' }}" placeholder="Search messages..." style="border:1px solid #e5e7eb; border-radius:8px; padding:9px 9px 9px 36px; width:100%; font-size:13px; outline:none;">
                </div>
            </form>
        </div>

        @forelse($messages as $message)
        <a href="{{ route('messages.show', $message) }}" style="display:block; padding:16px; border-bottom:1px solid #f3f4f6; text-decoration:none; {{ !$message->is_read && $message->sender_type === 'patient' ? 'background:#fef2f2;' : 'background:white;' }} transition:background 0.15s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='{{ !$message->is_read && $message->sender_type === 'patient' ? '#fef2f2' : 'white' }}'">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <div style="width:36px; height:36px; background:#e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; color:#374151; flex-shrink:0;">
                    {{ strtoupper(substr($message->sender_name ?? 'P', 0, 2)) }}
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:{{ !$message->is_read && $message->sender_type === 'patient' ? '700' : '500' }}; font-size:14px; color:#1a1a2e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ $message->sender_name ?? 'Unknown' }}
                    </div>
                    <div style="font-size:11px; color:#9ca3af;">{{ $message->created_at->diffForHumans() }}</div>
                </div>
                @if(!$message->is_read && $message->sender_type === 'patient')
                    <div style="width:8px; height:8px; background:#C8102E; border-radius:50%; flex-shrink:0;"></div>
                @endif
            </div>
            <div style="font-weight:500; font-size:13px; color:#374151; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $message->subject }}</div>
            <div style="font-size:12px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ Str::limit($message->body, 60) }}</div>
            @if($message->category)
                <div style="margin-top:6px;">
                    <span style="font-size:11px; background:#eff6ff; color:#3b82f6; padding:2px 8px; border-radius:4px;">{{ ucfirst($message->category) }}</span>
                </div>
            @endif
        </a>
        @empty
        <div style="padding:48px; text-align:center; color:#9ca3af;">
            <i class="fas fa-envelope" style="font-size:32px; display:block; margin-bottom:12px;"></i>
            No messages yet
        </div>
        @endforelse
    </div>

    <!-- Empty State / Select Message -->
    <div style="display:flex; align-items:center; justify-content:center; background:#f9fafb;">
        <div style="text-align:center; color:#9ca3af;">
            <i class="fas fa-envelope-open" style="font-size:48px; display:block; margin-bottom:16px;"></i>
            <div style="font-size:16px; font-weight:500; margin-bottom:8px;">Select a message</div>
            <div style="font-size:14px;">Choose a conversation from the left to view it</div>
        </div>
    </div>
</div>
<script>
(function() {
    var inp = document.getElementById('messages-search-input');
    if (!inp) return;
    var timer;
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); }
    });
    document.getElementById('messages-filter-form').addEventListener('submit', function(e) {
        if (document.activeElement === inp) { e.preventDefault(); }
    });
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() {
            document.getElementById('messages-filter-form').submit();
        }, 300);
    });
})();
</script>
@endsection
