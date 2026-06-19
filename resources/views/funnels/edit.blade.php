<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Edit Funnel — AdvantageHCS Admin</title>
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; color: #111827; overflow: hidden; }
.topbar { height: 56px; background: #fff; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; padding: 0 20px; gap: 16px; position: fixed; top: 0; left: 0; right: 0; z-index: 100; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.topbar-logo { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 14px; color: #111827; text-decoration: none; }
.topbar-logo span { width: 38px; height: 38px; background: #C8102E; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0; }
.topbar-divider { width: 1px; height: 22px; background: #e5e7eb; }
.topbar-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #6b7280; }
.topbar-breadcrumb a { color: #6b7280; text-decoration: none; } .topbar-breadcrumb a:hover { color: #6366f1; }
.topbar-breadcrumb .sep { color: #d1d5db; }
.topbar-breadcrumb .current { color: #111827; font-weight: 600; }
.topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; }
.btn-ghost { background: transparent; color: #6b7280; border: 1px solid #e5e7eb; }
.btn-ghost:hover { background: #f9fafb; color: #374151; }
.btn-primary { background: #6366f1; color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
.btn-primary:hover { background: #4f52d8; }
.btn-success { background: #22c55e; color: #fff; }
.btn-success:hover { background: #16a34a; }
.layout { display: flex; height: calc(100vh - 56px); margin-top: 56px; }
.canvas-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #f0f2f5; }
.canvas-panel-header { padding: 16px 20px; background: #fff; border-bottom: 1px solid #e5e7eb; display: flex; flex-direction: column; gap: 12px; }
.canvas-panel-header h2 { font-size: 15px; font-weight: 700; color: #111827; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 600; color: #374151; }
.form-input { padding: 9px 12px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 13px; color: #111827; background: #f9fafb; outline: none; font-family: inherit; transition: border-color 0.15s; }
.form-input:focus { border-color: #6366f1; background: #fff; }
.form-textarea { resize: vertical; height: 70px; }
.canvas-body { flex: 1; overflow-y: auto; padding: 20px; }
.funnel-canvas { min-height: 300px; background: #fff; border-radius: 12px; border: 2px dashed #e5e7eb; padding: 16px; display: flex; flex-direction: column; gap: 0; transition: border-color 0.2s; }
.funnel-canvas.drag-over { border-color: #6366f1; background: #f5f3ff; }
.funnel-canvas-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 20px; color: #9ca3af; text-align: center; }
.funnel-canvas-empty-icon { font-size: 48px; margin-bottom: 12px; }
.funnel-canvas-empty-title { font-size: 15px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.funnel-canvas-empty-sub { font-size: 13px; }
.funnel-step { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 10px; margin-bottom: 8px; cursor: grab; transition: all 0.15s; }
.funnel-step:hover { border-color: #6366f1; background: #f5f3ff; box-shadow: 0 2px 8px rgba(99,102,241,0.1); }
.funnel-step.dragging { opacity: 0.4; }
.funnel-step-drag { color: #d1d5db; cursor: grab; flex-shrink: 0; font-size: 18px; line-height: 1; user-select: none; }
.funnel-step-num { width: 28px; height: 28px; border-radius: 50%; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
.funnel-step-info { flex: 1; min-width: 0; }
.funnel-step-name { font-size: 13px; font-weight: 600; color: #111827; }
.funnel-step-meta { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.funnel-step-actions { display: flex; gap: 4px; flex-shrink: 0; }
.funnel-step-btn { width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s; }
.funnel-step-btn-move { background: #f3f4f6; color: #6b7280; }
.funnel-step-btn-move:hover { background: #e5e7eb; }
.funnel-step-btn-del { background: #fee2e2; color: #ef4444; }
.funnel-step-btn-del:hover { background: #fecaca; }
.funnel-connector { display: flex; align-items: center; justify-content: center; height: 24px; color: #9ca3af; font-size: 18px; margin: -4px 0; }
.funnel-submit-preview { margin-top: 8px; padding: 12px 16px; background: #6366f1; color: #fff; border-radius: 8px; text-align: center; font-size: 13px; font-weight: 600; opacity: 0.7; }
.library-panel { width: 320px; background: #fff; border-left: 1px solid #e5e7eb; display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0; }
.library-header { padding: 16px; border-bottom: 1px solid #e5e7eb; }
.library-header h3 { font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 10px; }
.library-search { position: relative; }
.library-search svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
.library-search input { width: 100%; padding: 8px 12px 8px 32px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 12px; color: #374151; background: #f9fafb; outline: none; font-family: inherit; }
.library-search input:focus { border-color: #6366f1; background: #fff; }
.library-body { flex: 1; overflow-y: auto; padding: 8px; }
.library-form-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: all 0.15s; margin-bottom: 2px; border: 1.5px solid transparent; }
.library-form-item:hover { background: #f5f3ff; border-color: #c7d2fe; }
.library-form-item.added { background: #f0fdf4; border-color: #86efac; cursor: default; }
.library-form-icon { width: 32px; height: 32px; border-radius: 7px; background: #ede9fe; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; }
.library-form-item.added .library-form-icon { background: #dcfce7; }
.library-form-info { flex: 1; min-width: 0; }
.library-form-name { font-size: 12px; font-weight: 600; color: #111827; line-height: 1.3; }
.library-form-status { font-size: 10px; color: #9ca3af; margin-top: 2px; }
.library-form-add { width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; font-weight: 700; transition: all 0.15s; }
.library-form-add-btn { background: #ede9fe; color: #6366f1; }
.library-form-add-btn:hover { background: #6366f1; color: #fff; }
.library-form-added-badge { background: #dcfce7; color: #16a34a; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; pointer-events: none; }
::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }

/* Toast */
#funnel-toast-container { position:fixed; top:56px; right:0; z-index:99999; display:flex; flex-direction:column; gap:0; pointer-events:none; }
.f-toast { display:flex; align-items:center; gap:10px; padding:16px 24px; font-size:14px; font-weight:600; color:#fff; min-width:300px; max-width:460px; box-shadow:0 4px 16px rgba(0,0,0,0.18); pointer-events:all; animation:fToastIn .3s ease; border-radius:0 0 0 8px; }
.f-toast.success { background:#16a34a; }
.f-toast.error   { background:#dc2626; }
@keyframes fToastIn  { from { transform:translateY(-100%); opacity:0; } to { transform:translateY(0); opacity:1; } }
@keyframes fToastOut { from { transform:translateY(0); opacity:1; } to { transform:translateY(-100%); opacity:0; } }
.f-toast.hide { animation:fToastOut .3s ease forwards; }
</style>
</head>
<body>

<!-- Toast Container -->
<div id="funnel-toast-container"></div>

<div class="topbar">
  <a href="{{ route('dashboard') }}" class="topbar-logo"><span>A</span> AdvantageHCS <span style="font-size:10px; font-weight:600; background:#f3f4f6; color:#6b7280; border-radius:4px; padding:2px 7px; margin-left:2px;">Admin</span></a>
  <div class="topbar-divider"></div>
  <div class="topbar-breadcrumb">
    <a href="{{ route('funnels.index') }}">← Funnels</a>
    <span class="sep">›</span>
    <span class="current">Edit: {{ Str::limit($funnel->name, 40) }}</span>
  </div>
  <div class="topbar-actions">
    <a href="{{ route('funnels.index') }}" class="btn btn-ghost">Cancel</a>
    <button class="btn btn-success" onclick="saveFunnel('active')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Publish
    </button>
  </div>
</div>

<div class="layout">
  <div class="canvas-panel">
    <div class="canvas-panel-header">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h2>Funnel Builder</h2>
        <span id="stepCount" style="font-size:12px;color:#6b7280;background:#f3f4f6;padding:4px 10px;border-radius:20px;">0 forms</span>
      </div>
      @if($funnel->status === 'draft')
      <div style="padding:8px 12px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;font-size:12px;color:#d97706;">
        ⚠️ This funnel is a draft. Publish it to generate a public URL.
      </div>
      @endif
      <div style="display:flex;gap:12px;">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Funnel Name <span style="color:#ef4444;">*</span></label>
          <input type="text" class="form-input" id="funnelName" value="{{ $funnel->name }}" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Insurance Type</label>
        <select class="form-input" id="funnelInsuranceType">
          <option value="">Select Insurance Type</option>
          <option value="PI" {{ $funnel->insurance_type === 'PI' ? 'selected' : '' }}>Auto/Pers Inj</option>
          <option value="CASH" {{ $funnel->insurance_type === 'CASH' ? 'selected' : '' }}>Cash</option>
          <option value="WC" {{ $funnel->insurance_type === 'WC' ? 'selected' : '' }}>Wk Comp</option>
          <option value="DOL" {{ $funnel->insurance_type === 'DOL' ? 'selected' : '' }}>DOL</option>
          <option value="COMM" {{ $funnel->insurance_type === 'COMM' ? 'selected' : '' }}>Comm Ins</option>
          <option value="MC" {{ $funnel->insurance_type === 'MC' ? 'selected' : '' }}>MC</option>
          <option value="TriWest" {{ $funnel->insurance_type === 'TriWest' ? 'selected' : '' }}>TriWest</option>
          <option value="ALL" {{ $funnel->insurance_type === 'ALL' ? 'selected' : '' }}>All</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input form-textarea" id="funnelDesc">{{ $funnel->description }}</textarea>
      </div>
    </div>
    <div class="canvas-body">
      <div class="funnel-canvas" id="funnelCanvas"
        ondragover="event.preventDefault(); this.classList.add('drag-over')"
        ondragleave="this.classList.remove('drag-over')"
        ondrop="handleCanvasDrop(event)">
        <div class="funnel-canvas-empty" id="canvasEmpty" style="display:none;">
          <div class="funnel-canvas-empty-icon">🔗</div>
          <div class="funnel-canvas-empty-title">No forms added yet</div>
          <div class="funnel-canvas-empty-sub">Click a form from the right panel to add it.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="library-panel">
    <div class="library-header">
      <h3>📋 Available Forms</h3>
      <div class="library-search">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" id="librarySearch" placeholder="Search forms..." oninput="filterLibrary(this.value)">
      </div>
    </div>
    <div class="library-body">
      @forelse($forms as $form)
      <div class="library-form-item" id="lib_{{ $form->id }}"
        onclick="addFormToFunnel({{ $form->id }}, '{{ addslashes($form->name) }}', '{{ $form->status }}', '{{ $form->slug ?? '' }}')"
        draggable="true"
        ondragstart="handleLibraryDragStart(event, {{ $form->id }}, '{{ addslashes($form->name) }}', '{{ $form->status }}', '{{ $form->slug ?? '' }}')">
        <div class="library-form-icon">📝</div>
        <div class="library-form-info">
          <div class="library-form-name">{{ $form->name }}</div>
          <div class="library-form-status">{{ ucfirst($form->status) }}</div>
        </div>
        <button class="library-form-add library-form-add-btn" id="addbtn_{{ $form->id }}" title="Add to funnel">+</button>
      </div>
      @empty
      <div style="padding:32px 16px;text-align:center;color:#9ca3af;">
        <div style="font-size:32px;margin-bottom:8px;">📋</div>
        <div style="font-size:13px;">No forms found.</div>
      </div>
      @endforelse
    </div>
  </div>
</div>

<form id="funnelForm" method="POST" action="{{ route('funnels.update', $funnel) }}" style="display:none;">
  @csrf @method('PUT')
  <input type="hidden" name="name" id="hiddenName">
  <input type="hidden" name="description" id="hiddenDesc">
  <input type="hidden" name="insurance_type" id="hiddenInsuranceType">
  <input type="hidden" name="status" id="hiddenStatus" value="draft">
  <input type="hidden" name="form_ids" id="hiddenFormIds">
</form>


<script>
let funnelSteps = @json($existingSteps ?? []);
let dragSrcStepId = null;

// Mark already-added forms and render canvas on load
funnelSteps.forEach(step => markLibraryItem(step.id, true));
renderCanvas();

function addFormToFunnel(id, name, status, slug) {
  if (funnelSteps.find(s => s.id === id)) {
    const el = document.getElementById('step_' + id);
    if (el) { el.style.borderColor = '#f59e0b'; el.style.background = '#fffbeb'; setTimeout(() => { el.style.borderColor = ''; el.style.background = ''; }, 800); }
    return;
  }
  funnelSteps.push({ id, name, status, slug });
  renderCanvas();
  markLibraryItem(id, true);
}

function removeFormFromFunnel(id) {
  funnelSteps = funnelSteps.filter(s => s.id !== id);
  renderCanvas();
  markLibraryItem(id, false);
}

function moveStep(id, dir) {
  const idx = funnelSteps.findIndex(s => s.id === id);
  if (dir === 'up' && idx > 0) [funnelSteps[idx-1], funnelSteps[idx]] = [funnelSteps[idx], funnelSteps[idx-1]];
  else if (dir === 'down' && idx < funnelSteps.length - 1) [funnelSteps[idx], funnelSteps[idx+1]] = [funnelSteps[idx+1], funnelSteps[idx]];
  renderCanvas();
}

function renderCanvas() {
  const canvas = document.getElementById('funnelCanvas');
  const empty = document.getElementById('canvasEmpty');
  document.getElementById('stepCount').textContent = funnelSteps.length + ' form' + (funnelSteps.length !== 1 ? 's' : '');
  canvas.querySelectorAll('.funnel-step, .funnel-connector, .funnel-submit-preview').forEach(el => el.remove());
  if (funnelSteps.length === 0) { empty.style.display = 'flex'; return; }
  empty.style.display = 'none';
  funnelSteps.forEach((step, i) => {
    if (i > 0) { const conn = document.createElement('div'); conn.className = 'funnel-connector'; conn.innerHTML = '↓'; canvas.appendChild(conn); }
    const el = document.createElement('div');
    el.className = 'funnel-step'; el.id = 'step_' + step.id; el.draggable = true; el.dataset.stepId = step.id;
    el.innerHTML = `<span class="funnel-step-drag" title="Drag to reorder">⠿</span>
      <div class="funnel-step-num">${i + 1}</div>
      <div class="funnel-step-info">
        <div class="funnel-step-name">${escHtml(step.name)}</div>
        <div class="funnel-step-meta">Step ${i + 1} of ${funnelSteps.length} &nbsp;·&nbsp; ${step.status === 'active' ? '✅ Active' : '⚠️ Draft'}</div>
      </div>
      <div class="funnel-step-actions">
        ${i > 0 ? `<button class="funnel-step-btn funnel-step-btn-move" onclick="moveStep(${step.id}, 'up')" title="Move up">↑</button>` : ''}
        ${i < funnelSteps.length - 1 ? `<button class="funnel-step-btn funnel-step-btn-move" onclick="moveStep(${step.id}, 'down')" title="Move down">↓</button>` : ''}
        <button class="funnel-step-btn funnel-step-btn-del" onclick="removeFormFromFunnel(${step.id})" title="Remove">✕</button>
      </div>`;
    el.addEventListener('dragstart', e => { dragSrcStepId = step.id; e.dataTransfer.effectAllowed = 'move'; el.classList.add('dragging'); });
    el.addEventListener('dragend', () => { el.classList.remove('dragging'); dragSrcStepId = null; });
    el.addEventListener('dragover', e => { e.preventDefault(); el.style.background = '#f5f3ff'; });
    el.addEventListener('dragleave', () => el.style.background = '');
    el.addEventListener('drop', e => {
      e.preventDefault(); el.style.background = '';
      if (dragSrcStepId && dragSrcStepId !== step.id) {
        const fromIdx = funnelSteps.findIndex(s => s.id === dragSrcStepId);
        const toIdx = funnelSteps.findIndex(s => s.id === step.id);
        const [moved] = funnelSteps.splice(fromIdx, 1);
        funnelSteps.splice(toIdx, 0, moved);
        renderCanvas();
      }
    });
    canvas.appendChild(el);
  });
  const submitPreview = document.createElement('div');
  submitPreview.className = 'funnel-submit-preview';
  submitPreview.innerHTML = '🚀 Submit Form (End of Funnel)';
  canvas.appendChild(submitPreview);
}

function markLibraryItem(id, added) {
  const item = document.getElementById('lib_' + id);
  const btn = document.getElementById('addbtn_' + id);
  if (!item || !btn) return;
  if (added) { item.classList.add('added'); btn.className = 'library-form-add library-form-added-badge'; btn.textContent = '✓'; }
  else { item.classList.remove('added'); btn.className = 'library-form-add library-form-add-btn'; btn.textContent = '+'; }
}

function handleCanvasDrop(event) {
  event.preventDefault();
  document.getElementById('funnelCanvas').classList.remove('drag-over');
  const data = event.dataTransfer.getData('libraryForm');
  if (data) { const f = JSON.parse(data); addFormToFunnel(f.id, f.name, f.status, f.slug); }
}

function handleLibraryDragStart(event, id, name, status, slug) {
  event.dataTransfer.setData('libraryForm', JSON.stringify({ id, name, status, slug }));
}

function filterLibrary(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('.library-form-item').forEach(item => {
    const name = item.querySelector('.library-form-name').textContent.toLowerCase();
    item.style.display = name.includes(q) ? '' : 'none';
  });
}

function saveFunnel(status) {
  const name = document.getElementById('funnelName').value.trim();
  if (!name) {
    document.getElementById('funnelName').focus();
    document.getElementById('funnelName').style.borderColor = '#ef4444';
    setTimeout(() => document.getElementById('funnelName').style.borderColor = '', 2000);
    showFunnelToast('The funnel name field is required.', 'error');
    return;
  }
  if (funnelSteps.length === 0) {
    showFunnelToast('Please add at least one form to the funnel before saving.', 'error');
    return;
  }

  const btn = document.querySelector('[onclick="saveFunnel(\'active\')"]');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  const payload = {
    name:           name,
    description:    document.getElementById('funnelDesc').value,
    insurance_type: document.getElementById('funnelInsuranceType').value,
    status:         status,
    form_ids:       JSON.stringify(funnelSteps.map(s => s.id)),
    _method:        'PUT',
  };

  fetch('{{ route("funnels.update", $funnel) }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
  .then(r => {
    if (r.status === 422) {
      return r.json().then(data => {
        if (btn) { btn.disabled = false; btn.textContent = '🚀 Publish'; }
        const msgs = data.errors ? Object.values(data.errors).flat() : [data.message || 'Validation failed.'];
        msgs.forEach(m => showFunnelToast(m, 'error'));
        throw null;
      });
    }
    if (!r.ok) throw new Error('Server error ' + r.status);
    return r.json().catch(() => ({ status: 'success' }));
  })
  .then(res => {
    if (!res) return;
    if (btn) { btn.disabled = false; btn.textContent = '🚀 Publish'; }
    showFunnelToast(status === 'active' ? 'Funnel published successfully!' : 'Funnel updated successfully!', 'success');
    setTimeout(() => window.location.href = '/funnels', 1200);
  })
  .catch(err => {
    if (!err) return;
    if (btn) { btn.disabled = false; btn.textContent = '🚀 Publish'; }
    showFunnelToast('Save failed. Please try again.', 'error');
    console.error('Funnel save error:', err);
  });
}

function showFunnelToast(message, type) {
  type = type || 'success';
  const container = document.getElementById('funnel-toast-container');
  const el = document.createElement('div');
  el.className = 'f-toast ' + type;
  const icon = type === 'success' ? '✓' : '✕';
  el.innerHTML = '<span style="font-size:16px;">' + icon + '</span> ' + message;
  container.appendChild(el);
  setTimeout(() => {
    el.classList.add('hide');
    setTimeout(() => { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
  }, 4500);
}

function escHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }
</script>
</body>
</html>
