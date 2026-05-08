@extends('layouts.app')

@section('title', 'Old Forms - AdvantageHCS Admin')
@section('page-title', 'Old Forms')
@section('page-subtitle', 'View all old form submissions')

@section('content')
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
</div>

<script>
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
        var tbody = document.getElementById('old-forms-tbody');
        tbody.innerHTML = '';

        if (res.status && res.data && res.data.length > 0) {
            res.data.forEach(function(form) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td style="font-weight:600;">' + escapeHtml(form.title || '—') + '</td>' +
                    '<td style="font-size:13px; color:#6b7280;">' + escapeHtml(form.description || '—') + '</td>' +
                    '<td style="font-size:13px; color:#6b7280;">' + escapeHtml(form.email || '—') + '</td>';
                tbody.appendChild(tr);
            });
        } else {
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

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>
@endsection
