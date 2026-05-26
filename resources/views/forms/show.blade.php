@extends('layouts.app')

@section('title', $form->name . ' — Preview')
@section('page-title', 'Form Preview')
@section('page-subtitle', 'Preview of: ' . $form->name)

@section('header-actions')
    <a href="{{ route('forms.edit', $form) }}" class="btn btn-secondary">
        <i class="fas fa-edit"></i> Edit Settings
    </a>
    <a href="{{ route('forms.builder', $form) }}" class="btn btn-secondary">
        <i class="fas fa-tools"></i> Open Builder
    </a>
    <a href="{{ route('forms.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Forms
    </a>
@endsection

@section('content')
<style>
/* ── Preview wrapper ────────────────────────────────────────── */
.preview-outer {
    max-width: 720px;
    margin: 0 auto;
    padding-bottom: 60px;
}

/* ── Preview badge bar ──────────────────────────────────────── */
.preview-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.preview-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.preview-badge.active  { background: #dcfce7; color: #16a34a; }
.preview-badge.draft   { background: #fef9c3; color: #a16207; }
.preview-badge.inactive { background: #f3f4f6; color: #6b7280; }
.preview-meta-item { font-size: 13px; color: #6b7280; }

/* ── Form card ──────────────────────────────────────────────── */
.pv-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    overflow: hidden;
}
.pv-card-header {
    background: #8B1A1A;
    padding: 28px 36px;
    color: #fff;
}
.pv-card-title {
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 4px;
}
.pv-card-desc {
    font-size: 14px;
    opacity: 0.85;
    line-height: 1.5;
}
.pv-card-body {
    padding: 24px 28px;
    background: #f3f4f6;
}

/* ── Fields ─────────────────────────────────────────────────── */
.pv-field {
    margin-bottom: 16px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 18px 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.pv-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 7px;
}
.pv-required { color: #dc2626; margin-left: 3px; }
.pv-help { font-size: 11px; color: #9ca3af; margin-top: 5px; }
.pv-input {
    width: 100%;
    padding: 10px 14px;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 9px;
    color: #374151;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color .15s;
}
.pv-input:focus { border-color: #8B1A1A; background: #fff; box-shadow: 0 0 0 3px rgba(139,26,26,0.1); }
.pv-textarea {
    width: 100%;
    padding: 10px 14px;
    height: 100px;
    resize: vertical;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 9px;
    color: #374151;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color .15s;
}
.pv-textarea:focus { border-color: #8B1A1A; background: #fff; box-shadow: 0 0 0 3px rgba(139,26,26,0.1); }
.pv-select {
    width: 100%;
    padding: 10px 14px;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 9px;
    color: #374151;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    transition: border-color .15s;
}
.pv-select:focus { border-color: #8B1A1A; background: #fff; box-shadow: 0 0 0 3px rgba(139,26,26,0.1); }

/* Choice */
.pv-choice-group { display: flex; flex-direction: column; gap: 8px; }
.pv-choice-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 9px;
    border: 1.5px solid #e5e7eb;
    background: #f9fafb;
    font-size: 14px;
    color: #374151;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.pv-choice-item:hover { border-color: #8B1A1A; background: #fdf2f2; }
.pv-choice-item input { accent-color: #8B1A1A; width: 15px; height: 15px; cursor: pointer; }

/* Toggle */
.pv-toggle-row { display: flex; align-items: center; justify-content: space-between; }
.pv-toggle-track {
    width: 44px; height: 24px; border-radius: 24px;
    background: #e5e7eb; position: relative; flex-shrink: 0;
    cursor: pointer; transition: background .2s;
}
.pv-toggle-track.on { background: #8B1A1A; }
.pv-toggle-knob {
    position: absolute; top: 3px; left: 3px;
    width: 18px; height: 18px; border-radius: 50%;
    background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
    transition: left .2s;
}
.pv-toggle-track.on .pv-toggle-knob { left: 23px; }

/* Rating */
.pv-rating { display: flex; gap: 6px; }
.pv-star { font-size: 28px; color: #e5e7eb; line-height: 1; cursor: pointer; transition: color .1s, transform .1s; }
.pv-star.active { color: #f59e0b; }
.pv-star:hover { transform: scale(1.15); }

/* Scale */
.pv-scale { display: flex; gap: 6px; flex-wrap: wrap; }
.pv-scale-num {
    width: 40px; height: 40px; border-radius: 8px;
    border: 1.5px solid #e5e7eb; background: #f9fafb;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: #9ca3af;
    cursor: pointer; transition: all .15s;
}
.pv-scale-num:hover, .pv-scale-num.selected { border-color: #8B1A1A; background: #8B1A1A; color: #fff; }
.pv-scale-labels { display: flex; justify-content: space-between; font-size: 11px; color: #9ca3af; margin-top: 5px; }

/* Signature */
.pv-sig-wrap { position: relative; }
.pv-sig-canvas {
    width: 100%; height: 140px; display: block;
    border: 1.5px solid #e5e7eb; border-radius: 9px;
    background: #f9fafb; cursor: crosshair;
    touch-action: none;
}
.pv-sig-canvas.has-sig { background: #fff; }
.pv-sig-actions {
    display: flex; justify-content: flex-end; margin-top: 5px; gap: 8px;
}
.pv-sig-clear {
    font-size: 11px; color: #9ca3af; background: none; border: 1px solid #e5e7eb;
    border-radius: 6px; padding: 3px 10px; cursor: pointer;
}
.pv-sig-clear:hover { color: #dc2626; border-color: #dc2626; }
.pv-sig-hint { font-size: 11px; color: #9ca3af; margin-top: 4px; text-align: center; }

/* File upload */
.pv-file-zone {
    border: 2px dashed #e5e7eb;
    border-radius: 9px;
    padding: 28px 20px;
    text-align: center;
    background: #f9fafb;
    color: #9ca3af;
    font-size: 13px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.pv-file-zone:hover { border-color: #8B1A1A; background: #fdf2f2; color: #8B1A1A; }
.pv-file-zone i { font-size: 28px; display: block; margin-bottom: 8px; }
.pv-file-name { font-size: 12px; color: #374151; margin-top: 8px; font-weight: 600; }

/* Section header */
.pv-section-header { margin-bottom: 4px; }
.pv-section-header h3 {
    font-size: 18px; font-weight: 700; color: #111827;
    padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;
}

/* Paragraph */
.pv-para { font-size: 14px; color: #6b7280; line-height: 1.7; }

/* Divider */
.pv-divider { border: none; border-top: 1px solid #e5e7eb; margin: 4px 0; }

/* Layout-only fields: no white box */
.pv-field.pv-field-header,
.pv-field.pv-field-paragraph,
.pv-field.pv-field-divider {
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 4px 0;
}

/* Multi-col row */
.pv-row { display: flex; gap: 18px; }
.pv-row .pv-field { flex: 1; min-width: 0; }

/* Name / Address */
.pv-name-row, .pv-addr-row { display: flex; gap: 10px; }
.pv-name-row .pv-input, .pv-addr-row .pv-input { flex: 1; }

/* Submit button preview */
.pv-submit-btn {
    width: 100%; padding: 13px 24px; border-radius: 10px;
    border: none; background: #8B1A1A; color: #fff;
    font-size: 15px; font-weight: 700; cursor: pointer;
    font-family: inherit; transition: background .15s;
}
.pv-submit-btn:hover { background: #6b1414; }

/* Preview notice - hidden */
.preview-notice { display: none; }

@media (max-width: 600px) {
    .pv-card-header, .pv-card-body { padding: 22px 18px; }
    .pv-row { flex-direction: column; gap: 0; }
    .pv-name-row, .pv-addr-row { flex-direction: column; }
}
</style>

<div class="preview-outer">

    {{-- Meta bar --}}
    <div class="preview-meta">
        <span class="preview-badge {{ $form->is_active ? 'active' : 'inactive' }}">
            <i class="fas fa-circle" style="font-size:8px;"></i>
            {{ $form->is_active ? 'Active' : 'Inactive' }}
        </span>
        <span class="preview-meta-item"><i class="fas fa-inbox" style="margin-right:4px;"></i>{{ $form->submissions_count ?? $form->submission_count ?? 0 }} submissions</span>
        <span class="preview-meta-item"><i class="fas fa-clock" style="margin-right:4px;"></i>Created {{ $form->created_at->format('M d, Y') }}</span>
    </div>



    {{-- Form card --}}
    <div class="pv-card">
        <div class="pv-card-header">
            <div class="pv-card-title">{{ $form->name }}</div>
            @if($form->description)
                <div class="pv-card-desc">{{ $form->description }}</div>
            @endif
        </div>
        <div class="pv-card-body" id="pvFormBody">
            {{-- Fields rendered by JS below --}}
        </div>
    </div>

</div>

<script>
const schema = @json($form->fields ?? ['rows' => []]);
const rows = schema.rows || [];

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

function renderPreview() {
    const container = document.getElementById('pvFormBody');
    if (!rows.length) {
        container.innerHTML = '<div style="text-align:center;padding:48px;color:#9ca3af;"><i class="fas fa-wpforms" style="font-size:36px;display:block;margin-bottom:12px;"></i>No fields added yet. Open the builder to add fields.</div>';
        return;
    }
    rows.forEach(row => {
        if (row.cols && row.cols.length > 1) {
            const rowEl = document.createElement('div');
            rowEl.className = 'pv-row';
            row.cols.forEach(col => (col.fields || []).forEach(field => rowEl.appendChild(renderField(field))));
            container.appendChild(rowEl);
        } else {
            (row.cols || []).forEach(col => (col.fields || []).forEach(field => container.appendChild(renderField(field))));
        }
    });
    // Append submit button at bottom
    const submitWrap = document.createElement('div');
    submitWrap.style.marginTop = '16px';
    submitWrap.innerHTML = `<button class="pv-submit-btn" type="button" onclick="alert('This is a preview — form submission is disabled.')">Submit Form</button>`;
    container.appendChild(submitWrap);
}

function renderField(field) {
    const wrap = document.createElement('div');
    wrap.className = 'pv-field';
    const req = field.required ? `<span class="pv-required">*</span>` : '';

    switch (field.type) {
        case 'header':
            wrap.classList.add('pv-field-header');
            wrap.innerHTML = `<div class="pv-section-header"><h3>${esc(field.content || 'Section')}</h3></div>`;
            break;
        case 'paragraph':
            wrap.classList.add('pv-field-paragraph');
            wrap.innerHTML = `<div class="pv-para">${esc(field.content || '')}</div>`;
            break;
        case 'divider':
            wrap.classList.add('pv-field-divider');
            wrap.innerHTML = `<hr class="pv-divider">`;
            break;
        case 'image': {
            const imgFileId = 'pvImgFile_' + field.id;
            const imgZoneId = 'pvImgZone_' + field.id;
            const imgNameId = 'pvImgName_' + field.id;
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-file-zone" id="${imgZoneId}" onclick="document.getElementById('${imgFileId}').click()">
                    <i class="fas fa-image"></i>
                    Click to upload image<br>
                    <span style="font-size:11px;">JPG, PNG, GIF, WEBP up to 10MB</span>
                    <div class="pv-file-name" id="${imgNameId}" style="display:none;"></div>
                </div>
                <input type="file" id="${imgFileId}" accept="image/*" style="display:none;" onchange="pvFileChosen('${imgFileId}','${imgNameId}','${imgZoneId}')">`;
            break;
        }
        case 'submit':
            wrap.classList.add('pv-field-divider'); // reuse transparent style
            wrap.innerHTML = '';
            break;
        case 'text': case 'email': case 'phone': case 'number': case 'date': case 'time': case 'password':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <input class="pv-input" type="${field.type === 'phone' ? 'tel' : field.type}" placeholder="${esc(field.placeholder || '')}">
                ${field.helpText ? `<div class="pv-help">${esc(field.helpText)}</div>` : ''}`;
            break;
        case 'textarea':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <textarea class="pv-textarea" placeholder="${esc(field.placeholder || '')}"></textarea>
                ${field.helpText ? `<div class="pv-help">${esc(field.helpText)}</div>` : ''}`;
            break;
        case 'dropdown':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <select class="pv-select">
                    <option>${esc(field.placeholder || 'Select an option...')}</option>
                    ${(field.options || []).map(o => `<option>${esc(o)}</option>`).join('')}
                </select>
                ${field.helpText ? `<div class="pv-help">${esc(field.helpText)}</div>` : ''}`;
            break;
        case 'radio':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-choice-group">
                    ${(field.options || []).map((o, i) => `<div class="pv-choice-item" onclick="this.querySelector('input').click()"><input type="radio" name="pv_r_${field.id}" value="${esc(o)}"><span>${esc(o)}</span></div>`).join('')}
                </div>`;
            break;
        case 'checkbox':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-choice-group">
                    ${(field.options || []).map((o, i) => `<div class="pv-choice-item" onclick="this.querySelector('input').click()"><input type="checkbox" value="${esc(o)}"><span>${esc(o)}</span></div>`).join('')}
                </div>
                ${field.helpText ? `<div class="pv-help">${esc(field.helpText)}</div>` : ''}`;
            break;
        case 'toggle':
            wrap.innerHTML = `<div class="pv-toggle-row">
                <label class="pv-label" style="margin:0;">${esc(field.label)}${req}</label>
                <div class="pv-toggle-track" id="pvtgl_${field.id}" onclick="pvToggle('${field.id}')"><div class="pv-toggle-knob"></div></div>
            </div>`;
            break;
        case 'rating':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-rating" id="pvrat_${field.id}">${[1,2,3,4,5].map((n) => `<span class="pv-star" data-val="${n}" onclick="pvSetRating('${field.id}', ${n})">★</span>`).join('')}</div>`;
            break;
        case 'scale':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-scale" id="pvscl_${field.id}">${[1,2,3,4,5,6,7,8,9,10].map(n => `<div class="pv-scale-num" data-val="${n}" onclick="pvSetScale('${field.id}', ${n})">${n}</div>`).join('')}</div>
                <div class="pv-scale-labels"><span>Not at all</span><span>Extremely</span></div>`;
            break;
        case 'signature': {
            const sigId = 'pvSig_' + field.id;
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-sig-wrap">
                    <canvas id="${sigId}" class="pv-sig-canvas"></canvas>
                    <div class="pv-sig-actions">
                        <button type="button" class="pv-sig-clear" onclick="pvSigClear('${sigId}')">Clear</button>
                    </div>
                    <div class="pv-sig-hint">Draw your signature above</div>
                </div>`;
            // Init canvas after DOM insertion (deferred)
            setTimeout(() => pvSigInit(sigId), 0);
            break;
        }
        case 'file': {
            const fileId = 'pvFile_' + field.id;
            const zoneId = 'pvZone_' + field.id;
            const nameId = 'pvFName_' + field.id;
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-file-zone" id="${zoneId}" onclick="document.getElementById('${fileId}').click()">
                    <i class="fas fa-paperclip"></i>
                    Click to upload or drag &amp; drop<br>
                    <span style="font-size:11px;">PDF, JPG, PNG, DOCX up to 10MB</span>
                    <div class="pv-file-name" id="${nameId}" style="display:none;"></div>
                </div>
                <input type="file" id="${fileId}" style="display:none;" onchange="pvFileChosen('${fileId}','${nameId}','${zoneId}')">`;
            break;
        }
        case 'address':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <textarea class="pv-textarea" placeholder="${esc(field.placeholder || 'Enter address...')}" rows="3"></textarea>
                ${field.helpText ? `<div class="pv-help">${esc(field.helpText)}</div>` : ''}`;
            break;
        case 'name':
            wrap.innerHTML = `<label class="pv-label">${esc(field.label)}${req}</label>
                <div class="pv-name-row">
                    <input class="pv-input" placeholder="First Name">
                    <input class="pv-input" placeholder="Last Name">
                </div>`;
            break;
        default:
            wrap.innerHTML = `<label class="pv-label">${esc(field.label || field.type)}${req}</label>
                <input class="pv-input" type="text" placeholder="${esc(field.placeholder || '')}">`;
            break;
    }
    return wrap;
}

renderPreview();

function pvToggle(id) {
    const track = document.getElementById('pvtgl_' + id);
    if (track) track.classList.toggle('on');
}

function pvSetRating(id, val) {
    document.querySelectorAll('#pvrat_' + id + ' .pv-star').forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.val) <= val);
    });
}

function pvSetScale(id, val) {
    document.querySelectorAll('#pvscl_' + id + ' .pv-scale-num').forEach(n => {
        n.classList.toggle('selected', parseInt(n.dataset.val) === val);
    });
}

/* ── Signature pad ─────────────────────────────────────────── */
const _sigState = {};
function pvSigInit(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    // Set internal resolution to match display size
    const rect = canvas.getBoundingClientRect();
    canvas.width  = rect.width  || 600;
    canvas.height = rect.height || 140;
    const ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#1a1a1a';
    ctx.lineWidth   = 2;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
    _sigState[canvasId] = { drawing: false, ctx, canvas };

    function getPos(e) {
        const r = canvas.getBoundingClientRect();
        const src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - r.left) * (canvas.width / r.width),
                 y: (src.clientY - r.top)  * (canvas.height / r.height) };
    }
    function start(e) { e.preventDefault(); const s = _sigState[canvasId]; s.drawing = true; const p = getPos(e); s.ctx.beginPath(); s.ctx.moveTo(p.x, p.y); canvas.classList.add('has-sig'); }
    function move(e)  { e.preventDefault(); const s = _sigState[canvasId]; if (!s.drawing) return; const p = getPos(e); s.ctx.lineTo(p.x, p.y); s.ctx.stroke(); }
    function stop(e)  { _sigState[canvasId].drawing = false; }

    canvas.addEventListener('mousedown',  start);
    canvas.addEventListener('mousemove',  move);
    canvas.addEventListener('mouseup',    stop);
    canvas.addEventListener('mouseleave', stop);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove',  move,  { passive: false });
    canvas.addEventListener('touchend',   stop);
}
function pvSigClear(canvasId) {
    const s = _sigState[canvasId];
    if (!s) return;
    s.ctx.clearRect(0, 0, s.canvas.width, s.canvas.height);
    s.canvas.classList.remove('has-sig');
}

/* ── File upload ───────────────────────────────────────────── */
function pvFileChosen(inputId, nameId, zoneId) {
    const input = document.getElementById(inputId);
    const nameEl = document.getElementById(nameId);
    const zone   = document.getElementById(zoneId);
    if (!input || !input.files.length) return;
    const file = input.files[0];
    nameEl.textContent = '✓ ' + file.name;
    nameEl.style.display = 'block';
    zone.style.borderColor = '#16a34a';
    zone.style.background  = '#f0fdf4';
    zone.style.color       = '#16a34a';
}
</script>
@endsection
