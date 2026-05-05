<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $funnel->name }}</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; color: #111827; min-height: 100vh; }

/* Header */
.page-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 16px 24px; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.page-header-logo { width: 36px; height: 36px; background: #6366f1; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 15px; flex-shrink: 0; }
.page-header-title { font-size: 15px; font-weight: 700; color: #111827; }
.page-header-sub { font-size: 12px; color: #6b7280; margin-top: 1px; }

/* Progress bar */
.progress-bar-wrap { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 24px; }
.progress-steps { display: flex; align-items: center; gap: 0; max-width: 600px; margin: 0 auto; }
.progress-step { display: flex; align-items: center; flex: 1; }
.progress-step-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; transition: all 0.3s; }
.progress-step-dot.done { background: #22c55e; color: #fff; }
.progress-step-dot.active { background: #6366f1; color: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,0.2); }
.progress-step-dot.pending { background: #f3f4f6; color: #9ca3af; border: 2px solid #e5e7eb; }
.progress-step-label { font-size: 10px; color: #6b7280; margin-top: 4px; text-align: center; max-width: 60px; line-height: 1.2; }
.progress-step-connector { flex: 1; height: 2px; background: #e5e7eb; transition: background 0.3s; }
.progress-step-connector.done { background: #22c55e; }

/* Main content */
.main { max-width: 680px; margin: 32px auto; padding: 0 16px 60px; }

/* Form card */
.form-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; display: none; }
.form-card.active { display: block; }
.form-card-header { padding: 24px 28px 20px; border-bottom: 1px solid #f3f4f6; }
.form-card-step { font-size: 11px; font-weight: 600; color: #6366f1; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
.form-card-title { font-size: 20px; font-weight: 700; color: #111827; }
.form-card-desc { font-size: 13px; color: #6b7280; margin-top: 6px; line-height: 1.5; }
.form-card-body { padding: 24px 28px; }

/* Fields */
.field-group { margin-bottom: 20px; }
.field-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.field-label .required { color: #ef4444; margin-left: 2px; }
.field-help { font-size: 11px; color: #9ca3af; margin-top: 4px; }
.field-input { width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 9px; font-size: 14px; color: #111827; background: #f9fafb; outline: none; font-family: inherit; transition: border-color 0.15s, background 0.15s; }
.field-input:focus { border-color: #6366f1; background: #fff; }
.field-input.error { border-color: #ef4444; }
.field-error { font-size: 11px; color: #ef4444; margin-top: 4px; display: none; }
.field-row { display: grid; gap: 16px; }
.field-row.cols-2 { grid-template-columns: 1fr 1fr; }
.field-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }

/* Radio / Checkbox */
.choice-group { display: flex; flex-direction: column; gap: 8px; }
.choice-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 9px; cursor: pointer; transition: all 0.15s; }
.choice-item:hover { border-color: #6366f1; background: #f5f3ff; }
.choice-item input[type=radio]:checked + span, .choice-item input[type=checkbox]:checked + span { color: #6366f1; font-weight: 600; }
.choice-item:has(input:checked) { border-color: #6366f1; background: #f5f3ff; }
.choice-item input { accent-color: #6366f1; width: 16px; height: 16px; flex-shrink: 0; }

/* Signature */
.signature-wrap { border: 1.5px solid #e5e7eb; border-radius: 9px; overflow: hidden; background: #f9fafb; }
.signature-canvas { width: 100%; height: 140px; cursor: crosshair; display: block; }
.signature-actions { display: flex; gap: 8px; padding: 8px 12px; border-top: 1px solid #e5e7eb; background: #fff; }
.sig-btn { padding: 5px 12px; border-radius: 6px; border: 1px solid #e5e7eb; background: #f9fafb; color: #6b7280; font-size: 12px; cursor: pointer; }
.sig-btn:hover { background: #e5e7eb; }

/* Rating */
.rating-wrap { display: flex; gap: 6px; }
.rating-star { font-size: 28px; cursor: pointer; color: #e5e7eb; transition: color 0.1s; }
.rating-star.active { color: #f59e0b; }

/* Navigation buttons */
.form-card-footer { padding: 20px 28px; border-top: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; }
.btn-prev { background: #f3f4f6; color: #374151; }
.btn-prev:hover { background: #e5e7eb; }
.btn-next { background: #6366f1; color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
.btn-next:hover { background: #4f52d8; }
.btn-submit { background: #22c55e; color: #fff; box-shadow: 0 2px 8px rgba(34,197,94,0.3); }
.btn-submit:hover { background: #16a34a; }

/* Success screen */
.success-screen { display: none; text-align: center; padding: 60px 28px; }
.success-screen.visible { display: block; }
.success-icon { font-size: 64px; margin-bottom: 20px; }
.success-title { font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 8px; }
.success-sub { font-size: 15px; color: #6b7280; line-height: 1.6; }

/* Mobile */
@media (max-width: 600px) {
  .field-row.cols-2, .field-row.cols-3 { grid-template-columns: 1fr; }
  .form-card-header, .form-card-body, .form-card-footer { padding-left: 18px; padding-right: 18px; }
  .progress-step-label { display: none; }
}
</style>
</head>
<body>

<!-- Header -->
<div class="page-header">
  <div class="page-header-logo">A</div>
  <div>
    <div class="page-header-title">{{ $funnel->name }}</div>
    @if($funnel->description)
    <div class="page-header-sub">{{ $funnel->description }}</div>
    @endif
  </div>
</div>

<!-- Progress Steps -->
@if($orderedForms->count() > 1)
<div class="progress-bar-wrap">
  <div class="progress-steps" id="progressSteps">
    @foreach($orderedForms as $i => $form)
      @if($i > 0)
        <div class="progress-step-connector" id="conn_{{ $i }}"></div>
      @endif
      <div class="progress-step" style="flex-direction:column;align-items:center;flex:0 0 auto;">
        <div class="progress-step-dot {{ $i === 0 ? 'active' : 'pending' }}" id="dot_{{ $i }}">{{ $i + 1 }}</div>
        <div class="progress-step-label">{{ Str::limit($form->name, 12) }}</div>
      </div>
    @endforeach
  </div>
</div>
@endif

<!-- Main -->
<div class="main">

  @foreach($orderedForms as $i => $form)
  <div class="form-card {{ $i === 0 ? 'active' : '' }}" id="formCard_{{ $i }}">
    <div class="form-card-header">
      <div class="form-card-step">Step {{ $i + 1 }} of {{ $orderedForms->count() }}</div>
      <div class="form-card-title">{{ $form->name }}</div>
      @if($form->description)
      <div class="form-card-desc">{{ $form->description }}</div>
      @endif
    </div>
    <div class="form-card-body" id="formBody_{{ $i }}">
      @php
        $schema = $form->fields ?? [];
        $rows = is_array($schema) ? ($schema['rows'] ?? []) : [];
      @endphp
      @if(empty($rows))
        <div style="text-align:center;padding:32px;color:#9ca3af;">
          <div style="font-size:32px;margin-bottom:8px;">📋</div>
          <div style="font-size:14px;">This form has no fields yet.</div>
        </div>
      @else
        @foreach($rows as $row)
          @php $cols = $row['cols'] ?? []; $colCount = count($cols); @endphp
          <div class="field-row {{ $colCount > 1 ? 'cols-' . $colCount : '' }}">
            @foreach($cols as $col)
              @foreach($col['fields'] ?? [] as $field)
                @php
                  $fid = $field['id'] ?? uniqid();
                  $fname = 'form_' . $form->id . '[' . $fid . ']';
                  $label = $field['label'] ?? 'Field';
                  $placeholder = $field['placeholder'] ?? '';
                  $required = $field['required'] ?? false;
                  $helpText = $field['helpText'] ?? '';
                  $type = $field['type'] ?? 'text';
                  $options = $field['options'] ?? [];
                @endphp
                <div class="field-group">
                  @if(!in_array($type, ['header', 'paragraph', 'divider', 'submit']))
                  <label class="field-label" for="{{ $fid }}">
                    {{ $label }}@if($required)<span class="required">*</span>@endif
                  </label>
                  @endif

                  @if($type === 'text' || $type === 'email' || $type === 'phone' || $type === 'number' || $type === 'password')
                    <input type="{{ $type === 'phone' ? 'tel' : $type }}" id="{{ $fid }}" name="{{ $fname }}" class="field-input" placeholder="{{ $placeholder }}" {{ $required ? 'required' : '' }}>
                  @elseif($type === 'date')
                    <input type="date" id="{{ $fid }}" name="{{ $fname }}" class="field-input" {{ $required ? 'required' : '' }}>
                  @elseif($type === 'time')
                    <input type="time" id="{{ $fid }}" name="{{ $fname }}" class="field-input" {{ $required ? 'required' : '' }}>
                  @elseif($type === 'textarea')
                    <textarea id="{{ $fid }}" name="{{ $fname }}" class="field-input" placeholder="{{ $placeholder }}" rows="4" style="resize:vertical;" {{ $required ? 'required' : '' }}></textarea>
                  @elseif($type === 'dropdown')
                    <select id="{{ $fid }}" name="{{ $fname }}" class="field-input" {{ $required ? 'required' : '' }}>
                      <option value="">{{ $placeholder ?: 'Select an option...' }}</option>
                      @foreach($options as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                      @endforeach
                    </select>
                  @elseif($type === 'radio')
                    <div class="choice-group">
                      @foreach($options as $opt)
                      <label class="choice-item">
                        <input type="radio" name="{{ $fname }}" value="{{ $opt }}" {{ $required ? 'required' : '' }}>
                        <span>{{ $opt }}</span>
                      </label>
                      @endforeach
                    </div>
                  @elseif($type === 'checkbox')
                    <div class="choice-group">
                      @foreach($options as $opt)
                      <label class="choice-item">
                        <input type="checkbox" name="{{ $fname }}[]" value="{{ $opt }}">
                        <span>{{ $opt }}</span>
                      </label>
                      @endforeach
                    </div>
                  @elseif($type === 'toggle')
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                      <input type="hidden" name="{{ $fname }}" value="0">
                      <input type="checkbox" name="{{ $fname }}" value="1" id="{{ $fid }}" style="width:40px;height:22px;accent-color:#6366f1;">
                      <span style="font-size:13px;color:#374151;">{{ $placeholder ?: 'Yes' }}</span>
                    </label>
                  @elseif($type === 'signature')
                    <div class="signature-wrap">
                      <canvas class="signature-canvas" id="sig_{{ $fid }}" data-field="{{ $fid }}"></canvas>
                      <input type="hidden" name="{{ $fname }}" id="sigData_{{ $fid }}">
                      <div class="signature-actions">
                        <button type="button" class="sig-btn" onclick="clearSig('{{ $fid }}')">✕ Clear</button>
                        <span style="font-size:11px;color:#9ca3af;margin-left:auto;">Sign above</span>
                      </div>
                    </div>
                  @elseif($type === 'file')
                    <div style="border:2px dashed #e5e7eb;border-radius:9px;padding:24px;text-align:center;cursor:pointer;background:#f9fafb;" onclick="document.getElementById('{{ $fid }}').click()">
                      <div style="font-size:24px;margin-bottom:6px;">📎</div>
                      <div style="font-size:13px;color:#374151;font-weight:500;">Click to upload or drag & drop</div>
                      <div style="font-size:11px;color:#9ca3af;margin-top:4px;">Any file type accepted</div>
                      <input type="file" id="{{ $fid }}" name="{{ $fname }}" style="display:none;" {{ $required ? 'required' : '' }}>
                    </div>
                  @elseif($type === 'rating')
                    <div class="rating-wrap" id="rating_{{ $fid }}">
                      @for($s = 1; $s <= 5; $s++)
                        <span class="rating-star" onclick="setRating('{{ $fid }}', {{ $s }})">★</span>
                      @endfor
                    </div>
                    <input type="hidden" name="{{ $fname }}" id="{{ $fid }}" value="">
                  @elseif($type === 'header')
                    <div style="font-size:18px;font-weight:700;color:#111827;padding:8px 0 4px;">{{ $label }}</div>
                  @elseif($type === 'paragraph')
                    <div style="font-size:13px;color:#6b7280;line-height:1.6;padding:4px 0;">{{ $label }}</div>
                  @elseif($type === 'divider')
                    <hr style="border:none;border-top:1px solid #e5e7eb;margin:8px 0;">
                  @elseif($type === 'fullname')
                    <div class="field-row cols-2">
                      <input type="text" name="{{ $fname }}[first]" class="field-input" placeholder="First name" {{ $required ? 'required' : '' }}>
                      <input type="text" name="{{ $fname }}[last]" class="field-input" placeholder="Last name" {{ $required ? 'required' : '' }}>
                    </div>
                  @elseif($type === 'address')
                    <div style="display:flex;flex-direction:column;gap:10px;">
                      <input type="text" name="{{ $fname }}[street]" class="field-input" placeholder="Street address" {{ $required ? 'required' : '' }}>
                      <div class="field-row cols-2">
                        <input type="text" name="{{ $fname }}[city]" class="field-input" placeholder="City">
                        <input type="text" name="{{ $fname }}[state]" class="field-input" placeholder="State">
                      </div>
                      <div class="field-row cols-2">
                        <input type="text" name="{{ $fname }}[zip]" class="field-input" placeholder="ZIP code">
                        <input type="text" name="{{ $fname }}[country]" class="field-input" placeholder="Country">
                      </div>
                    </div>
                  @elseif($type === 'scale')
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                      @for($n = 1; $n <= 10; $n++)
                        <label style="cursor:pointer;">
                          <input type="radio" name="{{ $fname }}" value="{{ $n }}" style="display:none;" {{ $required ? 'required' : '' }}>
                          <span class="scale-btn" style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:8px;border:1.5px solid #e5e7eb;font-size:13px;font-weight:600;color:#374151;cursor:pointer;transition:all 0.15s;"
                            onmouseover="this.style.borderColor='#6366f1';this.style.background='#f5f3ff';"
                            onmouseout="this.style.borderColor='';this.style.background='';">{{ $n }}</span>
                        </label>
                      @endfor
                    </div>
                  @endif

                  @if($helpText)
                  <div class="field-help">{{ $helpText }}</div>
                  @endif
                </div>
              @endforeach
            @endforeach
          </div>
        @endforeach
      @endif
    </div>
    <div class="form-card-footer">
      <div></div>
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:12px;color:#9ca3af;">{{ $i + 1 }} / {{ $orderedForms->count() }}</span>
        @if($i < $orderedForms->count() - 1)
        <button type="button" class="btn btn-next" id="nextBtn_{{ $i }}" onclick="submitStep({{ $i }}, {{ $form->id }}, {{ $i + 1 }})">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Submit
        </button>
        @else
        <button type="button" class="btn btn-submit" id="submitBtn_{{ $i }}" onclick="submitStep({{ $i }}, {{ $form->id }}, null)">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Submit
        </button>
        @endif
      </div>
    </div>
  </div>
  @endforeach

  <!-- Success Screen -->
  <div class="form-card" id="successCard">
    <div class="success-screen visible">
      <div class="success-icon">🎉</div>
      <div class="success-title">All Done!</div>
      <div class="success-sub">Thank you for completing all the forms.<br>Our team will review your submissions and get back to you shortly.</div>
    </div>
  </div>

</div>

<script>
const totalSteps = {{ $orderedForms->count() }};
let currentStep = 0;

function goToStep(step) {
  if (step < 0 || step >= totalSteps) return;
  document.getElementById('formCard_' + currentStep).classList.remove('active');
  updateProgress(currentStep, step);
  currentStep = step;
  document.getElementById('formCard_' + currentStep).classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateProgress(from, to) {
  for (let i = 0; i < totalSteps; i++) {
    const dot = document.getElementById('dot_' + i);
    if (!dot) continue;
    if (i < to) { dot.className = 'progress-step-dot done'; dot.innerHTML = '✓'; }
    else if (i === to) { dot.className = 'progress-step-dot active'; dot.innerHTML = i + 1; }
    else { dot.className = 'progress-step-dot pending'; dot.innerHTML = i + 1; }
    const conn = document.getElementById('conn_' + i);
    if (conn) conn.className = 'progress-step-connector' + (i <= to ? ' done' : '');
  }
}

/**
 * Collect all inputs from a specific form card and submit them to the per-step endpoint.
 * stepIndex: the 0-based index of the current step (for UI transitions)
 * formId:    the DB id of the form being submitted
 * nextStep:  the step to advance to after success (null = last step → show success screen)
 */
function validateStep(stepIndex) {
  const card = document.getElementById('formCard_' + stepIndex);
  let valid = true;

  // Clear previous error states
  card.querySelectorAll('.field-input.error').forEach(el => el.classList.remove('error'));
  card.querySelectorAll('.field-error').forEach(el => { el.style.display = 'none'; el.textContent = ''; });
  card.querySelectorAll('.choice-group.error').forEach(el => el.classList.remove('error'));

  // Helper: get the label text for a field input
  function getFieldLabel(input) {
    const fieldGroup = input.closest('.field-group');
    if (fieldGroup) {
      const labelEl = fieldGroup.querySelector('.field-label');
      if (labelEl) {
        // Clone to remove the * span and get clean text
        const clone = labelEl.cloneNode(true);
        clone.querySelectorAll('.required').forEach(s => s.remove());
        return clone.textContent.trim();
      }
    }
    return null;
  }

  // Check each required field
  card.querySelectorAll('[required]').forEach(input => {
    if (input.type === 'radio') return; // handled separately per group
    if (input.type === 'hidden') return;
    let empty = false;
    if (input.type === 'checkbox') {
      // For required checkbox groups, at least one must be checked
      const name = input.name;
      const checked = card.querySelectorAll('[name="' + name + '"]:checked').length;
      if (checked === 0) empty = true;
    } else {
      empty = !input.value || input.value.trim() === '';
    }
    if (empty) {
      valid = false;
      input.classList.add('error');
      // Show inline error message with field name
      const label = getFieldLabel(input);
      let errEl = input.parentElement.querySelector('.field-error');
      if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error';
        input.parentElement.appendChild(errEl);
      }
      errEl.textContent = label ? label + ' is required.' : 'This field is required.';
      errEl.style.display = 'block';
    }
  });

  // Check required radio groups
  const radioGroups = {};
  card.querySelectorAll('input[type="radio"][required]').forEach(r => {
    radioGroups[r.name] = radioGroups[r.name] || [];
    radioGroups[r.name].push(r);
  });
  Object.values(radioGroups).forEach(radios => {
    const checked = radios.some(r => r.checked);
    if (!checked) {
      valid = false;
      const group = radios[0].closest('.choice-group');
      if (group) {
        group.style.outline = '2px solid #ef4444';
        group.style.borderRadius = '9px';
        const label = getFieldLabel(radios[0]);
        let errEl = group.parentElement.querySelector('.field-error');
        if (!errEl) {
          errEl = document.createElement('div');
          errEl.className = 'field-error';
          group.parentElement.appendChild(errEl);
        }
        errEl.textContent = label ? label + ' is required.' : 'Please select an option.';
        errEl.style.display = 'block';
      }
    }
  });

  // Scroll to first error
  if (!valid) {
    const firstError = card.querySelector('.field-input.error, .choice-group[style*="outline"]');
    if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  return valid;
}

function submitStep(stepIndex, formId, nextStep) {
  // Validate required fields before submitting
  if (!validateStep(stepIndex)) return;

  const btnId = nextStep !== null ? 'nextBtn_' + stepIndex : 'submitBtn_' + stepIndex;
  const btn = document.getElementById(btnId);
  if (btn) { btn.disabled = true; btn.style.opacity = '0.7'; }

  const card = document.getElementById('formCard_' + stepIndex);
  const formData = new FormData();
  formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  // Flush signature canvases in this card
  card.querySelectorAll('.signature-canvas').forEach(canvas => {
    const fieldId = canvas.dataset.field;
    const hiddenInput = document.getElementById('sigData_' + fieldId);
    if (hiddenInput && canvas.dataset.empty !== 'true') {
      hiddenInput.value = canvas.toDataURL('image/png');
    }
  });

  // Gather inputs only from this card
  // First pass: collect all checkbox arrays and regular values into a plain object
  const fieldValues = {};

  card.querySelectorAll('[name]').forEach(input => {
    if (!input.name || input.name === '_token') return;
    if (input.type === 'radio' && !input.checked) return;
    if (input.type === 'file') {
      if (input.files && input.files[0]) formData.append('fields[' + input.id + ']', input.files[0]);
      return;
    }

    // Detect checkbox array: name="form_X[fieldId][]" or name="form_X[fieldId][subkey]"
    const arrMatch = input.name.match(/^form_\d+\[(.+?)\]\[(.*)\]$/);
    const singleMatch = input.name.match(/^form_\d+\[(.+?)\]$/);

    if (arrMatch) {
      // Checkbox array or nested field (fullname, address)
      const fieldId = arrMatch[1];
      const subKey = arrMatch[2]; // empty string for checkbox[], or 'first'/'last' etc.
      if (input.type === 'checkbox') {
        if (!input.checked) return;
        // Ensure it's always an array before pushing
        if (!Array.isArray(fieldValues[fieldId])) fieldValues[fieldId] = [];
        fieldValues[fieldId].push(input.value);
      } else {
        // Nested object field (fullname[first], address[street], etc.)
        if (typeof fieldValues[fieldId] !== 'object' || Array.isArray(fieldValues[fieldId])) {
          fieldValues[fieldId] = {};
        }
        fieldValues[fieldId][subKey] = input.value;
      }
    } else if (singleMatch) {
      const fieldId = singleMatch[1];
      if (input.type === 'checkbox') {
        // Single toggle-style checkbox
        if (!Array.isArray(fieldValues[fieldId])) fieldValues[fieldId] = [];
        if (input.checked) fieldValues[fieldId].push(input.value);
      } else {
        fieldValues[fieldId] = input.value;
      }
    } else {
      // Fallback: non-remapped fields
      formData.append(input.name, input.value);
    }
  });

  // Second pass: append collected field values to formData
  Object.entries(fieldValues).forEach(([fieldId, value]) => {
    if (Array.isArray(value)) {
      value.forEach(v => formData.append('fields[' + fieldId + '][]', v));
    } else if (typeof value === 'object' && value !== null) {
      Object.entries(value).forEach(([subKey, v]) => {
        formData.append('fields[' + fieldId + '][' + subKey + ']', v);
      });
    } else {
      formData.append('fields[' + fieldId + ']', value);
    }
  });

  fetch('/funnel/{{ $funnel->slug }}/submit-step/' + formId, {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    if (data.status === 'success') {
      if (nextStep !== null) {
        goToStep(nextStep);
      } else {
        // Last step — show success screen
        for (let i = 0; i < totalSteps; i++) {
          const c = document.getElementById('formCard_' + i);
          if (c) c.classList.remove('active');
        }
        document.getElementById('successCard').classList.add('active');
        for (let i = 0; i < totalSteps; i++) {
          const dot = document.getElementById('dot_' + i);
          if (dot) { dot.className = 'progress-step-dot done'; dot.innerHTML = '✓'; }
          const conn = document.getElementById('conn_' + i);
          if (conn) conn.className = 'progress-step-connector done';
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    } else {
      alert(data.message || 'An error occurred. Please try again.');
    }
  })
  .catch(() => {
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    alert('An error occurred. Please try again.');
  });
}

// ─── Signature pads ──────────────────────────────────────────
document.querySelectorAll('.signature-canvas').forEach(canvas => {
  const ctx = canvas.getContext('2d');
  let drawing = false;
  canvas.dataset.empty = 'true';

  function getPos(e) {
    const r = canvas.getBoundingClientRect();
    const src = e.touches ? e.touches[0] : e;
    return { x: (src.clientX - r.left) * (canvas.width / r.width), y: (src.clientY - r.top) * (canvas.height / r.height) };
  }

  function resize() { canvas.width = canvas.offsetWidth; canvas.height = 140; }
  resize();

  canvas.addEventListener('mousedown', e => { drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); canvas.dataset.empty = 'false'; });
  canvas.addEventListener('mousemove', e => { if (!drawing) return; const p = getPos(e); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111827'; ctx.lineTo(p.x, p.y); ctx.stroke(); });
  canvas.addEventListener('mouseup', () => drawing = false);
  canvas.addEventListener('mouseleave', () => drawing = false);
  canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); canvas.dataset.empty = 'false'; }, { passive: false });
  canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111827'; ctx.lineTo(p.x, p.y); ctx.stroke(); }, { passive: false });
  canvas.addEventListener('touchend', () => drawing = false);
});

function clearSig(fieldId) {
  const canvas = document.querySelector('[data-field="' + fieldId + '"]');
  if (canvas) { const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, canvas.width, canvas.height); canvas.dataset.empty = 'true'; }
}

// ─── Rating stars ─────────────────────────────────────────────
function setRating(fieldId, value) {
  document.getElementById(fieldId).value = value;
  const stars = document.querySelectorAll('#rating_' + fieldId + ' .rating-star');
  stars.forEach((s, i) => s.classList.toggle('active', i < value));
}
</script>
</body>
</html>
