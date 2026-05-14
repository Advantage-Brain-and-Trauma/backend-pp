@extends('layouts.app')

@section('page-title', 'Users')
@section('page-subtitle', 'Manage and view all user accounts')

{{-- @section('header-actions')
    <button type="button" onclick="openCreateModal()" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create User
    </button>
@endsection --}}

@section('content')
<style>
/* ── Toast ─────────────────────────────────────────────── */
#toast-container {
    position: fixed; top: 20px; right: 20px; z-index: 99999;
    display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast {
    display: flex; align-items: center; gap: 10px; padding: 14px 20px;
    border-radius: 10px; font-size: 14px; font-weight: 500; color: #fff;
    min-width: 280px; max-width: 380px; box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    pointer-events: all; animation: slideIn .3s ease;
}
.toast.success { background: #16a34a; }
.toast.error   { background: #dc2626; }
@keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
.toast.hide { animation: slideOut .3s ease forwards; }

/* ── Custom Searchable Select ──────────────────────────── */
.cs-wrap { position: relative; width: 100%; }
.cs-display {
    width: 100%; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 9px 36px 9px 12px; font-size: 14px; color: #374151;
    background: #fff; cursor: pointer; box-sizing: border-box;
    display: flex; align-items: center; justify-content: space-between;
    user-select: none; min-height: 40px;
}
.cs-display:after { content: '▾'; color: #9ca3af; font-size: 12px; margin-left: 8px; flex-shrink: 0; }
.cs-dropdown {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12); z-index: 999999; max-height: 260px; overflow: hidden;
}
.cs-dropdown.open { display: block; }
.cs-search { padding: 8px; border-bottom: 1px solid #f3f4f6; }
.cs-search input {
    width: 100%; border: 1px solid #e5e7eb; border-radius: 6px;
    padding: 7px 10px; font-size: 13px; box-sizing: border-box; outline: none;
}
.cs-list { max-height: 200px; overflow-y: auto; }
.cs-option { padding: 9px 14px; font-size: 14px; cursor: pointer; color: #374151; }
.cs-option:hover, .cs-option.selected { background: #C8102E; color: #fff; }
.cs-hidden { display: none !important; }

/* ── Toggle Switch ─────────────────────────────────────── */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-track {
    position: absolute; inset: 0; border-radius: 24px; transition: .3s;
}
.toggle-knob {
    position: absolute; height: 18px; width: 18px; bottom: 3px;
    border-radius: 50%; background: #fff; transition: .3s; display: block;
}
</style>

{{-- Toast container --}}
<div id="toast-container"></div>

{{-- Hidden flash messages for JS toast only --}}
@if(session('success'))
<span id="flash-success" data-msg="{{ session('success') }}" style="display:none;"></span>
@endif
@if(session('error'))
<span id="flash-error" data-msg="{{ session('error') }}" style="display:none;"></span>
@endif

<div class="card">
    {{-- Card Header: per-page + search --}}
    <div class="card-header">
        <form method="GET" action="{{ route('user-management.index') }}" id="perPageForm" style="display:flex; align-items:center; gap:8px; margin-bottom:0;">
            <input type="hidden" name="search" value="{{ $search }}">
            <select name="per_page" onchange="document.getElementById('perPageForm').submit()" class="form-control" style="width:80px; padding:8px 12px;">
                @foreach([10, 25, 50, 100] as $opt)
                    <option value="{{ $opt }}" {{ $perPage == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                @endforeach
            </select>
            <span style="font-size:14px; color:#6b7280; white-space:nowrap;">Entries Per Page</span>
        </form>
        <form id="users-filter-form" method="GET" action="{{ route('user-management.index') }}" style="display:flex; align-items:center; gap:8px; margin-bottom:0;">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="search-input-wrap" style="width:240px;">
                <i class="fas fa-search"></i>
                <input type="text" id="users-search-input" name="search" value="{{ $search }}" placeholder="Search...">
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="table-container">
        <table>
            @php
            function usersSortUrl($col, $cs, $cd) {
                $dir = ($cs === $col && $cd === 'asc') ? 'desc' : 'asc';
                return request()->fullUrlWithQuery(['sort' => $col, 'direction' => $dir, 'page' => 1]);
            }
            function usersSortArrows($col, $cs, $cd) {
                $up   = '<span style="font-size:9px;color:' . ($cs===$col && $cd==='asc'  ? '#C8102E' : '#d1d5db') . ';">&#9650;</span>';
                $down = '<span style="font-size:9px;color:' . ($cs===$col && $cd==='desc' ? '#C8102E' : '#d1d5db') . ';">&#9660;</span>';
                return '<span style="display:inline-flex;flex-direction:column;line-height:1;gap:1px;margin-left:4px;">' . $up . $down . '</span>';
            }
            @endphp
            <thead>
                <tr>
                    <th><a href="{{ usersSortUrl('name', $currentSort, $currentDir) }}" style="display:inline-flex;align-items:center;color:inherit;text-decoration:none;font-weight:inherit;">Name {!! usersSortArrows('name', $currentSort, $currentDir) !!}</a></th>
                    <th><a href="{{ usersSortUrl('email', $currentSort, $currentDir) }}" style="display:inline-flex;align-items:center;color:inherit;text-decoration:none;font-weight:inherit;">Email {!! usersSortArrows('email', $currentSort, $currentDir) !!}</a></th>
                    <th><a href="{{ usersSortUrl('role', $currentSort, $currentDir) }}" style="display:inline-flex;align-items:center;color:inherit;text-decoration:none;font-weight:inherit;">Role {!! usersSortArrows('role', $currentSort, $currentDir) !!}</a></th>
                    <th>Status</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody id="users-table-body">
                @forelse($users as $user)
                <tr id="user-row-{{ $user->id }}">
                    <td style="font-weight:600; color:#1a1a2e;">{{ $user->name }}</td>
                    <td style="color:#374151;">{{ $user->email }}</td>
                    <td>
                        @php
                            $roleRaw   = strtolower($user->role ?? 'user');
                            $roleLabel = match($roleRaw) {
                                'admin'       => 'Admin',
                                'super_admin' => 'Super Admin',
                                'user'        => 'User',
                                default       => ucwords(str_replace('_', ' ', $roleRaw)),
                            };
                            $badgeClass = in_array($roleRaw, ['admin','super_admin']) ? 'badge-info' : 'badge-secondary';
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $roleLabel }}</span>
                    </td>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" {{ $user->is_active ? 'checked' : '' }}
                                onchange="toggleStatus({{ $user->id }}, this)">
                            <span class="toggle-track" data-user="{{ $user->id }}"
                                style="background:{{ $user->is_active ? '#C8102E' : '#d1d5db' }};">
                                <span class="toggle-knob toggle-knob-{{ $user->id }}"
                                    style="left:{{ $user->is_active ? '23px' : '3px' }};"></span>
                            </span>
                        </label>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <div style="display:flex; gap:8px; justify-content:center;">
                            <button type="button"
                                data-id="{{ $user->id }}"
                                data-name="{{ addslashes($user->name) }}"
                                data-email="{{ $user->email }}"
                                data-role="{{ $user->role }}"
                                data-phone="{{ $user->phone }}"
                                data-country="{{ $user->country_code }}"
                                data-patient="{{ $user->patient_id }}"
                                onclick="openEditModalFromBtn(this)"
                                class="btn btn-secondary btn-sm" title="View User">
                                <i class="fas fa-eye"></i>
                            </button>
                            @if($user->id !== auth()->id())
                            <button type="button" onclick="deleteUser({{ $user->id }})"
                                class="btn btn-danger btn-sm" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            @else
                            <button disabled class="btn btn-danger btn-sm" style="opacity:0.4; cursor:not-allowed;" title="Cannot delete yourself">
                                <i class="fas fa-trash"></i>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:48px; color:#9ca3af;">
                        <i class="fas fa-users" style="font-size:36px; display:block; margin-bottom:12px;"></i>
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div id="users-pagination-wrap">
    @if($users->hasPages())
    <div class="pagination" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div id="users-footer-text" style="font-size:13px; color:#6b7280;">
            Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} results
        </div>
        <div>{{ $users->onEachSide(2)->links('vendor.pagination.custom') }}</div>
    </div>
    @else
    <div id="users-footer-text" style="font-size:13px; color:#6b7280; padding:14px 20px;">
        Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} results
    </div>
    @endif
    </div>
</div>

{{-- Country codes data for JS --}}
<script>
var COUNTRY_CODES = [
    @foreach($countryCodes as $cc)
    { code: "{{ $cc['code'] }}", name: "{{ $cc['name'] }}" },
    @endforeach
];
</script>

{{-- ===== CREATE MODAL ===== --}}
<div id="createModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:10000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; padding:28px; box-shadow:0 8px 32px rgba(0,0,0,0.18); margin:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <h2 style="margin:0; font-size:18px; font-weight:700; color:#111827;">Create User</h2>
            <button type="button" onclick="closeCreateModal()" style="background:none; border:none; cursor:pointer; color:#6b7280; font-size:24px; line-height:1; padding:0;">×</button>
        </div>
        <form method="POST" action="{{ route('user-management.store') }}" id="createForm">
            @csrf
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" required placeholder="Full name" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" required placeholder="email@example.com" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="createPassword" required placeholder="Password" class="form-control">
                <p style="font-size:12px; color:#6b7280; margin:6px 0 0;">Must be 8+ characters with uppercase, lowercase, number and special character.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" id="createPasswordConfirm" placeholder="Confirm password" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Country Code</label>
                <input type="hidden" name="country_code" id="createCountryCodeVal">
                <div class="cs-wrap" id="createCsWrap">
                    <div class="cs-display" id="createCsDisplay" onclick="toggleCs('create')">Select country code</div>
                    <div class="cs-dropdown" id="createCsDropdown">
                        <div class="cs-search"><input type="text" placeholder="Search country..." oninput="filterCs('create', this.value)" id="createCsSearch"></div>
                        <div class="cs-list" id="createCsList"></div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" placeholder="Enter phone number" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" id="createRole" onchange="toggleCreatePatientId(this.value)" class="form-control">
                    <option value="admin">Admin</option>
                    <option value="user" selected>User (Patient)</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div id="createPatientIdWrap" class="form-group" style="display:block;">
                <label class="form-label">Patient ID</label>
                <input type="text" name="patient_id" id="createPatientId" placeholder="Enter patient ID" class="form-control">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- ===== EDIT MODAL ===== --}}
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:10000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; padding:28px; box-shadow:0 8px 32px rgba(0,0,0,0.18); margin:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <h2 style="margin:0; font-size:18px; font-weight:700; color:#111827;">View User</h2>
            <button type="button" onclick="closeEditModal()" style="background:none; border:none; cursor:pointer; color:#6b7280; font-size:24px; line-height:1; padding:0;">×</button>
        </div>
        <form method="POST" id="editForm" action="">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="editName" required class="form-control" disabled style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="editEmail" required class="form-control" disabled style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
            </div>
            {{-- Password fields hidden --}}
            <div class="form-group" style="display:none;">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="editPassword" placeholder="Leave blank to keep current" class="form-control" style="background:#f9fafb;">
                <p style="font-size:12px; color:#6b7280; margin:6px 0 0;">Must be 8+ characters with uppercase, lowercase, number and special character.</p>
            </div>
            <div class="form-group" style="display:none;">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" id="editPasswordConfirm" placeholder="Confirm password" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Country Code</label>
                <input type="hidden" name="country_code" id="editCountryCodeVal">
                <div class="cs-wrap" id="editCsWrap" style="pointer-events:none; opacity:0.6;">
                    <div class="cs-display" id="editCsDisplay">Select country code</div>
                    <div class="cs-dropdown" id="editCsDropdown">
                        <div class="cs-search"><input type="text" placeholder="Search country..." oninput="filterCs('edit', this.value)" id="editCsSearch"></div>
                        <div class="cs-list" id="editCsList"></div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" id="editPhone" placeholder="Enter phone number" class="form-control" disabled style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" id="editRole" onchange="toggleEditPatientId(this.value)" class="form-control" disabled style="background:#f3f4f6; color:#6b7280; cursor:not-allowed;">
                    <option value="admin">Admin</option>
                    <option value="user">User (Patient)</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div id="editPatientIdWrap" class="form-group" style="display:none;">
                <label class="form-label">Patient ID</label>
                <input type="text" id="editPatientId" name="patient_id" disabled
                    class="form-control" style="background:#f3f4f6; color:#9ca3af; cursor:not-allowed;"
                    title="Patient ID cannot be changed here">
                <p style="font-size:12px; color:#9ca3af; margin:4px 0 0;">Patient ID is read-only. It can only be set when creating a new user.</p>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:8px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Close</button>
            </div>
        </form>
    </div>
</div>

{{-- ===== DELETE CONFIRMATION MODAL ===== --}}
<div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10001; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; width:100%; max-width:400px; padding:32px 28px; box-shadow:0 12px 40px rgba(0,0,0,0.2); margin:20px; text-align:center;">
        <div style="width:60px; height:60px; background:#fef2f2; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
            <i class="fas fa-trash" style="font-size:24px; color:#C8102E;"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#111827;">Delete User</h3>
        <p style="margin:0 0 24px; font-size:14px; color:#6b7280;">Are you sure you want to delete this user? This action cannot be undone.</p>
        <div style="display:flex; gap:12px; justify-content:center;">
            <button type="button" onclick="cancelDelete()" class="btn btn-secondary">Cancel</button>
            <button type="button" onclick="confirmDelete()" class="btn btn-danger" style="background:#C8102E; border-color:#C8102E; color:#fff;">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('toast-container');
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    container.appendChild(toast);
    setTimeout(function() {
        toast.classList.add('hide');
        setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
    }, 4000);
}

