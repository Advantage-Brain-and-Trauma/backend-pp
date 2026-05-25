@extends('layouts.app')

@section('title', 'Old Users - AdvantageHCS Admin')
@section('page-title', 'Old Users')
@section('page-subtitle', 'Users not available in new patient portal')

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
        <div class="card-title">Old Users</div>
    </div>
    <div class="table-toolbar" id="table-toolbar" style="display:none;">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="search-input" placeholder="Search by name, email, role..." oninput="onSearch()">
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
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role / Type</th>
                    <th>Status</th>
                    <th style="width:110px; text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody id="old-users-tbody">
                <tr>
                    <td colspan="5" style="text-align:center; padding:48px; color:#9ca3af;">
                        <i class="fas fa-spinner fa-spin" style="font-size:24px; display:block; margin-bottom:12px;"></i>
                        Loading old users...
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
var oldUsers = [];
var filteredUsers = [];
var currentPage = 1;
var perPage = 10;
var searchQuery = '';

document.addEventListener('DOMContentLoaded', function() {
    fetch('/old-users/list', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(res) {
        if (res.status && Array.isArray(res.data) && res.data.length > 0) {
            oldUsers = res.data;
            filteredUsers = oldUsers;
            currentPage = 1;
            document.getElementById('table-toolbar').style.display = 'flex';
            renderPage();
        } else {
            renderEmptyState('No old users found.');
        }
    })
    .catch(function() {
        renderEmptyState('Failed to load old users. Please try again.');
    });
});

function renderPage() {
    var totalPages = Math.ceil(filteredUsers.length / perPage);
    var start = (currentPage - 1) * perPage;
    var end = start + perPage;
    var pageData = filteredUsers.slice(start, end);

    var tbody = document.getElementById('old-users-tbody');
    tbody.innerHTML = '';

    pageData.forEach(function(user) {
        var tr = document.createElement('tr');
        tr.id = 'old-user-row-' + user.id;
        tr.innerHTML =
            '<td style="font-weight:600;">' + escapeHtml(user.name || 'Unknown') + '</td>' +
            '<td>' + escapeHtml(user.email || '-') + '</td>' +
            '<td><span class="badge badge-secondary">' + escapeHtml(user.role || user.type || 'User') + '</span></td>' +
            '<td>' + formatStatus(user) + '</td>' +
            '<td style="text-align:center;">' +
                '<button class="btn btn-primary btn-sm" onclick="syncOldUser(' + user.id + ', this)">' +
                    '<i class="fas fa-sync-alt"></i> Sync' +
                '</button>' +
            '</td>';
        tbody.appendChild(tr);
    });

    var paginationWrap = document.getElementById('pagination-wrap');
    var paginationInfo = document.getElementById('pagination-info');
    var paginationButtons = document.getElementById('pagination-buttons');

    if (filteredUsers.length > perPage) {
        paginationWrap.style.display = 'flex';
        paginationInfo.textContent = 'Showing ' + (start + 1) + ' to ' + Math.min(end, filteredUsers.length) + ' of ' + filteredUsers.length + ' entries';

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
    filteredUsers = oldUsers.filter(function(user) {
        var name = (user.name || '').toLowerCase();
        var email = (user.email || '').toLowerCase();
        var role = (user.role || user.type || '').toLowerCase();
        return name.indexOf(searchQuery) !== -1 || email.indexOf(searchQuery) !== -1 || role.indexOf(searchQuery) !== -1;
    });
    currentPage = 1;
    if (filteredUsers.length === 0) {
        renderEmptyState('No old users found.');
        return;
    }
    renderPage();
}

function onPerPageChange() {
    perPage = parseInt(document.getElementById('per-page').value, 10);
    currentPage = 1;
    if (filteredUsers.length === 0) {
        renderEmptyState('No old users found.');
        return;
    }
    renderPage();
}

function goToPage(page) {
    var totalPages = Math.ceil(filteredUsers.length / perPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderPage();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function syncOldUser(userId, btn) {
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';

    fetch('/old-users/' + userId + '/sync', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(res) {
        if (res.status) {
            oldUsers = oldUsers.filter(function(item) { return item.id !== userId; });
            filteredUsers = oldUsers.filter(function(user) {
                var name = (user.name || '').toLowerCase();
                var email = (user.email || '').toLowerCase();
                var role = (user.role || user.type || '').toLowerCase();
                return name.indexOf(searchQuery) !== -1 || email.indexOf(searchQuery) !== -1 || role.indexOf(searchQuery) !== -1;
            });

            if (filteredUsers.length === 0 && oldUsers.length > 0 && searchQuery !== '') {
                renderEmptyState('No users match your search.');
            } else if (oldUsers.length === 0) {
                renderEmptyState('No old users found.');
            } else {
                var totalPages = Math.ceil(filteredUsers.length / perPage);
                if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
                renderPage();
            }
            showGlobalToast(res.message || 'User synced successfully.', 'success');
        } else {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showGlobalToast(res.message || 'Sync failed.', 'error');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showGlobalToast('Failed to sync user. Please try again.', 'error');
    });
}

function renderEmptyState(message) {
    var tbody = document.getElementById('old-users-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:48px; color:#9ca3af;"><i class="fas fa-user-slash" style="font-size:36px; display:block; margin-bottom:12px;"></i>' + escapeHtml(message) + '</td></tr>';
    document.getElementById('pagination-wrap').style.display = 'none';
}

function formatStatus(user) {
    var active = user.is_active;
    if (active === null || active === undefined) active = user.active_status;
    if (active === 1 || active === '1') return '<span class="badge badge-success">Active</span>';
    if (active === 0 || active === '0') return '<span class="badge badge-danger">Inactive</span>';
    return '<span class="badge badge-secondary">N/A</span>';
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}
</script>
@endsection
