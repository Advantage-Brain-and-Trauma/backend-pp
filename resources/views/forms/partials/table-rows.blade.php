{{-- Partial: forms table rows + pagination (returned for AJAX live search) --}}
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
    <td colspan="6" style="text-align:center; padding:48px; color:#9ca3af;">
        <i class="fas fa-wpforms" style="font-size:36px; display:block; margin-bottom:12px;"></i>
        No forms found. <a href="{{ route('forms.create') }}" style="color:#C8102E;">Create your first form</a>
    </td>
</tr>
@endforelse