// ── Show flash toasts on page load ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    var s = document.getElementById('flash-success');
    var e = document.getElementById('flash-error');
    if (s) showToast(s.getAttribute('data-msg'), 'success');
    if (e) showToast(e.getAttribute('data-msg'), 'error');
    buildCsList('create');
    buildCsList('edit');
    toggleCreatePatientId(document.getElementById('createRole').value);
});

// ── Custom Searchable Select ──────────────────────────────────────────────────
var csSelected = { create: '', edit: '' };

function buildCsList(prefix, filter) {
    filter = (filter || '').toLowerCase();
    var list = document.getElementById(prefix + 'CsList');
    list.innerHTML = '';
    COUNTRY_CODES.forEach(function(cc) {
        var text = cc.code + ' ' + cc.name;
        if (filter && text.toLowerCase().indexOf(filter) === -1) return;
        var div = document.createElement('div');
        div.className = 'cs-option' + (csSelected[prefix] === cc.code ? ' selected' : '');
        div.textContent = text;
        div.setAttribute('data-code', cc.code);
        div.addEventListener('click', function() { selectCs(prefix, cc.code, text); });
        list.appendChild(div);
    });
}

function selectCs(prefix, code, text) {
    csSelected[prefix] = code;
    document.getElementById(prefix + 'CountryCodeVal').value = code;
    document.getElementById(prefix + 'CsDisplay').textContent = text || 'Select country code';
    document.getElementById(prefix + 'CsDropdown').classList.remove('open');
    buildCsList(prefix, document.getElementById(prefix + 'CsSearch').value);
}

