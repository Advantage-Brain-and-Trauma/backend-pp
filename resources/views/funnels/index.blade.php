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

{{-- session toasts handled by global toast in app.blade.php --}}

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
              {{-- Send to Patient --}}
              @if($funnel->slug && $funnel->status === 'active')
              <button
                onclick="openSendModal({{ $funnel->id }}, '{{ addslashes($funnel->name) }}', '{{ route('funnels.send_to_patient', $funnel) }}')"
                title="Send to Patient"
                style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#22c55e;color:#fff;border:none;cursor:pointer;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
              </button>
              @else
              <span title="Publish funnel to send to patients"
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
              <button type="button" title="Delete Funnel"
                onclick="openDeleteModal({{ $funnel->id }}, '{{ addslashes($funnel->name) }}')"
                style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;background:#ef4444;color:#fff;border:none;cursor:pointer;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
              {{-- Hidden delete form --}}
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

<!-- ─── Send to Patient Modal ──────────────────────────────────────────────── -->
<div id="sendPatientModal" style="display:none;position:fixed;inset:0;z-index:9100;align-items:center;justify-content:center;">
  <!-- Backdrop -->
  <div onclick="closeSendModal()" style="position:absolute;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(2px);"></div>
  <!-- Dialog -->
  <div style="position:relative;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.18);padding:28px 28px 24px;width:100%;max-width:460px;margin:0 16px;z-index:1;">

    <!-- Header -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
      <div style="width:40px;height:40px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="20" height="20" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      </div>
      <div>
        <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 2px;">Send Funnel to Patient</h3>
        <p id="sendModalFunnelName" style="font-size:13px;color:#6b7280;margin:0;"></p>
      </div>
      <button onclick="closeSendModal()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Search Input -->
    <div style="position:relative;margin-bottom:10px;">
      <input id="patientSearchInput" type="text" placeholder="Search patient by name or email..."
        oninput="searchPatients(this.value)"
        style="width:100%;padding:10px 14px 10px 38px;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;color:#374151;background:#f9fafb;outline:none;box-sizing:border-box;">
      <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;" width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <!-- Patient List -->
    <div id="patientListContainer" style="max-height:220px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:9px;background:#fff;">
      <div id="patientListInner" style="padding:8px 0;">
        <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">Type to search patients...</div>
      </div>
    </div>

    <!-- Error / Success Message -->
    <div id="sendModalMsg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:500;"></div>

    <!-- Copied URL row (shown after success) -->
    <div id="sendModalUrlRow" style="display:none;margin-top:10px;">
      <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
        <input id="sendModalUrlInput" type="text" readonly
          style="flex:1;border:none;background:transparent;font-size:12px;color:#166534;font-family:monospace;outline:none;min-width:0;">
        <button onclick="copySendUrl()" style="flex-shrink:0;padding:5px 12px;background:#16a34a;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Copy</button>
      </div>
    </div>

    <!-- Footer Buttons -->
    <div style="display:flex;gap:10px;margin-top:20px;">
      <button onclick="closeSendModal()" style="flex:1;padding:10px 0;border-radius:9px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:14px;font-weight:500;cursor:pointer;">
        Cancel
      </button>
      <button id="sendModalBtn" onclick="confirmSendToPatient()" disabled
        style="flex:1;padding:10px 0;border-radius:9px;border:none;background:#22c55e;color:#fff;font-size:14px;font-weight:600;cursor:not-allowed;opacity:0.6;">
        Assign &amp; Copy URL
      </button>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteFunnelModal" style="display:none;position:fixed;inset:0;z-index:9000;align-items:center;justify-content:center;">
  <!-- Backdrop -->
  <div onclick="closeDeleteModal()" style="position:absolute;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(2px);"></div>
  <!-- Dialog -->
  <div style="position:relative;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.18);padding:32px 28px;width:100%;max-width:420px;margin:0 16px;z-index:1;">
    <!-- Icon -->
    <div style="width:52px;height:52px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
      <svg width="24" height="24" fill="none" stroke="#ef4444" stroke-width="2.5" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
      </svg>
    </div>
    <!-- Title -->
    <h3 style="text-align:center;font-size:18px;font-weight:700;color:#111827;margin:0 0 8px;">Delete Funnel</h3>
    <!-- Message -->
    <p style="text-align:center;font-size:14px;color:#6b7280;margin:0 0 6px;">Are you sure you want to delete</p>
    <p id="deleteFunnelName" style="text-align:center;font-size:14px;font-weight:600;color:#111827;margin:0 0 24px;word-break:break-word;"></p>
    <p style="text-align:center;font-size:13px;color:#9ca3af;margin:-16px 0 24px;">This action cannot be undone.</p>
    <!-- Buttons -->
    <div style="display:flex;gap:10px;">
      <button onclick="closeDeleteModal()" style="flex:1;padding:10px 0;border-radius:9px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:14px;font-weight:500;cursor:pointer;">
        Cancel
      </button>
      <button onclick="confirmDeleteFunnel()" style="flex:1;padding:10px 0;border-radius:9px;border:none;background:#ef4444;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">
        Delete
      </button>
    </div>
  </div>
