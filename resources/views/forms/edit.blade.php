@extends('layouts.app')
@section('title', 'Edit Form - AdvantageHCS Admin')
@section('page-title', 'Edit Form')
@section('page-subtitle', 'Update form settings for: ' . $form->name)

@section('header-actions')
    <a href="{{ route('forms.show', $form) }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Builder
    </a>
@endsection

@section('content')
<style>
    /* Toggle switch */
    .af-toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; vertical-align: middle; }
    .af-toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .af-toggle-track { position: absolute; inset: 0; border-radius: 24px; transition: background .3s; }
    .af-toggle-knob  { position: absolute; height: 18px; width: 18px; bottom: 3px; border-radius: 50%; background: #fff; transition: left .3s; display: block; }

    /* Two-column form layout */
    .form-two-col { display: flex; gap: 24px; align-items: flex-start; }
    .form-two-col .col-left  { flex: 1 1 0; min-width: 0; }
    .form-two-col .col-right { width: 380px; flex-shrink: 0; }
    @media (max-width: 900px) {
        .form-two-col { flex-direction: column; }
        .form-two-col .col-right { width: 100%; }
    }

    /* Email Setting card */
    .email-setting-card { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
    .email-setting-card .es-header {
        background: #8B1A1A;
        color: #fff;
        font-size: 15px;
        font-weight: 600;
        padding: 14px 20px;
    }
    .email-setting-card .es-body { padding: 20px; background: #fff; }

    /* Tag input */
    .tag-input-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 6px 10px;
        min-height: 42px;
        background: #fff;
        cursor: text;
    }
    .tag-input-wrap:focus-within { border-color: #C8102E; box-shadow: 0 0 0 2px rgba(200,16,46,.12); }
    .tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #4B3F8A;
        color: #fff;
        border-radius: 4px;
        padding: 3px 8px;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
    }
    .tag-chip button {
        background: none;
        border: none;
        color: #fff;
        cursor: pointer;
        padding: 0;
        font-size: 14px;
        line-height: 1;
        opacity: .8;
    }
    .tag-chip button:hover { opacity: 1; }
    .tag-input-field {
        border: none;
        outline: none;
        flex: 1 1 80px;
        min-width: 80px;
        font-size: 13px;
        color: #374151;
        background: transparent;
        padding: 2px 0;
    }
    .tag-input-field::placeholder { color: #9ca3af; }
</style>

@php
    $currentAssignType = old('assign_type', $form->assign_type ?? 'role');
    $ccEmails  = [];
    $bccEmails = [];
    $ccRaw  = old('ccemail',  $form->ccemail  ?? '');
    $bccRaw = old('bccemail', $form->bccemail ?? '');
    if ($ccRaw)  { $decoded = json_decode($ccRaw, true);  if (is_array($decoded)) $ccEmails  = $decoded; }
    if ($bccRaw) { $decoded = json_decode($bccRaw, true); if (is_array($decoded)) $bccEmails = $decoded; }
@endphp

<form method="POST" action="{{ route('forms.update', $form) }}" id="editFormEl">
@csrf
@method('PUT')

<input type="hidden" name="assign_type" id="assign_type_hidden" value="{{ $currentAssignType }}">
<input type="hidden" name="ccemail"  id="ccemail_hidden"  value="{{ old('ccemail',  $form->ccemail  ?? '') }}">
<input type="hidden" name="bccemail" id="bccemail_hidden" value="{{ old('bccemail', $form->bccemail ?? '') }}">

<div class="form-two-col">

    {{-- LEFT: Form Details card --}}
    <div class="col-left">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Form Details</span>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Form Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', $form->name) }}"
                           placeholder="Enter form name..." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Enter form description...">{{ old('description', $form->description) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Success Message</label>
                    <textarea name="success_msg" class="form-control" rows="3"
                              placeholder="Message shown after successful form submission...">{{ old('success_msg', $form->success_msg) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Thanks Message</label>
                    <textarea name="thanks_msg" class="form-control" rows="3"
                              placeholder="Message shown on the thank-you page...">{{ old('thanks_msg', $form->thanks_msg) }}</textarea>
                </div>

                {{-- Assign Form --}}
                <div class="form-group">
                    <label class="form-label" style="font-weight:700;color:#374151;display:block;margin-bottom:12px;">Assign Form</label>
                    <div style="display:flex;align-items:center;gap:40px;flex-wrap:nowrap;margin-bottom:16px;">

                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;font-weight:500;color:#374151;">Role</span>
                            <label class="af-toggle-switch">
                                <input type="checkbox" id="assign_role_chk"
                                       {{ $currentAssignType === 'role' ? 'checked' : '' }}
                                       onchange="afToggleChanged('role', this)">
                                <span class="af-toggle-track" id="role_track"
                                      style="background:{{ $currentAssignType === 'role' ? '#C8102E' : '#d1d5db' }};">
                                    <span class="af-toggle-knob" id="role_knob"
                                          style="left:{{ $currentAssignType === 'role' ? '23px' : '3px' }};"></span>
                                </span>
                            </label>
                        </div>

                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;font-weight:500;color:#374151;">User</span>
                            <label class="af-toggle-switch">
                                <input type="checkbox" id="assign_user_chk"
                                       {{ $currentAssignType === 'user' ? 'checked' : '' }}
                                       onchange="afToggleChanged('user', this)">
                                <span class="af-toggle-track" id="user_track"
                                      style="background:{{ $currentAssignType === 'user' ? '#C8102E' : '#d1d5db' }};">
                                    <span class="af-toggle-knob" id="user_knob"
                                          style="left:{{ $currentAssignType === 'user' ? '23px' : '3px' }};"></span>
                                </span>
                            </label>
                        </div>

                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;font-weight:500;color:#374151;">Public</span>
                            <label class="af-toggle-switch">
                                <input type="checkbox" id="assign_public_chk"
                                       {{ $currentAssignType === 'public' ? 'checked' : '' }}
                                       onchange="afToggleChanged('public', this)">
                                <span class="af-toggle-track" id="public_track"
                                      style="background:{{ $currentAssignType === 'public' ? '#C8102E' : '#d1d5db' }};">
                                    <span class="af-toggle-knob" id="public_knob"
                                          style="left:{{ $currentAssignType === 'public' ? '23px' : '3px' }};"></span>
                                </span>
                            </label>
                        </div>

                    </div>

                    <div id="role_field" style="{{ $currentAssignType === 'role' ? '' : 'display:none;' }}">
                        <div class="form-group" style="margin-top:10px;">
                            <label class="form-label">Role</label>
                            <select name="assign_role_value" class="form-control">
                                <option value="">Select Role</option>
                                <option value="admin"       {{ old('assign_role_value', $form->assign_type === 'role' ? $form->assign_user_id : '') === 'admin'       ? 'selected' : '' }}>Admin</option>
                                <option value="super_admin" {{ old('assign_role_value', $form->assign_type === 'role' ? $form->assign_user_id : '') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                                <option value="user"        {{ old('assign_role_value', $form->assign_type === 'role' ? $form->assign_user_id : '') === 'user'        ? 'selected' : '' }}>User</option>
                            </select>
                        </div>
                    </div>

                    <div id="user_field" style="{{ $currentAssignType === 'user' ? '' : 'display:none;' }}">
                        <div class="form-group" style="margin-top:10px;">
                            <label class="form-label">Select User</label>
                            <select name="assign_user_id" class="form-control">
                                <option value="">Select User</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}"
                                        {{ old('assign_user_id', $form->assign_user_id) == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    <a href="{{ route('forms.show', $form) }}" class="btn btn-secondary">Cancel</a>
                </div>

            </div>
        </div>
    </div>

    {{-- RIGHT: Email Setting card --}}
    <div class="col-right">
        <div class="email-setting-card">
            <div class="es-header">Email Setting</div>
            <div class="es-body">

                <div class="form-group">
                    <label class="form-label" style="font-size:14px;font-weight:600;color:#374151;">Recipient Email</label>
                    <input type="email" name="email" class="form-control"
                           value="{{ old('email', $form->email) }}"
                           placeholder="Enter recipient email">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size:14px;font-weight:600;color:#374151;">Cc Emails <span style="font-weight:400;color:#6b7280;">(Optional)</span></label>
                    <div class="tag-input-wrap" id="cc_wrap" onclick="document.getElementById('cc_input').focus()">
                        <span id="cc_tags"></span>
                        <input type="text" id="cc_input" class="tag-input-field"
                               placeholder="Enter recipient cc email"
                               onkeydown="tagKeydown(event,'cc')"
                               onblur="tagBlur('cc')">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size:14px;font-weight:600;color:#374151;">Bcc Emails <span style="font-weight:400;color:#6b7280;">(Optional)</span></label>
                    <div class="tag-input-wrap" id="bcc_wrap" onclick="document.getElementById('bcc_input').focus()">
                        <span id="bcc_tags"></span>
                        <input type="text" id="bcc_input" class="tag-input-field"
                               placeholder="Enter recipient bcc email"
                               onkeydown="tagKeydown(event,'bcc')"
                               onblur="tagBlur('bcc')">
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>
</form>

<script>
var AF_TYPES = ['role', 'user', 'public'];
function afToggleChanged(type, checkbox) {
    var on = checkbox.checked;
    if (on) {
        AF_TYPES.forEach(function(t) {
            if (t !== type) {
                var o = document.getElementById('assign_' + t + '_chk');
                if (o && o.checked) { o.checked = false; afSetVisual(t, false); afHideField(t); }
            }
        });
        document.getElementById('assign_type_hidden').value = type;
    } else {
        document.getElementById('assign_type_hidden').value = '';
    }
    afSetVisual(type, on);
    if (type === 'role') { document.getElementById('role_field').style.display = on ? '' : 'none'; if (!on) document.querySelector('select[name="assign_role_value"]').value = ''; }
    if (type === 'user') { document.getElementById('user_field').style.display = on ? '' : 'none'; if (!on) document.querySelector('select[name="assign_user_id"]').value = ''; }
}
function afSetVisual(type, on) {
    var track = document.getElementById(type + '_track');
    var knob  = document.getElementById(type + '_knob');
    if (track) track.style.background = on ? '#C8102E' : '#d1d5db';
    if (knob)  knob.style.left        = on ? '23px' : '3px';
}
function afHideField(type) {
    if (type === 'role') { document.getElementById('role_field').style.display = 'none'; document.querySelector('select[name="assign_role_value"]').value = ''; }
    if (type === 'user') { document.getElementById('user_field').style.display = 'none'; document.querySelector('select[name="assign_user_id"]').value = ''; }
}

var tagData = { cc: [], bcc: [] };
function renderTags(key) {
    var container = document.getElementById(key + '_tags');
    container.innerHTML = '';
    tagData[key].forEach(function(email, idx) {
        var chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = email + ' <button type="button" onclick="removeTag(\'' + key + '\',' + idx + ')">&#x2715;</button>';
        container.appendChild(chip);
    });
    document.getElementById(key + 'email_hidden').value = JSON.stringify(tagData[key]);
}
function addTag(key, val) {
    val = val.trim();
    if (!val) return;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return;
    if (tagData[key].indexOf(val) === -1) { tagData[key].push(val); renderTags(key); }
}
function removeTag(key, idx) { tagData[key].splice(idx, 1); renderTags(key); }
function tagKeydown(e, key) {
    if (e.key === 'Enter' || e.key === ',' || e.key === 'Tab') {
        e.preventDefault(); addTag(key, e.target.value); e.target.value = '';
    } else if (e.key === 'Backspace' && e.target.value === '' && tagData[key].length > 0) {
        tagData[key].pop(); renderTags(key);
    }
}
function tagBlur(key) {
    var input = document.getElementById(key + '_input');
    if (input.value.trim()) { addTag(key, input.value); input.value = ''; }
}

(function() {
    var ccSaved  = @json($ccEmails);
    var bccSaved = @json($bccEmails);
    if (Array.isArray(ccSaved)  && ccSaved.length)  { tagData.cc  = ccSaved;  renderTags('cc');  }
    if (Array.isArray(bccSaved) && bccSaved.length) { tagData.bcc = bccSaved; renderTags('bcc'); }
})();

document.getElementById('editFormEl').addEventListener('submit', function() {
    var ccInput  = document.getElementById('cc_input');
    var bccInput = document.getElementById('bcc_input');
    if (ccInput.value.trim())  { addTag('cc',  ccInput.value);  ccInput.value  = ''; }
    if (bccInput.value.trim()) { addTag('bcc', bccInput.value); bccInput.value = ''; }
});
</script>
@endsection
