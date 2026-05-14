@extends('layouts.app')

@section('title', 'Forms - AdvantageHCS Admin')
@section('page-title', 'Forms')
@section('page-subtitle', 'Manage patient intake forms and documents')

@section('header-actions')
    <a href="{{ route('forms.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create Form
    </a>
@endsection

@section('content')
<style>
/* ── Status Toggle ─────────────────────────────────────────── */
.status-toggle-wrap {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.status-toggle-track {
    position: relative;
    width: 42px;
    height: 24px;
    border-radius: 24px;
    background: #d1d5db;
    transition: background .25s;
    flex-shrink: 0;
}
.status-toggle-track.active { background: #C8102E; }
.status-toggle-knob {
    position: absolute;
    top: 3px; left: 3px;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.25);
    transition: left .25s;
}
.status-toggle-track.active .status-toggle-knob { left: 21px; }

/* ── Delete Confirmation Modal ─────────────────────────────── */
#delete-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 99998;
    align-items: center;
    justify-content: center;
}
#delete-modal-overlay.open { display: flex; }
#delete-modal {
    background: #fff;
    border-radius: 14px;
    padding: 32px 28px 24px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    text-align: center;
    animation: modalIn .2s ease;
}
@keyframes modalIn { from { transform: scale(.94); opacity: 0; } to { transform: scale(1); opacity: 1; } }
#delete-modal .modal-icon {
    width: 56px; height: 56px;
    background: #fef2f2;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
#delete-modal .modal-icon i { font-size: 24px; color: #dc2626; }
#delete-modal h3 { font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px; }
#delete-modal p { font-size: 14px; color: #6b7280; margin-bottom: 24px; line-height: 1.5; }
#delete-modal .modal-actions { display: flex; gap: 12px; justify-content: center; }
#delete-modal .btn-cancel-modal {
    flex: 1; padding: 10px 20px; border-radius: 8px;
    border: 1px solid #e5e7eb; background: #fff; color: #374151;
    font-size: 14px; font-weight: 600; cursor: pointer; transition: background .15s;
}
#delete-modal .btn-cancel-modal:hover { background: #f9fafb; }
#delete-modal .btn-confirm-delete {
    flex: 1; padding: 10px 20px; border-radius: 8px;
    border: none; background: #dc2626; color: #fff;
    font-size: 14px; font-weight: 600; cursor: pointer; transition: background .15s;
}
#delete-modal .btn-confirm-delete:hover { background: #b91c1c; }

/* ── Bulk Select ───────────────────────────────────────────── */
.row-checkbox {
    width: 16px; height: 16px; cursor: pointer;
    accent-color: #C8102E;
}
#bulk-action-bar {
    display: none;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    background: #fef2f2;
    border-bottom: 1px solid #fecaca;
    font-size: 13px;
    color: #374151;
}
#bulk-action-bar.visible { display: flex; }
#bulk-delete-btn {
    padding: 7px 16px;
    border-radius: 7px;
    border: none;
    background: #C8102E;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: background .15s;
}
#bulk-delete-btn:hover { background: #a50e25; }
#bulk-delete-btn:disabled { opacity: .6; cursor: not-allowed; }

