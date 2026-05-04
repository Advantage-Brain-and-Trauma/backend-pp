@extends('layouts.app')
@section('title', 'Create Form - AdvantageHCS Admin')
@section('page-title', 'Create Form')
@section('page-subtitle', 'Set up a new patient form')
@section('header-actions')
    <a href="{{ route('forms.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
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

<form method="POST" action="{{ route('forms.store') }}" id="createFormEl">
@csrf

{{-- Hidden assign_type --}}
<input type="hidden" name="assign_type" id="assign_type_hidden" value="{{ old('assign_type', 'role') }}">

{{-- Hidden JSON fields for cc/bcc tags --}}
<input type="hidden" name="ccemail"  id="ccemail_hidden"  value="{{ old('ccemail', '') }}">
<input type="hidden" name="bccemail" id="bccemail_hidden" value="{{ old('bccemail', '') }}">

<div class="form-two-col">

    {{-- LEFT: Form Details card --}}
    <div class="col-left">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Form Details</span>
            </div>
            <div class="card-body">

                {{-- Form Name --}}
                <div class="form-group">
                    <label class="form-label">Form Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                           placeholder="Enter form name..." required>
                </div>

                {{-- Description --}}
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Enter form description...">{{ old('description') }}</textarea>
                </div>

                {{-- Success Message --}}
                <div class="form-group">
                    <label class="form-label">Success Message</label>
                    <textarea name="success_msg" class="form-control" rows="3"
                              placeholder="Message shown after successful form submission...">{{ old('success_msg') }}</textarea>
                </div>

                {{-- Thanks Message --}}
                <div class="form-group">
                    <label class="form-label">Thanks Message</label>
                    <textarea name="thanks_msg" class="form-control" rows="3"
                              placeholder="Message shown on the thank-you page...">{{ old('thanks_msg') }}</textarea>
                </div>

                {{-- Assign Form --}}
                <div class="form-group">
                    <label class="form-label" style="font-weight:700;color:#374151;display:block;margin-bottom:12px;">Assign Form</label>
                    <div style="display:flex;align-items:center;gap:40px;flex-wrap:nowrap;margin-bottom:16px;">

                        {{-- Role toggle --}}
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;font-weight:500;color:#374151;">Role</span>
                            <label class="af-toggle-switch">
                                <input type="checkbox" id="assign_role_chk"
                                       {{ old('assign_type', 'role') === 'role' ? 'checked' : '' }}
                                       onchange="afToggleChanged('role', this)">
                                <span class="af-toggle-track" id="role_track"
                                      style="background:{{ old('assign_type', 'role') === 'role' ? '#C8102E' : '#d1d5db' }};">
                                    <span class="af-toggle-knob" id="role_knob"
                                          style="left:{{ old('assign_type', 'role') === 'role' ? '23px' : '3px' }};"></span>
                                </span>
                            </label>
                        </div>

                        {{-- User toggle --}}
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;font-weight:500;color:#374151;">User</span>
                            <label class="af-toggle-switch">
                                <input type="checkbox" id="assign_user_chk"
                                       {{ old('assign_type') === 'user' ? 'checked' : '' }}
                                       onchange="afToggleChanged('user', this)">
                                <span class="af-toggle-track" id="user_track"
                                      style="background:{{ old('assign_type') === 'user' ? '#C8102E' : '#d1d5db' }};">
                                    <span class="af-toggle-knob" id="user_knob"
                                          style="left:{{ old('assign_type') === 'user' ? '23px' : '3px' }};"></span>
                                </span>
                            </label>
                        </div>

                        {{-- Public toggle --}}
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;font-weight:500;color:#374151;">Public</span>
                            <label class="af-toggle-switch">
                                <input type="checkbox" id="assign_public_chk"
                                       {{ old('assign_type') === 'public' ? 'checked' : '' }}
                                       onchange="afToggleChanged('public', this)">
                                <span class="af-toggle-track" id="public_track"
                                      style="background:{{ old('assign_type') === 'public' ? '#C8102E' : '#d1d5db' }};">
                                    <span class="af-toggle-knob" id="public_knob"
                                          style="left:{{ old('assign_type') === 'public' ? '23px' : '3px' }};"></span>
                                </span>
                            </label>
                        </div>

                    </div>

                    {{-- Role dropdown --}}
                    <div id="role_field" style="{{ old('assign_type', 'role') === 'role' ? '' : 'display:none;' }}">
                        <div class="form-group" style="margin-top:10px;">
                            <label class="form-label">Role</label>
                            <select name="assign_role_value" class="form-control">
                                <option value="">Select Role</option>
                                <option value="admin"       {{ old('assign_role_value') === 'admin'       ? 'selected' : '' }}>Admin</option>
                                <option value="super_admin" {{ old('assign_role_value') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                                <option value="user"        {{ old('assign_role_value') === 'user'        ? 'selected' : '' }}>User</option>
                            </select>
                        </div>
                    </div>

                    {{-- User dropdown --}}
                    <div id="user_field" style="{{ old('assign_type') === 'user' ? '' : 'display:none;' }}">
                        <div class="form-group" style="margin-top:10px;">
                            <label class="form-label">Select User</label>
                            <select name="assign_user_id" class="form-control">
                                <option value="">Select User</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('assign_user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Submit buttons --}}
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Form</button>
                    <a href="{{ route('forms.index') }}" class="btn btn-secondary">Cancel</a>
                </div>

            </div>
        </div>
    </div>{{-- /col-left --}}

    {{-- RIGHT: Email Setting card --}}
    <div class="col-right">
        <div class="email-setting-card">
            <div class="es-header">Email Setting</div>
            <div class="es-body">

                {{-- Recipient Email --}}
                <div class="form-group">
                    <label class="form-label" style="font-size:14px;font-weight:600;color:#374151;">Recipient Email</label>
                    <input type="email" name="email" class="form-control"
                           value="{{ old('email') }}"
                           placeholder="Enter recipient email">
                </div>

                {{-- Cc Emails --}}
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

                {{-- Bcc Emails --}}
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
    </div>{{-- /col-right --}}

</div>{{-- /form-two-col --}}
</form>

<script>
/* ===== Assign-type toggles ===== */
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

/* ===== Tag-input for CC / BCC ===== */
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
    // Update hidden JSON field
    document.getElementById(key + 'email_hidden').value = JSON.stringify(tagData[key]);
}

function addTag(key, val) {
    val = val.trim();
    if (!val) return;
    // Basic email validation
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return;
    if (tagData[key].indexOf(val) === -1) {
        tagData[key].push(val);
        renderTags(key);
    }
}

function removeTag(key, idx) {
    tagData[key].splice(idx, 1);
    renderTags(key);
}

function tagKeydown(e, key) {
    if (e.key === 'Enter' || e.key === ',' || e.key === 'Tab') {
        e.preventDefault();
        addTag(key, e.target.value);
        e.target.value = '';
    } else if (e.key === 'Backspace' && e.target.value === '' && tagData[key].length > 0) {
        tagData[key].pop();
        renderTags(key);
    }
}

function tagBlur(key) {
    var input = document.getElementById(key + '_input');
    if (input.value.trim()) {
        addTag(key, input.value);
        input.value = '';
    }
}

// Pre-populate from old() values on validation error
(function() {
    var ccOld  = @json(old('ccemail', ''));
    var bccOld = @json(old('bccemail', ''));
    if (ccOld)  { try { tagData.cc  = JSON.parse(ccOld);  renderTags('cc');  } catch(e){} }
    if (bccOld) { try { tagData.bcc = JSON.parse(bccOld); renderTags('bcc'); } catch(e){} }
})();

// Before submit: flush any typed-but-not-confirmed email in the inputs
document.getElementById('createFormEl').addEventListener('submit', function() {
    var ccInput  = document.getElementById('cc_input');
    var bccInput = document.getElementById('bcc_input');
    if (ccInput.value.trim())  { addTag('cc',  ccInput.value);  ccInput.value  = ''; }
    if (bccInput.value.trim()) { addTag('bcc', bccInput.value); bccInput.value = ''; }
});
</script>
@endsection
