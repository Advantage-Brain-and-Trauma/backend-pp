@extends('layouts.app')

@section('title', 'Old Forms - AdvantageHCS Admin')
@section('page-title', 'Old Forms')
@section('page-subtitle', 'View all old form submissions')

@section('content')
<style>
.pagination-wrap {
    padding: 16px 20px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pagination-info {
    font-size: 13px;
    color: #6b7280;
}
.pagination-buttons {
    display: flex;
    align-items: center;
    gap: 4px;
}
.pagination-buttons button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}
.pagination-buttons button:hover:not(:disabled) {
    background: #f9fafb;
    border-color: #d1d5db;
}
.pagination-buttons button.active {
    background: #C8102E;
    color: #fff;
    border-color: #C8102E;
}
.pagination-buttons button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.table-toolbar {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #f3f4f6;
}
.table-toolbar .search-box {
    position: relative;
    flex: 1;
    max-width: 320px;
}
.table-toolbar .search-box input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #374151;
    outline: none;
    transition: border-color 0.15s;
}
.table-toolbar .search-box input:focus {
    border-color: #C8102E;
}
.table-toolbar .search-box .search-icon {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 13px;
}
.table-toolbar .per-page-select {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #6b7280;
}
.table-toolbar .per-page-select select {
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #374151;
    outline: none;
    cursor: pointer;
    background: #fff;
}
.table-toolbar .per-page-select select:focus {
    border-color: #C8102E;
}
</style>

<div class="card">
    <div class="card-header">
        <div class="card-title">Old Forms</div>
    </div>
    <div class="table-toolbar" id="table-toolbar" style="display:none;">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="search-input" placeholder="Search by title..." oninput="onSearch()">
        </div>
        <div class="per-page-select">
            <label for="per-page">Show</label>
            <select id="per-page" onchange="onPerPageChange()">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span>entries</span>
        </div>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th style="width:100px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="old-forms-tbody">
                <tr id="loading-row">
                    <td colspan="4" style="text-align:center; padding:48px; color:#9ca3af;">
                        <i class="fas fa-spinner fa-spin" style="font-size:24px; display:block; margin-bottom:12px;"></i>
                        Loading old forms...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap" id="pagination-wrap" style="display:none;">
        <div class="pagination-info" id="pagination-info"></div>
        <div class="pagination-buttons" id="pagination-buttons"></div>
    </div>
</div>

<script>
var allForms = [];
var filteredForms = [];
var currentPage = 1;
var perPage = 10;
var searchQuery = '';

document.addEventListener('DOMContentLoaded', function() {
    fetch('/old-forms/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    })
    .then(function(response) { return response.json(); })
    .then(function(res) {
        if (res.status && res.data && res.data.length > 0) {
            allForms = res.data;
            filteredForms = allForms;
            currentPage = 1;
            document.getElementById('table-toolbar').style.display = 'flex';
            renderPage();
        } else {
            var tbody = document.getElementById('old-forms-tbody');
            tbody.innerHTML =
                '<tr><td colspan="4" style="text-align:center; padding:48px; color:#9ca3af;">' +
                '<i class="fas fa-file-alt" style="font-size:36px; display:block; margin-bottom:12px;"></i>' +
                'No old forms found.</td></tr>';
        }
    })
    .catch(function(err) {
        var tbody = document.getElementById('old-forms-tbody');
        tbody.innerHTML =
            '<tr><td colspan="4" style="text-align:center; padding:48px; color:#dc2626;">' +
            '<i class="fas fa-exclamation-circle" style="font-size:36px; display:block; margin-bottom:12px;"></i>' +
            'Failed to load old forms. Please try again later.</td></tr>';
        console.error('Error fetching old forms:', err);
    });
});

function renderPage() {
    var totalPages = Math.ceil(filteredForms.length / perPage);
    var start = (currentPage - 1) * perPage;
    var end = start + perPage;
    var pageData = filteredForms.slice(start, end);

    var tbody = document.getElementById('old-forms-tbody');
    tbody.innerHTML = '';

    pageData.forEach(function(form) {
        var tr = document.createElement('tr');
        tr.setAttribute('id', 'form-row-' + form.id);
        tr.innerHTML =
            '<td style="font-weight:600;">' + escapeHtml(form.title || '—') + '</td>' +
            '<td style="font-size:13px;">' + formatStatus(form.is_active) + '</td>' +
            '<td style="font-size:13px; color:#6b7280;">' + formatDate(form.created_at) + '</td>' +
            '<td style="text-align:center;">' +
                '<button class="btn btn-primary btn-sm" onclick="syncForm(' + form.id + ', this)" title="Sync to new platform">' +
                    '<i class="fas fa-sync-alt"></i> Sync' +
                '</button>' +
            '</td>';
        tbody.appendChild(tr);
    });

    var paginationWrap = document.getElementById('pagination-wrap');
    var paginationInfo = document.getElementById('pagination-info');
    var paginationButtons = document.getElementById('pagination-buttons');

    if (filteredForms.length > perPage) {
        paginationWrap.style.display = 'flex';
        paginationInfo.textContent = 'Showing ' + (start + 1) + ' to ' + Math.min(end, filteredForms.length) + ' of ' + filteredForms.length + ' entries';

        var html = '';
        html += '<button ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="goToPage(' + (currentPage - 1) + ')"><i class="fas fa-chevron-left"></i></button>';

        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                html += '<button class="' + (i === currentPage ? 'active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</button>';
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                html += '<button disabled>...</button>';
            }
        }

        html += '<button ' + (currentPage === totalPages ? 'disabled' : '') + ' onclick="goToPage(' + (currentPage + 1) + ')"><i class="fas fa-chevron-right"></i></button>';
        paginationButtons.innerHTML = html;
    } else {
        paginationWrap.style.display = 'none';
    }
}

function onSearch() {
    searchQuery = document.getElementById('search-input').value.toLowerCase().trim();
    filteredForms = allForms.filter(function(form) {
        var title = (form.title || '').toLowerCase();
        return title.indexOf(searchQuery) !== -1;
    });
    currentPage = 1;
    renderPage();
}

function onPerPageChange() {
    perPage = parseInt(document.getElementById('per-page').value, 10);
    currentPage = 1;
    renderPage();
}

function goToPage(page) {
    var totalPages = Math.ceil(filteredForms.length / perPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderPage();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function syncForm(formId, btn) {
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    fetch('/old-forms/' + formId + '/sync', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    })
    .then(function(response) { return response.json(); })
    .then(function(res) {
        if (res.status) {
            showGlobalToast(res.message, 'success');
            // Remove synced form from lists and re-render
            allForms = allForms.filter(function(f) { return f.id !== formId; });
            filteredForms = filteredForms.filter(function(f) { return f.id !== formId; });
            var totalPages = Math.ceil(filteredForms.length / perPage);
            if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
            renderPage();
        } else {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showGlobalToast(res.error || res.message || 'Sync failed.', 'error');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showGlobalToast('Failed to sync form. Please try again.', 'error');
        console.error('Sync error:', err);
    });
}

function formatStatus(isActive) {
    if (isActive === 1 || isActive === '1') {
        return '<span style="color:#16a34a; font-weight:600;">ON</span>';
    } else if (isActive === 0 || isActive === '0') {
        return '<span style="color:#dc2626; font-weight:600;">OFF</span>';
    }
    return '<span style="color:#6b7280;">N/A</span>';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) return escapeHtml(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>
@endsection
