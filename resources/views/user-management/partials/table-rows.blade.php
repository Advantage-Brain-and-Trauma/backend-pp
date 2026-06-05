{{-- Partial: users table rows (returned for AJAX live search) --}}
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
                data-patient="{{ is_array($user->patient_id) ? implode(',', $user->patient_id) : $user->patient_id }}"
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
