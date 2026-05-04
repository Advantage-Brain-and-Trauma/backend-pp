@extends('layouts.app')

@section('title', 'Funnels - AdvantageHCS Admin')
@section('page-title', 'Funnel')
@section('page-subtitle', 'Create multi-step form sequences and generate patient links')

@section('header-actions')
    <a href="{{ route('funnels.create') }}" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Create
    </a>
@endsection

@section('content')

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

<div class="card" style="padding:0;overflow:hidden;">

  <!-- Toolbar -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb;gap:12px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;">
      <form method="GET" action="{{ route('funnels.index') }}" style="display:flex;align-items:center;gap:8px;">
        <select name="per_page" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;background:#f9fafb;color:#374151;cursor:pointer;">
          @foreach([10,25,50,100] as $n)
            <option value="{{ $n }}" {{ request('per_page', 10) == $n ? 'selected' : '' }}>{{ $n }}</option>
          @endforeach
        </select>
        <span style="font-size:13px;color:#6b7280;">Entries Per Page</span>
        @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
      </form>

    </div>
    <form method="GET" action="{{ route('funnels.index') }}" style="display:flex;gap:8px;align-items:center;">
      @if(request('per_page')) <input type="hidden" name="per_page" value="{{ request('per_page') }}"> @endif
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
        style="padding:8px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;background:#f9fafb;color:#374151;width:220px;outline:none;">
    </form>
  </div>

  <!-- Table -->
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
          <th style="padding:12px 20px;text-align:left;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;width:60px;">NO</th>
          <th style="padding:12px 20px;text-align:left;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;">
            TITLE
            <svg style="display:inline;vertical-align:middle;margin-left:4px;" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
          </th>
          <th style="padding:12px 20px;text-align:left;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;width:180px;">CREATED AT</th>
          <th style="padding:12px 20px;text-align:right;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;width:140px;">ACTION</th>
        </tr>
      </thead>
      <tbody>
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
              {{-- View / Copy URL --}}
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
              {{-- Edit --}}
              <a href="{{ route('funnels.edit', $funnel) }}" title="Edit Funnel"
                style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#6366f1;color:#fff;text-decoration:none;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              {{-- Delete --}}
              <form method="POST" action="{{ route('funnels.destroy', $funnel) }}" onsubmit="return confirm('Delete this funnel? This cannot be undone.')" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" title="Delete Funnel"
                  style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#ef4444;color:#fff;border:none;cursor:pointer;">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="4" style="padding:60px 20px;text-align:center;color:#9ca3af;">
            <div style="font-size:40px;margin-bottom:12px;">🔗</div>
            <div style="font-size:16px;font-weight:600;color:#374151;margin-bottom:6px;">No funnels yet</div>
            <div style="font-size:13px;margin-bottom:16px;">Create your first funnel to group forms and share with patients.</div>

          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <!-- Footer / Pagination -->
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #e5e7eb;flex-wrap:wrap;gap:10px;">
    <div style="font-size:13px;color:#6b7280;">
      Showing {{ $funnels->firstItem() ?? 0 }} to {{ $funnels->lastItem() ?? 0 }} of {{ $funnels->total() }} entries
    </div>
    <div style="display:flex;gap:4px;align-items:center;">
      @if($funnels->onFirstPage())
        <span style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#f9fafb;color:#d1d5db;font-size:13px;">‹</span>
      @else
        <a href="{{ $funnels->previousPageUrl() }}" style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;text-decoration:none;">‹</a>
      @endif
      @foreach($funnels->getUrlRange(max(1, $funnels->currentPage()-2), min($funnels->lastPage(), $funnels->currentPage()+2)) as $page => $url)
        @if($page == $funnels->currentPage())
          <span style="padding:6px 12px;border-radius:7px;border:1px solid #6366f1;background:#6366f1;color:#fff;font-size:13px;font-weight:600;">{{ $page }}</span>
        @else
          <a href="{{ $url }}" style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;text-decoration:none;">{{ $page }}</a>
        @endif
      @endforeach
      @if($funnels->hasMorePages())
        <a href="{{ $funnels->nextPageUrl() }}" style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;text-decoration:none;">›</a>
      @else
        <span style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#f9fafb;color:#d1d5db;font-size:13px;">›</span>
      @endif
    </div>
  </div>
</div>

<!-- Copy URL Toast -->
<div id="copyToast" style="display:none;position:fixed;bottom:24px;right:24px;background:#111827;color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);z-index:9999;">
  ✅ Funnel URL copied to clipboard!
</div>

<script>
function copyFunnelUrl(url) {
  navigator.clipboard.writeText(url).then(() => {
    const toast = document.getElementById('copyToast');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
  }).catch(() => {
    prompt('Copy this URL:', url);
  });
}
</script>
@endsection
