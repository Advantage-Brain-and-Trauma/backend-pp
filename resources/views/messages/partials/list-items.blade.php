{{-- Partial: messages list items (returned for AJAX live search) --}}
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
    No messages found
</div>
@endforelse