</div>

<script>
// ─── Delete Modal ─────────────────────────────────────────────────────────────
var _deleteFunnelId = null;

function openDeleteModal(id, name) {
  _deleteFunnelId = id;
  document.getElementById('deleteFunnelName').textContent = '"' + name + '"';
  var modal = document.getElementById('deleteFunnelModal');
  modal.style.display = 'flex';
}

function closeDeleteModal() {
  _deleteFunnelId = null;
  document.getElementById('deleteFunnelModal').style.display = 'none';
}

function confirmDeleteFunnel() {
  if (_deleteFunnelId) {
    document.getElementById('delete-form-' + _deleteFunnelId).submit();
  }
}

// ─── Send to Patient Modal ────────────────────────────────────────────────────
var _sendFunnelId       = null;
var _sendFunnelName     = null;
var _sendActionUrl      = null;
var _selectedPatientId  = null;
var _searchTimeout      = null;

function openSendModal(funnelId, funnelName, actionUrl) {
  _sendFunnelId      = funnelId;
  _sendFunnelName    = funnelName;
  _sendActionUrl     = actionUrl;
  _selectedPatientId = null;

  document.getElementById('sendModalFunnelName').textContent = funnelName;
  document.getElementById('patientSearchInput').value = '';
  document.getElementById('patientListInner').innerHTML =
    '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">Type to search patients...</div>';
  document.getElementById('sendModalMsg').style.display = 'none';
  document.getElementById('sendModalUrlRow').style.display = 'none';

  var btn = document.getElementById('sendModalBtn');
  btn.disabled = true;
  btn.style.opacity = '0.6';
  btn.style.cursor  = 'not-allowed';
  btn.textContent   = 'Assign & Copy URL';

  document.getElementById('sendPatientModal').style.display = 'flex';
  setTimeout(function(){ document.getElementById('patientSearchInput').focus(); }, 100);
}

function closeSendModal() {
  _sendFunnelId      = null;
  _selectedPatientId = null;
  document.getElementById('sendPatientModal').style.display = 'none';
}

function searchPatients(q) {
  clearTimeout(_searchTimeout);
  _selectedPatientId = null;
  disableSendBtn();

  if (q.trim().length === 0) {
    document.getElementById('patientListInner').innerHTML =
      '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">Type to search patients...</div>';
    return;
  }

  document.getElementById('patientListInner').innerHTML =
    '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">Searching...</div>';

  _searchTimeout = setTimeout(function() {
    var url = '{{ route("funnels.search_patients") }}?q=' + encodeURIComponent(q) + '&funnel_id=' + _sendFunnelId;
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        renderPatientList(data.data || []);
      })
      .catch(function() {
        document.getElementById('patientListInner').innerHTML =
          '<div style="padding:20px;text-align:center;color:#ef4444;font-size:13px;">Failed to load patients.</div>';
      });
  }, 300);
}

function renderPatientList(patients) {
  var container = document.getElementById('patientListInner');
  if (!patients.length) {
    container.innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;">No patients found.</div>';
    return;
  }

  var html = '';
  patients.forEach(function(p) {
    var assigned = p.already_assigned;
    html += '<div id="patient-row-' + p.id + '" onclick="' + (assigned ? '' : 'selectPatient(' + p.id + ', \'' + escapeJs(p.name) + '\')') + '"'
      + ' style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:' + (assigned ? 'default' : 'pointer') + ';'
      + 'opacity:' + (assigned ? '0.55' : '1') + ';'
      + 'transition:background 0.1s;"'
      + ' onmouseover="' + (assigned ? '' : 'this.style.background=\'#f9fafb\'') + '"'
      + ' onmouseout="this.style.background=\'\'">  '
      + '<div style="width:34px;height:34px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#4f46e5;flex-shrink:0;">'
      + p.name.charAt(0).toUpperCase()
      + '</div>'
      + '<div style="flex:1;min-width:0;">'
      + '<div style="font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(p.name) + '</div>'
      + '<div style="font-size:11px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(p.email) + '</div>'
      + '</div>'
      + (assigned
          ? '<span style="font-size:11px;font-weight:600;color:#16a34a;background:#dcfce7;padding:2px 8px;border-radius:20px;white-space:nowrap;">Already assigned</span>'
          : '<div id="patient-check-' + p.id + '" style="width:18px;height:18px;border-radius:50%;border:2px solid #d1d5db;flex-shrink:0;"></div>')
      + '</div>';
  });

  container.innerHTML = html;
}

