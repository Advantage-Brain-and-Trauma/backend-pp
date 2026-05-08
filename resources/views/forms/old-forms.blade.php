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
</style>

<div class="card">
    <div class="card-header">
        <div class="card-title">Old Forms</div>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody id="old-forms-tbody">
                <tr id="loading-row">
                    <td colspan="3" style="text-align:center; padding:48px; color:#9ca3af;">
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
var currentPage = 1;
var perPage = 10;

document.addEventListener('DOMContentLoaded', function() {
    fetch('/api/get-all-old-forms', {
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
            currentPage = 1;
            renderPage();
        } else {
            var tbody = document.getElementById('old-forms-tbody');
            tbody.innerHTML =
                '<tr><td colspan="3" style="text-align:center; padding:48px; color:#9ca3af;">' +
                '<i class="fas fa-file-alt" style="font-size:36px; display:block; margin-bottom:12px;"></i>' +
                'No old forms found.</td></tr>';
        }
    })
    .catch(function(err) {
        var tbody = document.getElementById('old-forms-tbody');
        tbody.innerHTML =
            '<tr><td colspan="3" style="text-align:center; padding:48px; color:#dc2626;">' +
            '<i class="fas fa-exclamation-circle" style="font-size:36px; display:block; margin-bottom:12px;"></i>' +
            'Failed to load old forms. Please try again later.</td></tr>';
        console.error('Error fetching old forms:', err);
    });
});

function renderPage() {
    var totalPages = Math.ceil(allForms.length / perPage);
    var start = (currentPage - 1) * perPage;
    var end = start + perPage;
    var pageData = allForms.slice(start, end);

    var tbody = document.getElementById('old-forms-tbody');
    tbody.innerHTML = '';

    pageData.forEach(function(form) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="font-weight:600;">' + escapeHtml(form.title || '—') + '</td>' +
            '<td style="font-size:13px; color:#6b7280;">' + escapeHtml(form.description || '—') + '</td>' +
            '<td style="font-size:13px; color:#6b7280;">' + escapeHtml(form.email || '—') + '</td>';
        tbody.appendChild(tr);
    });

    var paginationWrap = document.getElementById('pagination-wrap');
    var paginationInfo = document.getElementById('pagination-info');
    var paginationButtons = document.getElementById('pagination-buttons');

    if (allForms.length > perPage) {
        paginationWrap.style.display = 'flex';
        paginationInfo.textContent = 'Showing ' + (start + 1) + ' to ' + Math.min(end, allForms.length) + ' of ' + allForms.length + ' entries';

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

function goToPage(page) {
    var totalPages = Math.ceil(allForms.length / perPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderPage();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>
@endsection
