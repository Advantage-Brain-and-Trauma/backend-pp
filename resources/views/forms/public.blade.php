<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $form->name }} — AdvantageHCS</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', system-ui, sans-serif;
  background: #f0f4f8;
  color: #1a202c;
  min-height: 100vh;
  padding: 0;
}

/* Header */
.form-header-bar {
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  padding: 14px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.form-header-logo {
  display: flex; align-items: center; gap: 10px;
  font-weight: 700; font-size: 15px; color: #1a202c;
  text-decoration: none;
}
.form-header-logo span {
  width: 34px; height: 34px; background: #6366f1;
  border-radius: 8px; display: flex; align-items: center;
  justify-content: center; color: #fff; font-weight: 800; font-size: 14px;
}
.form-header-divider { width: 1px; height: 24px; background: #e2e8f0; }
.form-header-name { font-size: 14px; color: #4a5568; font-weight: 500; }

/* Progress Bar */
.progress-bar-wrap {
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  padding: 0 24px;
}
.progress-bar-inner {
  max-width: 700px; margin: 0 auto;
  padding: 12px 0;
  display: flex; align-items: center; gap: 12px;
}
.progress-track {
  flex: 1; height: 5px; background: #e2e8f0; border-radius: 10px; overflow: hidden;
}
.progress-fill {
  height: 100%; background: #6366f1; border-radius: 10px;
  transition: width 0.4s ease;
}
.progress-label { font-size: 12px; color: #718096; white-space: nowrap; }

/* Main */
.form-main {
  max-width: 700px;
  margin: 36px auto;
  padding: 0 20px 80px;
}

/* Form Card */
.form-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
  overflow: hidden;
}
.form-card-header {
  padding: 32px 36px 24px;
  border-bottom: 1px solid #f0f4f8;
  background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
}
.form-card-title {
  font-size: 26px; font-weight: 800; color: #1a202c; margin-bottom: 8px;
  line-height: 1.3;
}
.form-card-desc { font-size: 14px; color: #718096; line-height: 1.6; }
.form-card-body { padding: 32px 36px; }

/* Fields */
.form-field { margin-bottom: 24px; }
.form-label {
  display: block; font-size: 13px; font-weight: 600;
  color: #2d3748; margin-bottom: 7px;
}
.form-required { color: #e53e3e; margin-left: 3px; }
.form-help { font-size: 11px; color: #a0aec0; margin-top: 5px; }
.form-input {
  width: 100%; padding: 11px 14px;
  background: #f8f9fb; border: 1.5px solid #e2e8f0;
  border-radius: 9px; color: #1a202c; font-size: 14px;
  outline: none; transition: all 0.15s ease; font-family: inherit;
  appearance: none;
}
.form-input:focus {
  border-color: #6366f1;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.form-input.error { border-color: #e53e3e; }
.form-textarea {
  width: 100%; padding: 11px 14px; height: 110px; resize: vertical;
  background: #f8f9fb; border: 1.5px solid #e2e8f0;
  border-radius: 9px; color: #1a202c; font-size: 14px;
  outline: none; transition: all 0.15s ease; font-family: inherit;
}
.form-textarea:focus { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.form-select {
  width: 100%; padding: 11px 14px;
  background: #f8f9fb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23718096' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 14px center;
  border: 1.5px solid #e2e8f0; border-radius: 9px;
  color: #1a202c; font-size: 14px; outline: none;
  cursor: pointer; transition: all 0.15s ease; font-family: inherit;
}
.form-select:focus { border-color: #6366f1; background-color: #fff; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }

/* Radio / Checkbox */
.choice-group { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.choice-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 14px; border-radius: 9px;
  border: 1.5px solid #e2e8f0; cursor: pointer;
  transition: all 0.15s ease; background: #f8f9fb;
}
.choice-item:hover { border-color: #6366f1; background: #f0f0fd; }
.choice-item input { accent-color: #6366f1; width: 16px; height: 16px; cursor: pointer; }
.choice-item label { font-size: 14px; color: #2d3748; cursor: pointer; flex: 1; }

/* Toggle */
.toggle-field { display: flex; align-items: center; justify-content: space-between; padding: 4px 0; }
.toggle-switch {
  width: 50px; height: 28px; border-radius: 14px; background: #e2e8f0;
  cursor: pointer; position: relative; transition: background 0.2s; border: none; outline: none;
  flex-shrink: 0;
}
.toggle-switch.on { background: #6366f1; }
.toggle-switch::after {
  content: ''; position: absolute; top: 4px; left: 4px;
  width: 20px; height: 20px; border-radius: 50%; background: #fff;
  transition: left 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.toggle-switch.on::after { left: 26px; }

/* Rating */
.rating-group { display: flex; gap: 6px; }
.rating-star {
  font-size: 32px; cursor: pointer; color: #e2e8f0;
  transition: color 0.1s, transform 0.1s;
  line-height: 1;
}
.rating-star.active { color: #f59e0b; }
.rating-star:hover { transform: scale(1.15); }

/* Scale */
.scale-group { display: flex; gap: 6px; flex-wrap: wrap; }
.scale-num {
  width: 42px; height: 42px; border-radius: 8px;
  border: 1.5px solid #e2e8f0; background: #f8f9fb;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 600; color: #718096;
  cursor: pointer; transition: all 0.15s;
}
.scale-num:hover, .scale-num.selected { border-color: #6366f1; background: #6366f1; color: #fff; }
.scale-labels { display: flex; justify-content: space-between; font-size: 11px; color: #a0aec0; margin-top: 6px; }

/* Signature */
.sig-canvas-wrap { position: relative; border: 1.5px solid #e2e8f0; border-radius: 9px; overflow: hidden; background: #f8f9fb; }
.sig-canvas { display: block; width: 100%; height: 130px; cursor: crosshair; touch-action: none; }
.sig-clear { position: absolute; top: 8px; right: 8px; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #718096; font-size: 11px; cursor: pointer; transition: all 0.15s; }
.sig-clear:hover { border-color: #e53e3e; color: #e53e3e; }
.sig-hint { text-align: center; font-size: 11px; color: #a0aec0; margin-top: 5px; }

/* File Upload */
.file-upload-zone {
  border: 2px dashed #e2e8f0; border-radius: 9px; padding: 32px 20px;
  text-align: center; cursor: pointer; transition: all 0.15s; background: #f8f9fb;
}
.file-upload-zone:hover, .file-upload-zone.drag-over { border-color: #6366f1; background: #f0f0fd; }
.file-upload-icon { font-size: 32px; margin-bottom: 8px; }
.file-upload-text { font-size: 14px; color: #4a5568; font-weight: 500; }
.file-upload-hint { font-size: 11px; color: #a0aec0; margin-top: 4px; }
.file-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.file-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f0f4f8; border-radius: 7px; font-size: 12px; color: #4a5568; }
.file-item-remove { margin-left: auto; cursor: pointer; color: #a0aec0; font-size: 14px; }
.file-item-remove:hover { color: #e53e3e; }

/* Address */
.address-grid { display: flex; flex-direction: column; gap: 10px; }
.address-row { display: flex; gap: 10px; }
.address-row .form-input { flex: 1; }

/* Name */
.name-row { display: flex; gap: 10px; }
.name-row .form-input { flex: 1; }

/* Section Header */
.section-header { margin-bottom: 4px; }
.section-header h3 { font-size: 20px; font-weight: 700; color: #1a202c; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }

/* Paragraph */
.para-text { font-size: 14px; color: #718096; line-height: 1.7; }

/* Divider */
.form-divider { border: none; border-top: 1px solid #e2e8f0; margin: 4px 0; }

/* Multi-col row */
.form-row { display: flex; gap: 20px; }
.form-row .form-field { flex: 1; min-width: 0; }

/* Submit */
.form-submit-wrap { margin-top: 10px; }
.form-submit-btn {
  width: 100%; padding: 14px 24px; border-radius: 10px;
  border: none; background: #6366f1; color: #fff;
  font-size: 15px; font-weight: 700; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 14px rgba(99,102,241,0.35);
  font-family: inherit;
}
.form-submit-btn:hover { background: #4f52d8; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.form-submit-btn:active { transform: translateY(0); }
.form-submit-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

/* Error message */
.field-error { font-size: 11px; color: #e53e3e; margin-top: 5px; display: none; }
.field-error.visible { display: block; }

/* Success screen */
.success-screen {
  display: none; text-align: center; padding: 60px 36px;
}
.success-screen.visible { display: block; }
.success-icon { font-size: 64px; margin-bottom: 20px; }
.success-title { font-size: 26px; font-weight: 800; color: #1a202c; margin-bottom: 10px; }
.success-desc { font-size: 15px; color: #718096; line-height: 1.6; max-width: 400px; margin: 0 auto; }

/* Footer */
.form-footer {
  text-align: center; padding: 24px;
  font-size: 12px; color: #a0aec0;
}
.form-footer a { color: #6366f1; text-decoration: none; }

/* Responsive */
@media (max-width: 600px) {
  .form-card-header, .form-card-body { padding: 24px 20px; }
  .form-row { flex-direction: column; gap: 0; }
  .name-row, .address-row { flex-direction: column; }
  .form-card-title { font-size: 22px; }
}
</style>
</head>
<body>

<!-- Header -->
<div class="form-header-bar">
  <a href="/" class="form-header-logo">
    <span>A</span> AdvantageHCS
  </a>
  <div class="form-header-divider"></div>
  <span class="form-header-name">{{ $form->name }}</span>
</div>

<!-- Progress Bar -->
<div class="progress-bar-wrap">
  <div class="progress-bar-inner">
    <div class="progress-track">
      <div class="progress-fill" id="progressFill" style="width:0%"></div>
    </div>
    <span class="progress-label" id="progressLabel">0% complete</span>
  </div>
</div>

<!-- Main Form -->
<div class="form-main">
  <div class="form-card">
    <!-- Success Screen (hidden by default) -->
    <div class="success-screen" id="successScreen">
      <div class="success-icon">✅</div>
      <div class="success-title">Form Submitted!</div>
      <div class="success-desc">Thank you for completing this form. Our team will review your submission and get back to you shortly.</div>
    </div>

    <!-- Form Header -->
    <div class="form-card-header" id="formCardHeader">
      <div class="form-card-title">{{ $form->name }}</div>
      @if($form->description)
      <div class="form-card-desc">{{ $form->description }}</div>
      @endif
    </div>

    <!-- Form Body -->
    <div class="form-card-body" id="formCardBody">
      @if(session('success'))
        <div style="padding:14px 18px;background:#f0fdf4;border:1px solid #86efac;border-radius:9px;color:#16a34a;font-size:14px;margin-bottom:20px;">
          ✅ {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div style="padding:14px 18px;background:#fef2f2;border:1px solid #fca5a5;border-radius:9px;color:#dc2626;font-size:14px;margin-bottom:20px;">
          ❌ {{ session('error') }}
        </div>
      @endif

      <form id="publicForm" method="POST" action="{{ route('forms.submit', $form->slug) }}" enctype="multipart/form-data" novalidate>
        @csrf
        <div id="formFields"></div>
        <div class="form-submit-wrap">
          <button type="submit" class="form-submit-btn" id="submitBtn">Submit Form</button>
        </div>
      </form>
    </div>
  </div>

  <div class="form-footer">
    Powered by <a href="#">AdvantageHCS Patient Portal</a> &nbsp;·&nbsp; Your data is encrypted and secure 🔒
  </div>
</div>

<script>
// Form schema from Laravel
const schema = @json($form->fields ?? ['rows' => []]);
const rows = schema.rows || [];
let totalFields = 0;
let filledFields = 0;

function renderForm() {
  const container = document.getElementById('formFields');
  rows.forEach(row => {
    if (row.cols.length > 1) {
      const rowEl = document.createElement('div');
      rowEl.className = 'form-row';
      row.cols.forEach(col => col.fields.forEach(field => { totalFields++; rowEl.appendChild(renderField(field)); }));
      container.appendChild(rowEl);
    } else {
      row.cols.forEach(col => col.fields.forEach(field => { totalFields++; container.appendChild(renderField(field)); }));
    }
  });
  updateProgress();
}

function renderField(field) {
  const wrap = document.createElement('div');
  wrap.className = 'form-field';
  wrap.dataset.fieldId = field.id;

  const labelRequired = field.required ? `<span class="form-required">*</span>` : '';

  switch(field.type) {
    case 'header':
      wrap.innerHTML = `<div class="section-header"><h3>${esc(field.content || 'Section')}</h3></div>`;
      totalFields--; break;
    case 'paragraph':
      wrap.innerHTML = `<div class="para-text">${esc(field.content || '')}</div>`;
      totalFields--; break;
    case 'divider':
      wrap.innerHTML = `<hr class="form-divider">`;
      totalFields--; break;
    case 'image':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="file-upload-zone" id="fuz_img_${field.id}" onclick="document.getElementById('imgfile_${field.id}').click()">
          <div class="file-upload-icon">🖼</div>
          <div class="file-upload-text">Click to upload image</div>
          <div class="file-upload-hint">JPG, PNG, GIF, WEBP up to 10MB</div>
        </div>
        <input type="file" id="imgfile_${field.id}" name="fields[${field.id}]" accept="image/*" style="display:none;" onchange="handleFileSelect('imgf_${field.id}', this)" ${field.required ? 'required' : ''}>
        <div class="file-list" id="fl_imgf_${field.id}"></div>`;
      setTimeout(() => setupFileDrop('imgf_' + field.id), 0);
      break;
    case 'submit':
      wrap.innerHTML = ''; totalFields--;
      document.getElementById('submitBtn').textContent = field.buttonText || 'Submit Form';
      break;
    case 'text': case 'email': case 'phone': case 'number': case 'date': case 'time': case 'password':
      wrap.innerHTML = `<label class="form-label" for="${field.id}">${esc(field.label)}${labelRequired}</label>
        <input class="form-input" id="${field.id}" name="fields[${field.id}]" type="${field.type === 'phone' ? 'tel' : field.type}" placeholder="${esc(field.placeholder)}" ${field.required ? 'required' : ''}>
        ${field.helpText ? `<div class="form-help">${esc(field.helpText)}</div>` : ''}
        <div class="field-error" id="err_${field.id}">This field is required.</div>`;
      setTimeout(() => {
        const inp = document.getElementById(field.id);
        if (inp) inp.addEventListener('input', () => { updateProgress(); clearError(field.id); });
      }, 0);
      break;
    case 'textarea':
      wrap.innerHTML = `<label class="form-label" for="${field.id}">${esc(field.label)}${labelRequired}</label>
        <textarea class="form-textarea" id="${field.id}" name="fields[${field.id}]" placeholder="${esc(field.placeholder)}" ${field.required ? 'required' : ''}></textarea>
        ${field.helpText ? `<div class="form-help">${esc(field.helpText)}</div>` : ''}
        <div class="field-error" id="err_${field.id}">This field is required.</div>`;
      setTimeout(() => {
        const inp = document.getElementById(field.id);
        if (inp) inp.addEventListener('input', () => { updateProgress(); clearError(field.id); });
      }, 0);
      break;
    case 'dropdown':
      wrap.innerHTML = `<label class="form-label" for="${field.id}">${esc(field.label)}${labelRequired}</label>
        <select class="form-select" id="${field.id}" name="fields[${field.id}]" ${field.required ? 'required' : ''}>
          <option value="">${esc(field.placeholder || 'Select an option...')}</option>
          ${(field.options || []).map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('')}
        </select>
        ${field.helpText ? `<div class="form-help">${esc(field.helpText)}</div>` : ''}
        <div class="field-error" id="err_${field.id}">Please select an option.</div>`;
      setTimeout(() => {
        const inp = document.getElementById(field.id);
        if (inp) inp.addEventListener('change', () => { updateProgress(); clearError(field.id); });
      }, 0);
      break;
    case 'radio':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="choice-group" id="rg_${field.id}">
          ${(field.options || []).map((o, i) => `<div class="choice-item"><input type="radio" id="${field.id}_${i}" name="fields[${field.id}]" value="${esc(o)}" ${field.required ? 'required' : ''}><label for="${field.id}_${i}">${esc(o)}</label></div>`).join('')}
        </div>
        <div class="field-error" id="err_${field.id}">Please select an option.</div>`;
      setTimeout(() => {
        document.querySelectorAll(`#rg_${field.id} input`).forEach(inp => inp.addEventListener('change', () => { updateProgress(); clearError(field.id); }));
      }, 0);
      break;
    case 'checkbox':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="choice-group" id="cg_${field.id}">
          ${(field.options || []).map((o, i) => `<div class="choice-item"><input type="checkbox" id="${field.id}_${i}" name="fields[${field.id}][]" value="${esc(o)}"><label for="${field.id}_${i}">${esc(o)}</label></div>`).join('')}
        </div>
        ${field.helpText ? `<div class="form-help">${esc(field.helpText)}</div>` : ''}`;
      break;
    case 'toggle':
      wrap.innerHTML = `<div class="toggle-field">
        <label class="form-label" style="margin:0;">${esc(field.label)}${labelRequired}</label>
        <button type="button" class="toggle-switch" id="tgl_${field.id}" onclick="toggleSwitch('${field.id}')"></button>
        <input type="hidden" name="fields[${field.id}]" id="tgl_val_${field.id}" value="0">
      </div>`;
      break;
    case 'rating':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="rating-group" id="rat_${field.id}">
          ${[1,2,3,4,5].map(n => `<span class="rating-star" data-val="${n}" onclick="setRating('${field.id}', ${n})">★</span>`).join('')}
        </div>
        <input type="hidden" name="fields[${field.id}]" id="rat_val_${field.id}" value="">
        <div class="field-error" id="err_${field.id}">Please select a rating.</div>`;
      break;
    case 'scale':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="scale-group" id="scl_${field.id}">
          ${[1,2,3,4,5,6,7,8,9,10].map(n => `<div class="scale-num" data-val="${n}" onclick="setScale('${field.id}', ${n})">${n}</div>`).join('')}
        </div>
        <div class="scale-labels"><span>Not at all</span><span>Extremely</span></div>
        <input type="hidden" name="fields[${field.id}]" id="scl_val_${field.id}" value="">`;
      break;
    case 'signature':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="sig-canvas-wrap">
          <canvas class="sig-canvas" id="sig_${field.id}" width="660" height="130"></canvas>
          <button type="button" class="sig-clear" onclick="clearSig('${field.id}')">Clear</button>
        </div>
        <div class="sig-hint">Draw your signature above</div>
        <input type="hidden" name="fields[${field.id}]" id="sig_val_${field.id}" value="">`;
      setTimeout(() => initSignature(field.id), 0);
      break;
    case 'file':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="file-upload-zone" id="fuz_${field.id}" onclick="document.getElementById('file_${field.id}').click()">
          <div class="file-upload-icon">📎</div>
          <div class="file-upload-text">Click to upload or drag & drop</div>
          <div class="file-upload-hint">PDF, JPG, PNG, DOCX up to 10MB</div>
        </div>
        <input type="file" id="file_${field.id}" name="fields[${field.id}]" style="display:none;" onchange="handleFileSelect('${field.id}', this)" ${field.required ? 'required' : ''} multiple>
        <div class="file-list" id="fl_${field.id}"></div>`;
      setTimeout(() => setupFileDrop(field.id), 0);
      break;
    case 'address':
      wrap.innerHTML = `<label class="form-label" for="${field.id}">${esc(field.label)}${labelRequired}</label>
        <textarea class="form-textarea" id="${field.id}" name="fields[${field.id}]" placeholder="${esc(field.placeholder || 'Enter address...')}" rows="3" ${field.required ? 'required' : ''}></textarea>
        ${field.helpText ? `<div class="form-help">${esc(field.helpText)}</div>` : ''}
        <div class="field-error" id="err_${field.id}">This field is required.</div>`;
      setTimeout(() => {
        const inp = document.getElementById(field.id);
        if (inp) inp.addEventListener('input', () => { updateProgress(); clearError(field.id); });
      }, 0);
      break;
    case 'name':
      wrap.innerHTML = `<label class="form-label">${esc(field.label)}${labelRequired}</label>
        <div class="name-row">
          <input class="form-input" name="fields[${field.id}][first]" placeholder="First Name" ${field.required ? 'required' : ''}>
          <input class="form-input" name="fields[${field.id}][last]" placeholder="Last Name" ${field.required ? 'required' : ''}>
        </div>`;
      break;
    default:
      wrap.innerHTML = `<label class="form-label" for="${field.id}">${esc(field.label)}${labelRequired}</label>
        <input class="form-input" id="${field.id}" name="fields[${field.id}]" type="text" placeholder="${esc(field.placeholder || '')}" ${field.required ? 'required' : ''}>`;
  }
  return wrap;
}

function toggleSwitch(id) {
  const btn = document.getElementById('tgl_' + id);
  const val = document.getElementById('tgl_val_' + id);
  btn.classList.toggle('on');
  val.value = btn.classList.contains('on') ? '1' : '0';
}

function setRating(id, val) {
  document.querySelectorAll(`#rat_${id} .rating-star`).forEach(s => {
    s.classList.toggle('active', parseInt(s.dataset.val) <= val);
  });
  document.getElementById('rat_val_' + id).value = val;
  clearError(id); updateProgress();
}

function setScale(id, val) {
  document.querySelectorAll(`#scl_${id} .scale-num`).forEach(n => n.classList.toggle('selected', parseInt(n.dataset.val) === val));
  document.getElementById('scl_val_' + id).value = val;
  updateProgress();
}

function initSignature(id) {
  const canvas = document.getElementById('sig_' + id);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let drawing = false;
  ctx.strokeStyle = '#1a202c'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
  const getPos = e => {
    const r = canvas.getBoundingClientRect();
    const src = e.touches ? e.touches[0] : e;
    return { x: (src.clientX - r.left) * (canvas.width / r.width), y: (src.clientY - r.top) * (canvas.height / r.height) };
  };
  canvas.addEventListener('mousedown', e => { drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
  canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); document.getElementById('sig_val_' + id).value = canvas.toDataURL(); });
  canvas.addEventListener('mouseup', () => drawing = false);
  canvas.addEventListener('mouseleave', () => drawing = false);
  canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
  canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); document.getElementById('sig_val_' + id).value = canvas.toDataURL(); });
  canvas.addEventListener('touchend', () => drawing = false);
}

function clearSig(id) {
  const canvas = document.getElementById('sig_' + id);
  if (canvas) { canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); document.getElementById('sig_val_' + id).value = ''; }
}

function handleFileSelect(id, input) {
  const list = document.getElementById('fl_' + id);
  list.innerHTML = '';
  Array.from(input.files).forEach(file => {
    const item = document.createElement('div'); item.className = 'file-item';
    item.innerHTML = `📄 ${esc(file.name)} <span style="color:#a0aec0;font-size:11px;margin-left:6px;">${(file.size/1024).toFixed(0)} KB</span><span class="file-item-remove" onclick="this.parentElement.remove()">✕</span>`;
    list.appendChild(item);
  });
  updateProgress();
}

function setupFileDrop(id) {
  const zone = document.getElementById('fuz_' + id);
  if (!zone) return;
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('drag-over'); const input = document.getElementById('file_' + id); if (input) { input.files = e.dataTransfer.files; handleFileSelect(id, input); } });
}

function clearError(id) {
  const err = document.getElementById('err_' + id);
  if (err) { err.classList.remove('visible'); const inp = document.getElementById(id); if (inp) inp.classList.remove('error'); }
}

function showError(id, msg) {
  const err = document.getElementById('err_' + id);
  if (err) { err.textContent = msg || 'This field is required.'; err.classList.add('visible'); const inp = document.getElementById(id); if (inp) inp.classList.add('error'); }
}

function updateProgress() {
  let filled = 0;
  document.querySelectorAll('#formFields .form-field').forEach(wrap => {
    const inputs = wrap.querySelectorAll('input:not([type=hidden]):not([type=file]), textarea, select');
    inputs.forEach(inp => { if (inp.value && inp.value.trim()) filled++; });
  });
  const pct = totalFields > 0 ? Math.min(100, Math.round((filled / totalFields) * 100)) : 0;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressLabel').textContent = pct + '% complete';
}

// Form submission
document.getElementById('publicForm').addEventListener('submit', function(e) {
  e.preventDefault();
  let valid = true;
  // Basic required validation
  this.querySelectorAll('[required]').forEach(inp => {
    if (!inp.value || !inp.value.trim()) {
      const fieldWrap = inp.closest('.form-field');
      const fieldId = fieldWrap ? fieldWrap.dataset.fieldId : null;
      if (fieldId) showError(fieldId, 'This field is required.');
      inp.classList.add('error'); valid = false;
      if (valid === false && !document.querySelector('.error:focus')) inp.focus();
    }
  });
  if (!valid) return;
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = 'Submitting...';
  const formData = new FormData(this);
  fetch(this.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        document.getElementById('formCardHeader').style.display = 'none';
        document.getElementById('formCardBody').style.display = 'none';
        document.getElementById('successScreen').classList.add('visible');
        document.getElementById('progressFill').style.width = '100%';
        document.getElementById('progressLabel').textContent = '100% complete';
      } else {
        btn.disabled = false; btn.textContent = 'Submit Form';
        alert(data.message || 'An error occurred. Please try again.');
      }
    })
    .catch(() => {
      btn.disabled = false; btn.textContent = 'Submit Form';
      alert('Network error. Please check your connection and try again.');
    });
});

function esc(str) {
  const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML;
}

// Init
renderForm();
</script>
</body>
</html>