function selectPatient(id, name) {
  // Deselect previous
  if (_selectedPatientId) {
    var prev = document.getElementById('patient-check-' + _selectedPatientId);
    if (prev) {
      prev.style.background = '';
      prev.style.borderColor = '#d1d5db';
    }
    var prevRow = document.getElementById('patient-row-' + _selectedPatientId);
    if (prevRow) prevRow.style.background = '';
  }

  _selectedPatientId = id;

  var check = document.getElementById('patient-check-' + id);
  if (check) {
    check.style.background = '#22c55e';
    check.style.borderColor = '#22c55e';
  }
  var row = document.getElementById('patient-row-' + id);
  if (row) row.style.background = '#f0fdf4';

  // Enable send button
  var btn = document.getElementById('sendModalBtn');
  btn.disabled = false;
  btn.style.opacity = '1';
  btn.style.cursor  = 'pointer';

  // Clear any previous messages
  document.getElementById('sendModalMsg').style.display = 'none';
  document.getElementById('sendModalUrlRow').style.display = 'none';
}

function disableSendBtn() {
  var btn = document.getElementById('sendModalBtn');
  btn.disabled = true;
  btn.style.opacity = '0.6';
  btn.style.cursor  = 'not-allowed';
}

function confirmSendToPatient() {
  if (!_selectedPatientId || !_sendActionUrl) return;

  var btn = document.getElementById('sendModalBtn');
  btn.disabled = true;
  btn.style.opacity = '0.7';
  btn.textContent = 'Assigning...';

  var csrfToken = document.querySelector('meta[name="csrf-token"]')
    ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    : '{{ csrf_token() }}';

  fetch(_sendActionUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ user_id: _selectedPatientId }),
  })
  .then(function(r) { return r.json().then(function(d){ return { ok: r.ok, data: d }; }); })
  .then(function(res) {
    var msgEl = document.getElementById('sendModalMsg');
    if (res.ok && res.data.status) {
      // Success
      msgEl.style.display = 'block';
      msgEl.style.background = '#f0fdf4';
      msgEl.style.border = '1px solid #bbf7d0';
      msgEl.style.color = '#166534';
      msgEl.textContent = '✅ ' + res.data.message;

      // Show URL row
      document.getElementById('sendModalUrlInput').value = res.data.url;
      document.getElementById('sendModalUrlRow').style.display = 'block';

      // Auto-copy
      navigator.clipboard.writeText(res.data.url).catch(function(){});

      btn.textContent = 'Assigned!';
      btn.style.background = '#16a34a';

      // Mark patient as assigned in the list
      var check = document.getElementById('patient-check-' + _selectedPatientId);
      if (check) check.parentElement.innerHTML =
        '<span style="font-size:11px;font-weight:600;color:#16a34a;background:#dcfce7;padding:2px 8px;border-radius:20px;white-space:nowrap;">Already assigned</span>';

      _selectedPatientId = null;
    } else {
      // Error (e.g. already assigned)
      msgEl.style.display = 'block';
      msgEl.style.background = '#fef2f2';
      msgEl.style.border = '1px solid #fecaca';
      msgEl.style.color = '#991b1b';
      msgEl.textContent = '⚠️ ' + (res.data.message || 'An error occurred.');

      btn.disabled = false;
      btn.style.opacity = '1';
      btn.style.cursor = 'pointer';
      btn.textContent = 'Assign & Copy URL';
    }
  })
  .catch(function() {
    var msgEl = document.getElementById('sendModalMsg');
    msgEl.style.display = 'block';
    msgEl.style.background = '#fef2f2';
    msgEl.style.border = '1px solid #fecaca';
    msgEl.style.color = '#991b1b';
    msgEl.textContent = '⚠️ Network error. Please try again.';

    btn.disabled = false;
    btn.style.opacity = '1';
    btn.style.cursor = 'pointer';
    btn.textContent = 'Assign & Copy URL';
  });
}

function copySendUrl() {
  var input = document.getElementById('sendModalUrlInput');
  navigator.clipboard.writeText(input.value).then(function() {
    var btn = event.target;
    btn.textContent = 'Copied!';
    setTimeout(function(){ btn.textContent = 'Copy'; }, 2000);
  }).catch(function(){
    prompt('Copy this URL:', input.value);
  });
}

function escapeHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escapeJs(str) {
  return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}
</script>
@endsection