/* ── Sortable Columns ──────────────────────────────────────── */
.sort-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: inherit;
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
}
.sort-link:hover { color: #C8102E; }
.sort-arrows { display: inline-flex; flex-direction: column; line-height: 1; gap: 1px; }
.sort-arrows .arr { font-size: 9px; color: #d1d5db; }
.sort-arrows .arr.active { color: #C8102E; }
</style>

<div class="card" style="padding:0;overflow:hidden;">
    {{-- Toolbar --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb;gap:12px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;">
            <form method="GET" action="{{ route('forms.index') }}" style="display:flex;align-items:center;gap:8px;">
                <select name="per_page" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;background:#f9fafb;color:#374151;cursor:pointer;">
                    @foreach([10,25,50,100] as $n)
                        <option value="{{ $n }}" {{ request('per_page', 10) == $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </select>
                <span style="font-size:13px;color:#6b7280;">Entries Per Page</span>
                @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            </form>
        </div>
        <form id="forms-filter-form" method="GET" action="{{ route('forms.index') }}" style="display:flex;gap:8px;align-items:center;">
            @if(request('per_page')) <input type="hidden" name="per_page" value="{{ request('per_page') }}"> @endif
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <select name="status" id="forms-status-select" onchange="document.getElementById('forms-filter-form').submit()" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;background:#f9fafb;color:#374151;outline:none;">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <input type="text" id="forms-search-input" name="search" value="{{ request('search') }}" placeholder="Search..."
                style="padding:8px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;background:#f9fafb;color:#374151;width:220px;outline:none;">
            <button type="submit" style="padding:8px 16px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;background:#f9fafb;color:#374151;cursor:pointer;"><i class="fas fa-search"></i></button>
        </form>
    </div>
    {{-- Bulk Action Bar --}}
    <div id="bulk-action-bar">
        <span id="bulk-selected-count">0 selected</span>
        <button id="bulk-delete-btn" onclick="openBulkDeleteModal()">
            <i class="fas fa-trash"></i> Delete Selected
        </button>
        <button onclick="clearSelection()" style="padding:7px 14px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;cursor:pointer;">Clear</button>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;text-align:center;">
                        <input type="checkbox" id="select-all-checkbox" class="row-checkbox" title="Select all" onchange="toggleSelectAll(this)">
                    </th>
@php
                        function formsSortUrl($col, $currentSort, $currentDir) {
                            $dir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
                            return request()->fullUrlWithQuery(['sort' => $col, 'direction' => $dir, 'page' => 1]);
                        }
                        function formsSortArrows($col, $currentSort, $currentDir) {
                            return '<span class="sort-arrows">'
                                . '<span class="arr' . ($currentSort===$col && $currentDir==='asc' ? ' active' : '') . '">&#9650;</span>'
                                . '<span class="arr' . ($currentSort===$col && $currentDir==='desc' ? ' active' : '') . '">&#9660;</span>'
                                . '</span>';
                        }
                    @endphp
                    <th><a href="{{ formsSortUrl('name', $currentSort, $currentDir) }}" class="sort-link">Form Name {!! formsSortArrows('name', $currentSort, $currentDir) !!}</a></th>
                    <th style="width:90px; text-align:center;"><a href="{{ formsSortUrl('status', $currentSort, $currentDir) }}" class="sort-link">Status {!! formsSortArrows('status', $currentSort, $currentDir) !!}</a></th>
                    <th><a href="{{ formsSortUrl('submissions', $currentSort, $currentDir) }}" class="sort-link">Submissions {!! formsSortArrows('submissions', $currentSort, $currentDir) !!}</a></th>
                    <th><a href="{{ formsSortUrl('created_by', $currentSort, $currentDir) }}" class="sort-link">Created By {!! formsSortArrows('created_by', $currentSort, $currentDir) !!}</a></th>
                    <th><a href="{{ formsSortUrl('created_at', $currentSort, $currentDir) }}" class="sort-link">Created {!! formsSortArrows('created_at', $currentSort, $currentDir) !!}</a></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($forms as $form)
                <tr id="form-row-{{ $form->id }}">
                    <td style="text-align:center;">
                        <input type="checkbox" class="row-checkbox form-checkbox" value="{{ $form->id }}" onchange="onRowCheckboxChange()">
                    </td>
                    <td>
                        <div style="font-weight:600;">{{ $form->name }}</div>
                        @if($form->description)
                            <div style="font-size:12px; color:#6b7280;">{{ Str::limit($form->description, 60) }}</div>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <span class="status-toggle-wrap"
                              onclick="toggleFormStatus({{ $form->id }}, this)"
                              title="{{ $form->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}">
                            <span class="status-toggle-track {{ $form->is_active ? 'active' : '' }}">
                                <span class="status-toggle-knob"></span>
                            </span>
                        </span>
                    </td>
                    <td style="font-size:13px; color:#6b7280;">{{ $form->submissions_count ?? 0 }}</td>
                    <td style="font-size:13px; color:#6b7280;">{{ $form->creator->name ?? '—' }}</td>
                    <td style="font-size:12px; color:#6b7280;">{{ $form->created_at->format('M d, Y') }}</td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <a href="{{ route('forms.show', $form) }}" class="btn btn-secondary btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <button type="button" class="btn btn-secondary btn-sm" title="Duplicate"
                                onclick="openDuplicateModal({{ $form->id }}, '{{ addslashes($form->name) }}')"
                                style="background:#f59e0b; border-color:#f59e0b; color:#fff;">
                                <i class="fas fa-copy"></i>
                            </button>
                            <a href="{{ route('forms.edit', $form) }}" class="btn btn-secondary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                            <form id="delete-form-{{ $form->id }}" method="POST" action="{{ route('forms.destroy', $form) }}" style="display:none;">
                                @csrf @method('DELETE')
                            </form>
                            <form id="duplicate-form-{{ $form->id }}" method="POST" action="{{ route('forms.duplicate', $form) }}" style="display:none;">
                                @csrf
                            </form>
                            <button type="button"
                                class="btn btn-danger btn-sm"
                                onclick="openDeleteModal({{ $form->id }}, '{{ addslashes($form->name) }}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:48px; color:#9ca3af;">
                        <i class="fas fa-wpforms" style="font-size:36px; display:block; margin-bottom:12px;"></i>
                        No forms yet. <a href="{{ route('forms.create') }}" style="color:#C8102E;">Create your first form</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{-- Footer / Pagination --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #e5e7eb;flex-wrap:wrap;gap:10px;">
        <div id="pagination-footer-text" style="font-size:13px;color:#6b7280;">
            Showing {{ $forms->firstItem() ?? 0 }} to {{ $forms->lastItem() ?? 0 }} of {{ $forms->total() }} results
        </div>
        <div style="display:flex;gap:4px;align-items:center;">
            @if($forms->onFirstPage())
                <span style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#f9fafb;color:#d1d5db;font-size:13px;">&#8249;</span>
            @else
                <a href="{{ $forms->previousPageUrl() }}" style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;text-decoration:none;">&#8249;</a>
            @endif
            @foreach($forms->getUrlRange(max(1, $forms->currentPage()-2), min($forms->lastPage(), $forms->currentPage()+2)) as $page => $url)
                @if($page == $forms->currentPage())
                    <span style="padding:6px 12px;border-radius:7px;border:1px solid #C8102E;background:#C8102E;color:#fff;font-size:13px;font-weight:600;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;text-decoration:none;">{{ $page }}</a>
                @endif
            @endforeach
            @if($forms->hasMorePages())
                <a href="{{ $forms->nextPageUrl() }}" style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#fff;color:#374151;font-size:13px;text-decoration:none;">&#8250;</a>
            @else
                <span style="padding:6px 12px;border-radius:7px;border:1px solid #e5e7eb;background:#f9fafb;color:#d1d5db;font-size:13px;">&#8250;</span>
            @endif
        </div>
    </div>
</div>

{{-- Duplicate Confirmation Modal --}}
<div id="duplicate-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:99998; align-items:center; justify-content:center;">
    <div id="duplicate-modal" style="background:#fff; border-radius:14px; padding:32px 28px 24px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.2); text-align:center; animation:modalIn .2s ease;">
        <div style="width:56px; height:56px; background:#fffbeb; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
            <i class="fas fa-copy" style="font-size:24px; color:#f59e0b;"></i>
        </div>
        <h3 style="font-size:18px; font-weight:700; color:#111827; margin-bottom:8px;">Duplicate Form</h3>
        <p id="duplicate-modal-msg" style="font-size:14px; color:#6b7280; margin-bottom:24px; line-height:1.5;"></p>
        <div style="display:flex; gap:12px; justify-content:center;">
            <button onclick="closeDuplicateModal()" style="flex:1; padding:10px 20px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:14px; font-weight:600; cursor:pointer;">Cancel</button>
            <button onclick="confirmDuplicate()" style="flex:1; padding:10px 20px; border-radius:8px; border:none; background:#f59e0b; color:#fff; font-size:14px; font-weight:600; cursor:pointer;">Duplicate</button>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="delete-modal-overlay">
    <div id="delete-modal">
        <div class="modal-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3>Delete Form</h3>
        <p id="delete-modal-msg">Are you sure you want to delete this form? This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn-cancel-modal" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-confirm-delete" onclick="confirmDelete()">Delete</button>
        </div>
    </div>
</div>

<script>
// ── Duplicate Modal ───────────────────────────────────────────
var _duplicateFormId = null;

function openDuplicateModal(formId, formName) {
    _duplicateFormId = formId;
    document.getElementById('duplicate-modal-msg').textContent =
        'A copy of "' + formName + '" will be created and placed directly below it.';
    var overlay = document.getElementById('duplicate-modal-overlay');
    overlay.style.display = 'flex';
}

function closeDuplicateModal() {
    _duplicateFormId = null;
    document.getElementById('duplicate-modal-overlay').style.display = 'none';
}

function confirmDuplicate() {
    if (_duplicateFormId) {
        document.getElementById('duplicate-form-' + _duplicateFormId).submit();
    }
}

document.getElementById('duplicate-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeDuplicateModal();
});

// ── Delete Modal ──────────────────────────────────────────────
var _deleteFormId = null;

function openDeleteModal(formId, formName) {
    _deleteFormId = formId;
    document.getElementById('delete-modal-msg').textContent =
        'Are you sure you want to delete "' + formName + '"? This action cannot be undone.';
    document.getElementById('delete-modal-overlay').classList.add('open');
}

function closeDeleteModal() {
    _deleteFormId = null;
    document.getElementById('delete-modal-overlay').classList.remove('open');
}

function updateFooterCount(removedCount) {
    var footer = document.querySelector('#pagination-footer-text');
    if (!footer) {
        // fallback: find by content
        document.querySelectorAll('div').forEach(function(el) {
            if (/Showing \d+ to \d+ of \d+/.test(el.textContent) && el.children.length === 0) footer = el;
        });
    }
    if (!footer) return;
    var text = footer.textContent.trim();
    var match = text.match(/(\d+) to (\d+) of (\d+)/);
    if (match) {
        var from = parseInt(match[1]);
        var to = Math.max(from - 1, parseInt(match[2]) - removedCount);
        var total = parseInt(match[3]) - removedCount;
        if (total <= 0) { footer.textContent = 'Showing 0 to 0 of 0 results'; return; }
        if (to < from) from = to;
        footer.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total + ' results';
    }
}

function removeRowsAnimated(ids, callback) {
    var pending = ids.length;
    if (pending === 0) { if (callback) callback(); return; }
    ids.forEach(function(id) {
        var row = document.getElementById('form-row-' + id);
        if (row) {
            row.style.transition = 'opacity 0.25s';
            row.style.opacity = '0';
            setTimeout(function() {
                if (row.parentNode) row.remove();
                pending--;
                if (pending === 0 && callback) callback();
            }, 260);
        } else {
            pending--;
            if (pending === 0 && callback) callback();
        }
    });
}

function confirmDelete() {
    if (!_deleteFormId) return;
    var btn = document.querySelector('#delete-modal .btn-confirm-delete');
    if (btn) { btn.disabled = true; btn.textContent = 'Deleting...'; }

    // ── Bulk delete ──
    if (_deleteFormId === '__bulk__') {
        var ids = getSelectedIds();
        if (ids.length === 0) { closeDeleteModal(); if (btn) { btn.disabled = false; btn.textContent = 'Delete'; } return; }
        var token = document.querySelector('meta[name="csrf-token"]').content;
        fetch('{{ route("forms.bulk-destroy") }}', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids }),
        })
        .then(function(r) { return r.json().catch(function() { return { status: false }; }); })
        .then(function(res) {
            closeDeleteModal();
            if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
            if (!res.status) { showGlobalToast(res.message || 'Failed to delete.', 'error'); return; }
            removeRowsAnimated(ids, function() {
                updateFooterCount(ids.length);
                clearSelection();
            });
            showGlobalToast(res.message || (ids.length + ' form(s) deleted.'), 'success');
        })
        .catch(function() {
            closeDeleteModal();
            if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
            showGlobalToast('Failed to delete forms.', 'error');
        });
        return;
    }

    // ── Single delete ──
    var formId = _deleteFormId;
    var deleteForm = document.getElementById('delete-form-' + formId);
    if (!deleteForm) return;
    var url = deleteForm.action;
    var token = deleteForm.querySelector('input[name="_token"]').value;
    fetch(url, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(function(r) { return r.json().catch(function() { return { status: false }; }); })
    .then(function(res) {
        closeDeleteModal();
        if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
        if (res.status === false && res.message) { showGlobalToast(res.message || 'Failed to delete.', 'error'); return; }
        removeRowsAnimated([formId], function() { updateFooterCount(1); });
        showGlobalToast('Form deleted successfully.', 'success');
    })
    .catch(function() {
        closeDeleteModal();
        if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
        showGlobalToast('Failed to delete form.', 'error');
    });
}

document.getElementById('delete-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

// ── Bulk Select ──────────────────────────────────────────────
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.form-checkbox:checked')).map(function(cb) { return cb.value; });
}

function updateBulkBar() {
    var ids = getSelectedIds();
    var bar = document.getElementById('bulk-action-bar');
    var countEl = document.getElementById('bulk-selected-count');
    if (ids.length > 0) {
        bar.classList.add('visible');
        countEl.textContent = ids.length + ' selected';
    } else {
        bar.classList.remove('visible');
    }
    // Sync select-all state
    var all = document.querySelectorAll('.form-checkbox');
    var selectAll = document.getElementById('select-all-checkbox');
    if (selectAll) {
        selectAll.checked = all.length > 0 && ids.length === all.length;
        selectAll.indeterminate = ids.length > 0 && ids.length < all.length;
    }
}

function onRowCheckboxChange() { updateBulkBar(); }

function toggleSelectAll(cb) {
    document.querySelectorAll('.form-checkbox').forEach(function(el) { el.checked = cb.checked; });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.form-checkbox').forEach(function(el) { el.checked = false; });
    var sa = document.getElementById('select-all-checkbox');
    if (sa) { sa.checked = false; sa.indeterminate = false; }
    updateBulkBar();
}

function openBulkDeleteModal() {
    var ids = getSelectedIds();
    if (ids.length === 0) return;
    document.getElementById('delete-modal-msg').textContent =
        'Are you sure you want to delete ' + ids.length + ' selected form(s)? This action cannot be undone.';
    // Store bulk mode flag
    _deleteFormId = '__bulk__';
    document.getElementById('delete-modal-overlay').classList.add('open');
}

// ── Status Toggle ─────────────────────────────────────────────
function toggleFormStatus(formId, wrapEl) {
    var track = wrapEl.querySelector('.status-toggle-track');
    var wasActive = track.classList.contains('active');

    // Optimistic UI update
    track.classList.toggle('active');
    wrapEl.title = wasActive ? 'Inactive — click to activate' : 'Active — click to deactivate';

    fetch('/forms/' + formId + '/toggle-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.status === 'success') {
            showGlobalToast(res.message, 'success');
        } else {
            // Revert on failure
            track.classList.toggle('active');
            wrapEl.title = wasActive ? 'Active — click to deactivate' : 'Inactive — click to activate';
            showGlobalToast('Failed to update status.', 'error');
        }
    })
    .catch(function() {
        track.classList.toggle('active');
        wrapEl.title = wasActive ? 'Active — click to deactivate' : 'Inactive — click to activate';
        showGlobalToast('Failed to update status.', 'error');
    });
}

// Live search — submit form after user stops typing (300ms debounce)
(function() {
    var inp = document.getElementById('forms-search-input');
    if (!inp) return;
    var timer;
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() {
            document.getElementById('forms-filter-form').submit();
        }, 300);
    });
})();
</script>
@endsection