function toggleCs(prefix) {
    var dd = document.getElementById(prefix + 'CsDropdown');
    var isOpen = dd.classList.contains('open');
    document.querySelectorAll('.cs-dropdown').forEach(function(d) { d.classList.remove('open'); });
    if (!isOpen) { dd.classList.add('open'); document.getElementById(prefix + 'CsSearch').focus(); }
}

function filterCs(prefix, val) { buildCsList(prefix, val); }

document.addEventListener('click', function(e) {
    if (!e.target.closest('.cs-wrap')) {
        document.querySelectorAll('.cs-dropdown').forEach(function(d) { d.classList.remove('open'); });
    }
});

// ── Patient ID toggle ─────────────────────────────────────────────────────────
function toggleCreatePatientId(role) {
    var wrap = document.getElementById('createPatientIdWrap');
    wrap.style.display = (role === 'user') ? 'block' : 'none';
    if (role !== 'user') document.getElementById('createPatientId').value = '';
}
function toggleEditPatientId(role) {
    document.getElementById('editPatientIdWrap').style.display = (role === 'user') ? 'block' : 'none';
}

// ── Toggle Status ─────────────────────────────────────────────────────────────
function toggleStatus(userId, checkbox) {
    fetch('/user-management/' + userId + '/toggle-status', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            var track = document.querySelector('[data-user="' + userId + '"]');
            var knob  = document.querySelector('.toggle-knob-' + userId);
            if (data.is_active) { track.style.background = '#C8102E'; knob.style.left = '23px'; }
            else                { track.style.background = '#d1d5db'; knob.style.left = '3px'; }
            showToast('User status updated.', 'success');
        } else {
            checkbox.checked = !checkbox.checked;
            showToast('Failed to update status.', 'error');
        }
    })
    .catch(function() { checkbox.checked = !checkbox.checked; showToast('Network error.', 'error'); });
}

