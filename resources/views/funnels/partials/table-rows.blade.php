{{-- Partial: funnels table rows (returned for AJAX live search) --}}
@forelse($funnels as $funnel)
<tr style="border-bottom:1px solid #f3f4f6;transition:background 0.1s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
  <td style="padding:14px 20px;font-size:14px;color:#6b7280;">{{ $loop->iteration + ($funnels->currentPage() - 1) * $funnels->perPage() }}</td>
  <td style="padding:14px 20px;">
    <div style="font-size:14px;font-weight:500;color:#111827;">{{ $funnel->name }}</div>
    @if($funnel->slug)
    <div style="font-size:11px;color:#6366f1;margin-top:2px;font-family:monospace;">/funnel/{{ $funnel->slug }}</div>
    @endif
  </td>
  <td style="padding:14px 20px;font-size:13px;color:#6b7280;">{{ $funnel->created_at->format('M d, Y g:i A') }}</td>
  <td style="padding:14px 20px;text-align:right;">
    <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;">
      @if($funnel->slug && $funnel->status === 'active')
      <button onclick="copyFunnelUrl('{{ url('/funnel/' . $funnel->slug) }}')" title="Copy Public URL"
        style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#22c55e;color:#fff;border:none;cursor:pointer;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      </button>
      @else
      <span title="Publish funnel to get public URL"
        style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#d1fae5;color:#6ee7b7;cursor:not-allowed;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      </span>
      @endif
      <a href="{{ route('funnels.edit', $funnel) }}" title="Edit Funnel"
        style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#6366f1;color:#fff;text-decoration:none;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </a>
      <button type="button" title="Delete Funnel"
        onclick="openDeleteModal({{ $funnel->id }}, '{{ addslashes($funnel->name) }}')"
        style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#ef4444;color:#fff;border:none;cursor:pointer;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      </button>
      <form id="delete-form-{{ $funnel->id }}" method="POST" action="{{ route('funnels.destroy', $funnel) }}" style="display:none;">
        @csrf @method('DELETE')
      </form>
    </div>
  </td>
</tr>
@empty
<tr>
  <td colspan="4" style="padding:60px 20px;text-align:center;color:#9ca3af;">
    <div style="font-size:40px;margin-bottom:12px;">🔗</div>
    <div style="font-size:16px;font-weight:600;color:#374151;margin-bottom:6px;">No funnels found</div>
    <div style="font-size:13px;margin-bottom:16px;">Try a different search term.</div>
  </td>
</tr>
@endforelse