// ── Delete User ───────────────────────────────────────────────────────────────
var _deleteUserId = null;
function deleteUser(userId) {
    _deleteUserId = userId;
    document.getElementById('deleteModal').style.display = 'flex';
}
function confirmDelete() {
    if (!_deleteUserId) return;
    document.getElementById('deleteModal').style.display = 'none';
    fetch('/user-management/' + _deleteUserId, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            var row = document.getElementById('user-row-' + _deleteUserId);
            if (row) row.remove();
            showToast('User deleted successfully.', 'success');
        } else {
            showToast(data.message || 'Could not delete user.', 'error');
        }
        _deleteUserId = null;
    })
    .catch(function() { showToast('Network error.', 'error'); _deleteUserId = null; });
}
function cancelDelete() {
    _deleteUserId = null;
    document.getElementById('deleteModal').style.display = 'none';
}

// ── Create Modal ──────────────────────────────────────────────────────────────
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    toggleCreatePatientId(document.getElementById('createRole').value);
}
function closeCreateModal() { document.getElementById('createModal').style.display = 'none'; }

// ── Edit Modal ────────────────────────────────────────────────────────────────
function openEditModalFromBtn(btn) {
    var id        = btn.getAttribute('data-id');
    var name      = btn.getAttribute('data-name');
    var email     = btn.getAttribute('data-email');
    var role      = btn.getAttribute('data-role') || '';
    var phone     = btn.getAttribute('data-phone') || '';
    var country   = btn.getAttribute('data-country') || '';
    var patientId = btn.getAttribute('data-patient') || '';

    document.getElementById('editName').value            = name;
    document.getElementById('editEmail').value           = email;
    document.getElementById('editPhone').value           = phone;
    document.getElementById('editPassword').value        = '';
    document.getElementById('editPasswordConfirm').value = '';
    document.getElementById('editPatientId').value       = patientId;

    var roleNorm = role.toLowerCase().trim().replace(/ /g, '_');
    var roleMap  = { 'admin': 'admin', 'user': 'user', 'super_admin': 'super_admin', 'super admin': 'super_admin' };
    var roleVal  = roleMap[roleNorm] || roleNorm || 'user';
    document.getElementById('editRole').value = roleVal;
    toggleEditPatientId(roleVal);

    var countryText = country || 'Select country code';
    if (country) {
        var found = COUNTRY_CODES.find(function(c) { return c.code === country; });
        if (found) countryText = found.code + ' ' + found.name;
    }
    csSelected['edit'] = country;
    document.getElementById('editCountryCodeVal').value = country;
    document.getElementById('editCsDisplay').textContent = countryText;
    buildCsList('edit');

    document.getElementById('editForm').action = '/user-management/' + id;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

// ── Password validation ───────────────────────────────────────────────────────
document.getElementById('editForm').addEventListener('submit', function(e) {
    var pw = document.getElementById('editPassword').value;
    var cpw = document.getElementById('editPasswordConfirm').value;
    if (pw && pw !== cpw) { e.preventDefault(); showToast('Passwords do not match.', 'error'); }
});
document.getElementById('createForm').addEventListener('submit', function(e) {
    var pw = document.getElementById('createPassword').value;
    var cpw = document.getElementById('createPasswordConfirm').value;
    if (pw && pw !== cpw) { e.preventDefault(); showToast('Passwords do not match.', 'error'); }
});

// ── Close on backdrop click ───────────────────────────────────────────────────
document.getElementById('createModal').addEventListener('click', function(e) { if (e.target === this) closeCreateModal(); });
document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });
document.getElementById('deleteModal').addEventListener('click', function(e) { if (e.target === this) cancelDelete(); });

// Live search — AJAX in-place (no page reload, cursor stays in input)
(function() {
    var inp    = document.getElementById('users-search-input');
    var form   = document.getElementById('users-filter-form');
    var tbody  = document.getElementById('users-table-body');
    var footer = document.getElementById('users-footer-text');
    if (!inp || !form || !tbody) return;

    var timer;

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
    });
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        doSearch();
    });
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(doSearch, 300);
    });

    function doSearch() {
        var params = new URLSearchParams();
        params.set('search', inp.value);
        var perPage = document.querySelector('[name="per_page"]');
        if (perPage) params.set('per_page', perPage.value);
        var url = new URL(window.location.href);
        if (url.searchParams.get('sort'))      params.set('sort',      url.searchParams.get('sort'));
        if (url.searchParams.get('direction')) params.set('direction', url.searchParams.get('direction'));

        fetch('{{ route('user-management.index') }}?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            tbody.innerHTML = data.rows;
            if (footer) footer.textContent = 'Showing ' + data.from + ' to ' + data.to + ' of ' + data.total + ' results';
            history.replaceState(null, '', '{{ route('user-management.index') }}?' + params.toString());
        })
        .catch(function(err) { console.error('Search error', err); });
    }
})();
</script>
@endsection
