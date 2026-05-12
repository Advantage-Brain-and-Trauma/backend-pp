<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Form Builder — {{ $form->name ?? 'New Form' }} — AdvantageHCS Admin</title>
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<style>
/* ===================== THEME VARIABLES ===================== */
:root, [data-theme="light"] {
  --bg-body:        #f0f2f5;
  --bg-panel:       #ffffff;
  --bg-card:        #f8f9fb;
  --bg-hover:       #f1f3f7;
  --bg-active:      #e8ebf2;
  --border:         #e2e5ec;
  --border-light:   #d0d4de;
  --accent:         #6366f1;
  --accent-hover:   #4f52d8;
  --accent-light:   rgba(99,102,241,0.08);
  --accent-glow:    rgba(99,102,241,0.2);
  --green:          #16a34a;
  --green-bg:       rgba(22,163,74,0.08);
  --red:            #dc2626;
  --red-bg:         rgba(220,38,38,0.08);
  --yellow:         #d97706;
  --yellow-bg:      rgba(217,119,6,0.1);
  --text-primary:   #111827;
  --text-secondary: #4b5563;
  --text-muted:     #9ca3af;
  --canvas-bg:      #e8eaf0;
  --field-bg:       #ffffff;
  --field-border:   #e2e5ec;
  --field-hover:    #f8f9fb;
  --field-selected: #eef0fd;
  --input-bg:       #f8f9fb;
  --shadow-sm:      0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.05);
  --shadow:         0 4px 16px rgba(0,0,0,0.08);
  --shadow-lg:      0 8px 32px rgba(0,0,0,0.1);
  --radius:         8px;
  --radius-lg:      12px;
  --transition:     0.15s ease;
}

[data-theme="dark"] {
  --bg-body:        #0a0a0f;
  --bg-panel:       #111118;
  --bg-card:        #16161f;
  --bg-hover:       #1e1e2a;
  --bg-active:      #1a1a28;
  --border:         #2a2a3a;
  --border-light:   #333348;
  --accent:         #6366f1;
  --accent-hover:   #4f52d8;
  --accent-light:   rgba(99,102,241,0.12);
  --accent-glow:    rgba(99,102,241,0.3);
  --green:          #22c55e;
  --green-bg:       rgba(34,197,94,0.12);
  --red:            #ef4444;
  --red-bg:         rgba(239,68,68,0.12);
  --yellow:         #f59e0b;
  --yellow-bg:      rgba(245,158,11,0.12);
  --text-primary:   #f0f0f8;
  --text-secondary: #9090b0;
  --text-muted:     #5a5a7a;
  --canvas-bg:      #0d0d14;
  --field-bg:       #1a1a28;
  --field-border:   #2e2e45;
  --field-hover:    #22223a;
  --field-selected: #1e1e35;
  --input-bg:       #111118;
  --shadow-sm:      0 1px 3px rgba(0,0,0,0.3);
  --shadow:         0 4px 20px rgba(0,0,0,0.4);
  --shadow-lg:      0 8px 32px rgba(0,0,0,0.5);
}

/* ===================== RESET & BASE ===================== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', system-ui, sans-serif;
  background: var(--bg-body);
  color: var(--text-primary);
  font-size: 13px;
  line-height: 1.5;
  transition: background 0.2s ease, color 0.2s ease;
}

/* ===================== TOP BAR ===================== */
.topbar {
  height: 54px;
  background: var(--bg-panel);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 16px;
  gap: 12px;
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  box-shadow: var(--shadow-sm);
}
.topbar-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.topbar-logo {
  display: flex; align-items: center; gap: 8px;
  font-weight: 700; font-size: 14px; color: var(--text-primary);
  text-decoration: none; white-space: nowrap;
}
.topbar-logo span { width: 38px; height: 38px; background: #C8102E; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0; }
.topbar-divider { width: 1px; height: 22px; background: var(--border); flex-shrink: 0; }
.back-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border);
  background: transparent; color: var(--text-secondary); font-size: 12px;
  cursor: pointer; text-decoration: none; transition: all var(--transition); white-space: nowrap;
}
.back-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
.form-title-input {
  background: transparent; border: 1px solid transparent; outline: none;
  color: var(--text-primary); font-size: 14px; font-weight: 600;
  min-width: 0; max-width: 220px; padding: 5px 8px; border-radius: 6px;
  transition: all var(--transition);
}
.form-title-input:hover { background: var(--bg-hover); border-color: var(--border); }
.form-title-input:focus { background: var(--bg-card); border-color: var(--accent); }
.status-badge {
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
  white-space: nowrap; flex-shrink: 0;
  background: var(--green-bg); color: var(--green); border: 1px solid rgba(22,163,74,0.25);
}
[data-theme="dark"] .status-badge { border-color: rgba(34,197,94,0.3); }
.status-badge.draft { background: var(--yellow-bg); color: var(--yellow); border-color: rgba(217,119,6,0.25); }
[data-theme="dark"] .status-badge.draft { border-color: rgba(245,158,11,0.3); }

.topbar-center { display: flex; align-items: center; gap: 2px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 3px; flex-shrink: 0; }
.tab-btn {
  padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer;
  font-size: 12px; font-weight: 500; transition: all var(--transition);
  background: transparent; color: var(--text-secondary); white-space: nowrap;
}
.tab-btn.active { background: var(--accent); color: #fff; box-shadow: 0 1px 4px rgba(99,102,241,0.3); }
.tab-btn:hover:not(.active) { background: var(--bg-hover); color: var(--text-primary); }

.topbar-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.btn {
  padding: 7px 14px; border-radius: 7px; border: none; cursor: pointer;
  font-size: 12px; font-weight: 600; transition: all var(--transition);
  display: flex; align-items: center; gap: 5px; white-space: nowrap;
}
.btn-ghost {
  background: transparent; color: var(--text-secondary);
  border: 1px solid var(--border);
}
.btn-ghost:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-light); }
.btn-primary { background: var(--accent); color: #fff; border: 1px solid transparent; }
.btn-primary:hover { background: var(--accent-hover); }
.btn-success { background: var(--green); color: #fff; border: 1px solid transparent; }
.btn-success:hover { opacity: 0.9; }

/* Theme toggle */
.theme-toggle {
  width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg-card); color: var(--text-secondary); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 16px;
  transition: all var(--transition); flex-shrink: 0;
}
.theme-toggle:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }

/* ===================== MAIN LAYOUT ===================== */
.builder-layout {
  display: flex;
  height: calc(100vh - 54px);
  margin-top: 54px;
}

/* ===================== LEFT SIDEBAR ===================== */
.palette {
  width: 220px; min-width: 220px;
  background: var(--bg-panel);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column; overflow: hidden;
}
.palette-header { padding: 12px 12px 10px; border-bottom: 1px solid var(--border); }
.palette-search {
  width: 100%; padding: 7px 10px 7px 30px;
  background: var(--input-bg); border: 1px solid var(--border);
  border-radius: 7px; color: var(--text-primary); font-size: 12px; outline: none;
  transition: border-color var(--transition);
}
.palette-search:focus { border-color: var(--accent); }
.search-wrap { position: relative; }
.search-icon { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.palette-body { flex: 1; overflow-y: auto; padding: 10px; }
.palette-body::-webkit-scrollbar { width: 4px; }
.palette-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
.palette-section { margin-bottom: 14px; }
.palette-section-title {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.8px; color: var(--text-muted); padding: 0 4px 6px;
}
.palette-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
.palette-item {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 5px; padding: 9px 6px; border-radius: 8px;
  background: var(--bg-card); border: 1px solid var(--border);
  cursor: grab; transition: all var(--transition); text-align: center; user-select: none;
}
.palette-item:hover {
  background: var(--accent-light); border-color: var(--accent);
  transform: translateY(-1px); box-shadow: var(--shadow-sm);
}
.palette-item:active { cursor: grabbing; transform: scale(0.96); }
.palette-item.dragging-source { opacity: 0.4; }
.palette-icon {
  width: 28px; height: 28px; border-radius: 7px;
  background: var(--accent-light); display: flex; align-items: center;
  justify-content: center; font-size: 14px;
}
.palette-label { font-size: 10px; color: var(--text-secondary); font-weight: 500; line-height: 1.2; }
.palette-item-wide {
  display: flex; align-items: center; gap: 8px; padding: 8px 10px;
  border-radius: 8px; background: var(--bg-card); border: 1px solid var(--border);
  cursor: grab; transition: all var(--transition); user-select: none;
}
.palette-item-wide:hover { background: var(--accent-light); border-color: var(--accent); }
.palette-item-wide .palette-icon { width: 24px; height: 24px; font-size: 12px; flex-shrink: 0; }
.palette-item-wide .palette-label { font-size: 11px; color: var(--text-secondary); }

/* ===================== CENTER CANVAS ===================== */
.canvas-wrap {
  flex: 1; min-height: 0; background: var(--canvas-bg); overflow-y: auto;
  display: flex; flex-direction: column; align-items: center;
  padding: 20px 20px 80px; position: relative;
}
.canvas-wrap::-webkit-scrollbar { width: 6px; }
.canvas-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
.canvas-toolbar {
  width: 100%; max-width: 800px;
  display: flex; align-items: center; gap: 6px; margin-bottom: 14px;
  flex-wrap: wrap;
}
.canvas-toolbar-label { font-size: 11px; color: var(--text-muted); margin-right: 2px; font-weight: 500; }
.layout-btn {
  padding: 5px 10px; border-radius: 6px; border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--text-secondary); font-size: 11px; font-weight: 500;
  cursor: pointer; transition: all var(--transition); display: flex; align-items: center; gap: 4px;
  box-shadow: var(--shadow-sm);
}
.layout-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
.canvas-toolbar-sep { flex: 1; }
.zoom-controls { display: flex; align-items: center; gap: 4px; }
.zoom-btn {
  width: 26px; height: 26px; border-radius: 5px; border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--text-secondary); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 14px;
  transition: all var(--transition); box-shadow: var(--shadow-sm);
}
.zoom-btn:hover { border-color: var(--accent); color: var(--accent); }
.zoom-label { font-size: 11px; color: var(--text-muted); min-width: 36px; text-align: center; font-weight: 500; }

.canvas-form {
  width: 100%; max-width: 800px;
  background: var(--bg-panel); border: 1px solid var(--border);
  border-radius: var(--radius-lg); min-height: 500px; padding: 28px;
  box-shadow: var(--shadow);
  transform-origin: top center;
  transition: box-shadow var(--transition);
  /* Ensure the box grows with its content and never clips fields */
  height: auto; overflow: visible; flex-shrink: 0;
}
.canvas-form-header { margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px solid var(--border); }
.canvas-form-title { font-size: 22px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
.canvas-form-desc { font-size: 13px; color: var(--text-muted); }

.canvas-drop-zone {
  min-height: 220px; border: 2px dashed var(--border);
  border-radius: 10px; display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 8px; transition: all var(--transition); padding: 40px 20px;
}
.canvas-drop-zone.drag-over { border-color: var(--accent); background: var(--accent-light); }
.canvas-drop-zone.has-fields { border: none; padding: 0; min-height: unset; display: block; background: transparent; }
.drop-hint { color: var(--text-muted); font-size: 13px; text-align: center; }
.drop-hint-icon { font-size: 36px; margin-bottom: 6px; opacity: 0.5; }

/* ===================== FORM ROWS & COLUMNS ===================== */
.form-row {
  display: flex; gap: 10px; margin-bottom: 10px;
  position: relative; border: 1px solid transparent;
  border-radius: 10px; padding: 4px; transition: border-color var(--transition);
}
.form-row:hover { border-color: var(--border); background: var(--bg-hover); }
.form-row.selected { border-color: var(--accent); background: var(--accent-light); }
.form-row-actions {
  position: absolute; top: -14px; right: 6px;
  display: none; gap: 3px; z-index: 10;
}
.form-row:hover .form-row-actions,
.form-row.selected .form-row-actions { display: flex; }
.row-action-btn {
  padding: 2px 7px; border-radius: 4px; border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--text-muted); font-size: 10px; font-weight: 600;
  cursor: pointer; transition: all var(--transition); display: flex; align-items: center; gap: 3px;
  box-shadow: var(--shadow-sm);
}
.row-action-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
.row-action-btn.danger:hover { border-color: var(--red); color: var(--red); background: var(--red-bg); }
.form-col {
  flex: 1; min-width: 0; border: 1px dashed transparent;
  border-radius: 8px; padding: 4px; transition: all var(--transition);
  position: relative; min-height: 60px;
}
.form-col.drag-over-col { border-color: var(--accent); background: var(--accent-light); }
.form-col.empty-col {
  border-color: var(--border); display: flex; align-items: center;
  justify-content: center; color: var(--text-muted); font-size: 11px;
  background: var(--bg-card);
}

/* ===================== FIELD ELEMENT ===================== */
.field-el {
  background: var(--field-bg); border: 1px solid var(--field-border);
  border-radius: 9px; padding: 12px 14px; cursor: pointer;
  transition: all var(--transition); position: relative; margin-bottom: 4px;
  user-select: none; box-shadow: var(--shadow-sm);
}
.field-el:hover { border-color: var(--accent); background: var(--field-hover); box-shadow: var(--shadow); }
.field-el.selected { border-color: var(--accent); background: var(--field-selected); box-shadow: 0 0 0 3px var(--accent-glow); }
.field-el.dragging { opacity: 0.3; }
.field-el-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.field-drag-handle {
  color: var(--text-muted); cursor: grab; font-size: 14px; line-height: 1;
  padding: 2px 3px; border-radius: 3px; transition: color var(--transition);
}
.field-drag-handle:hover { color: var(--accent); }
.field-drag-handle:active { cursor: grabbing; }
.field-label-text { font-size: 12px; font-weight: 600; color: var(--text-primary); flex: 1; }
.field-required-star { color: var(--red); margin-left: 2px; }
.field-type-badge {
  font-size: 9px; padding: 2px 7px; border-radius: 10px;
  background: var(--accent-light); color: var(--accent);
  font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.field-actions { display: none; gap: 3px; }
.field-el:hover .field-actions,
.field-el.selected .field-actions { display: flex; }
.field-action-btn {
  width: 24px; height: 24px; border-radius: 5px; border: 1px solid var(--border);
  background: var(--bg-panel); color: var(--text-muted); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 11px;
  transition: all var(--transition);
}
.field-action-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
.field-action-btn.danger:hover { border-color: var(--red); color: var(--red); background: var(--red-bg); }

/* Field Previews */
.field-preview { pointer-events: none; }
.field-preview input,
.field-preview textarea,
.field-preview select {
  width: 100%; padding: 8px 11px;
  background: var(--input-bg); border: 1px solid var(--field-border);
  border-radius: 7px; color: var(--text-secondary); font-size: 12px;
  outline: none; font-family: inherit;
}
.field-preview textarea { resize: none; height: 64px; }
.field-preview select { appearance: none; }
.field-preview .field-help { font-size: 10px; color: var(--text-muted); margin-top: 4px; }
.field-preview .radio-group,
.field-preview .check-group { display: flex; flex-direction: column; gap: 6px; }
.field-preview .radio-opt,
.field-preview .check-opt {
  display: flex; align-items: center; gap: 7px;
  font-size: 12px; color: var(--text-secondary);
}
.field-preview .radio-opt input,
.field-preview .check-opt input { width: auto; }
.field-preview .sig-box {
  height: 72px; border: 2px dashed var(--field-border); border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); font-size: 11px; background: var(--input-bg);
}
.field-preview .upload-box {
  border: 2px dashed var(--field-border); border-radius: 7px;
  padding: 16px; text-align: center; color: var(--text-muted);
  font-size: 11px; background: var(--input-bg);
}
.field-preview .divider-line { border-top: 1px solid var(--border); margin: 6px 0; }
.field-preview .header-text { font-size: 17px; font-weight: 700; color: var(--text-primary); }
.field-preview .para-text { font-size: 12px; color: var(--text-secondary); line-height: 1.6; }
.field-preview .rating-stars { display: flex; gap: 4px; font-size: 22px; color: #fbbf24; }
.field-preview .scale-wrap { display: flex; gap: 4px; flex-wrap: wrap; }
.field-preview .scale-num {
  width: 28px; height: 28px; border-radius: 5px; border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; color: var(--text-muted); background: var(--input-bg);
}
.field-preview .toggle-wrap { display: flex; align-items: center; gap: 10px; }
.field-preview .toggle-track {
  width: 42px; height: 24px; border-radius: 12px; background: var(--border);
  position: relative; flex-shrink: 0;
}
.field-preview .toggle-thumb {
  width: 18px; height: 18px; border-radius: 50%; background: #fff;
  position: absolute; top: 3px; left: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.field-preview .submit-btn {
  padding: 10px 22px; border-radius: 7px; background: var(--accent);
  color: #fff; border: none; font-size: 13px; font-weight: 600; cursor: default;
}

/* ===================== RIGHT PANEL ===================== */
.properties {
  width: 272px; min-width: 272px;
  background: var(--bg-panel); border-left: 1px solid var(--border);
  display: flex; flex-direction: column; overflow: hidden;
}
.properties-header {
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.properties-title { font-size: 13px; font-weight: 700; color: var(--text-primary); }
.properties-subtitle { font-size: 10px; color: var(--text-muted); margin-top: 1px; }
.properties-body { flex: 1; overflow-y: auto; padding: 14px 16px; }
.properties-body::-webkit-scrollbar { width: 4px; }
.properties-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
.prop-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  height: 220px; gap: 10px; color: var(--text-muted); text-align: center;
}
.prop-empty-icon { font-size: 36px; opacity: 0.4; }
.prop-empty-text { font-size: 12px; line-height: 1.5; max-width: 160px; }

.prop-tabs {
  display: flex; gap: 2px; margin-bottom: 16px;
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 8px; padding: 3px;
}
.prop-tab {
  flex: 1; padding: 5px 6px; border-radius: 6px; border: none;
  background: transparent; color: var(--text-muted); font-size: 11px;
  font-weight: 500; cursor: pointer; transition: all var(--transition); text-align: center;
}
.prop-tab.active { background: var(--accent); color: #fff; box-shadow: 0 1px 3px rgba(99,102,241,0.3); }
.prop-tab:hover:not(.active) { background: var(--bg-hover); color: var(--text-primary); }

.prop-group { margin-bottom: 18px; }
.prop-group-title {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 10px;
  padding-bottom: 6px; border-bottom: 1px solid var(--border);
}
.prop-field { margin-bottom: 12px; }
.prop-label { font-size: 11px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 5px; }
.prop-input {
  width: 100%; padding: 7px 10px; background: var(--input-bg);
  border: 1px solid var(--border); border-radius: 7px; color: var(--text-primary);
  font-size: 12px; outline: none; transition: border-color var(--transition); font-family: inherit;
}
.prop-input:focus { border-color: var(--accent); }
.prop-textarea {
  width: 100%; padding: 7px 10px; height: 72px; resize: none;
  background: var(--input-bg); border: 1px solid var(--border); border-radius: 7px;
  color: var(--text-primary); font-size: 12px; outline: none;
  transition: border-color var(--transition); font-family: inherit;
}
.prop-textarea:focus { border-color: var(--accent); }
.prop-select {
  width: 100%; padding: 7px 10px; background: var(--input-bg);
  border: 1px solid var(--border); border-radius: 7px; color: var(--text-primary);
  font-size: 12px; outline: none; appearance: none; cursor: pointer;
}
.prop-select:focus { border-color: var(--accent); }
.prop-toggle-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 7px 0; border-bottom: 1px solid var(--border);
}
.prop-toggle-row:last-child { border-bottom: none; }
.prop-toggle-label { font-size: 12px; color: var(--text-secondary); }
.toggle {
  width: 38px; height: 22px; border-radius: 11px; background: var(--border);
  cursor: pointer; position: relative; transition: background var(--transition);
  flex-shrink: 0; border: none; outline: none;
}
.toggle.on { background: var(--accent); }
.toggle::after {
  content: ''; position: absolute; top: 3px; left: 3px;
  width: 16px; height: 16px; border-radius: 50%; background: #fff;
  transition: left var(--transition); box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle.on::after { left: 19px; }
.prop-row { display: flex; gap: 8px; }
.prop-row .prop-field { flex: 1; }

.options-list { display: flex; flex-direction: column; gap: 5px; margin-bottom: 8px; }
.option-item { display: flex; align-items: center; gap: 6px; }
.option-item input {
  flex: 1; padding: 6px 8px; background: var(--input-bg);
  border: 1px solid var(--border); border-radius: 6px;
  color: var(--text-primary); font-size: 11px; outline: none;
}
.option-item input:focus { border-color: var(--accent); }
.option-del-btn {
  width: 22px; height: 22px; border-radius: 5px; border: 1px solid var(--border);
  background: transparent; color: var(--text-muted); cursor: pointer;
  display: flex; align-items: center; justify-content: center; font-size: 12px;
  transition: all var(--transition); flex-shrink: 0;
}
.option-del-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-bg); }
.add-option-btn {
  width: 100%; padding: 7px; border-radius: 7px; border: 1px dashed var(--border);
  background: transparent; color: var(--text-muted); font-size: 11px; font-weight: 500;
  cursor: pointer; transition: all var(--transition);
  display: flex; align-items: center; justify-content: center; gap: 4px;
}
.add-option-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }

.prop-footer {
  padding: 12px 16px; border-top: 1px solid var(--border);
  display: flex; gap: 8px;
}
.prop-footer .btn { flex: 1; justify-content: center; font-size: 11px; }

/* ===================== PREVIEW MODE ===================== */
.preview-overlay {
  display: none; position: fixed; inset: 54px 0 0 0;
  background: var(--canvas-bg); z-index: 50; overflow-y: auto;
  padding: 40px 24px;
}
.preview-overlay.visible { display: flex; justify-content: center; }
.preview-form {
  width: 100%; max-width: 700px;
  background: var(--bg-panel); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 36px;
  box-shadow: var(--shadow-lg);
}
.preview-form h2 { font-size: 24px; font-weight: 700; margin-bottom: 6px; color: var(--text-primary); }
.preview-form p { font-size: 13px; color: var(--text-muted); margin-bottom: 28px; }
.preview-field { margin-bottom: 20px; }
.preview-label { font-size: 13px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 7px; }
.preview-input {
  width: 100%; padding: 10px 13px; background: var(--input-bg);
  border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);
  font-size: 13px; outline: none; transition: border-color var(--transition); font-family: inherit;
}
.preview-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.preview-textarea {
  width: 100%; padding: 10px 13px; height: 96px; resize: vertical;
  background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px;
  color: var(--text-primary); font-size: 13px; outline: none; font-family: inherit;
}
.preview-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.preview-select {
  width: 100%; padding: 10px 13px; background: var(--input-bg);
  border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary);
  font-size: 13px; outline: none; appearance: none;
}
.preview-select:focus { border-color: var(--accent); }
.preview-submit {
  width: 100%; padding: 13px; border-radius: 9px; border: none;
  background: var(--accent); color: #fff; font-size: 14px; font-weight: 600;
  cursor: pointer; margin-top: 10px; transition: all var(--transition);
  box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.preview-submit:hover { background: var(--accent-hover); transform: translateY(-1px); }
.preview-row { display: flex; gap: 16px; }
.preview-row .preview-field { flex: 1; }

/* ===================== CONTEXT MENU ===================== */
.ctx-menu {
  position: fixed; z-index: 200; background: var(--bg-panel);
  border: 1px solid var(--border); border-radius: 9px; padding: 4px;
  min-width: 170px; box-shadow: var(--shadow-lg); display: none;
}
.ctx-menu.visible { display: block; }
.ctx-item {
  padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;
  color: var(--text-secondary); display: flex; align-items: center; gap: 8px;
  transition: all var(--transition);
}
.ctx-item:hover { background: var(--bg-hover); color: var(--text-primary); }
.ctx-item.danger:hover { background: var(--red-bg); color: var(--red); }
.ctx-sep { height: 1px; background: var(--border); margin: 3px 0; }

/* ===================== TOAST ===================== */
.toast-container { position: fixed; top: 54px; right: 0; z-index: 99999; display: flex; flex-direction: column; gap: 0; }
.toast {
  padding: 16px 24px; border-radius: 0 0 0 8px; font-size: 14px; font-weight: 600;
  color: #fff; box-shadow: 0 4px 16px rgba(0,0,0,0.18);
  animation: toastSlideIn 0.3s ease; display: flex; align-items: center; gap: 10px;
  min-width: 280px;
}
.toast.success { background: #16a34a; }
.toast.error   { background: #dc2626; }
@keyframes toastSlideIn  { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes toastSlideOut { from { transform: translateY(0); opacity: 1; } to { transform: translateY(-100%); opacity: 0; } }
.toast.hide { animation: toastSlideOut 0.3s ease forwards; }

::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div class="topbar-left">
    <a href="{{ route('dashboard') }}" class="topbar-logo">
      <span>A</span> AdvantageHCS
      <span style="font-size:10px; font-weight:600; background:#f3f4f6; color:#6b7280; border-radius:4px; padding:2px 7px; margin-left:2px;">Admin</span>
    </a>
    <div class="topbar-divider"></div>
    <a href="{{ route('forms.index') }}" class="back-btn">← Forms</a>
    <div class="topbar-divider"></div>
    <input class="form-title-input" id="formTitleInput" value="{{ $form->name ?? 'New Form' }}" type="text" placeholder="Form name...">
    <span class="status-badge {{ $form->is_active ? '' : 'draft' }}" id="formStatusBadge">{{ $form->is_active ? 'Active' : 'Draft' }}</span>
  </div>
  <div class="topbar-center">
    <button class="tab-btn active" id="tabBuild" onclick="switchTab('build')">✏️ Build</button>
    <button class="tab-btn" id="tabPreview" onclick="switchTab('preview')">👁 Preview</button>
    <button class="tab-btn" id="tabSettings" onclick="switchTab('settings')">⚙️ Settings</button>
  </div>
  <div class="topbar-right">
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle light/dark theme">🌙</button>
    <button class="btn btn-ghost" onclick="clearCanvas()">🗑 Clear</button>
    <button class="btn btn-primary" onclick="saveForm()">💾 Save</button>
  </div>
</div>

<!-- MAIN BUILDER LAYOUT -->
<div class="builder-layout" id="builderLayout">

  <!-- LEFT: FIELD PALETTE -->
  <div class="palette">
    <div class="palette-header">
      <div class="search-wrap">
        <svg class="search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input class="palette-search" id="paletteSearch" placeholder="Search fields..." oninput="filterPalette(this.value)">
      </div>
    </div>
    <div class="palette-body" id="paletteBody">
      <div class="palette-section">
        <div class="palette-section-title">Basic Fields</div>
        <div class="palette-grid">
          <div class="palette-item" draggable="true" data-type="text"      data-label="Text Input"><div class="palette-icon">📝</div><div class="palette-label">Text</div></div>
          <div class="palette-item" draggable="true" data-type="email"     data-label="Email"><div class="palette-icon">📧</div><div class="palette-label">Email</div></div>
          <div class="palette-item" draggable="true" data-type="phone"     data-label="Phone"><div class="palette-icon">📞</div><div class="palette-label">Phone</div></div>
          <div class="palette-item" draggable="true" data-type="number"    data-label="Number"><div class="palette-icon">#️⃣</div><div class="palette-label">Number</div></div>
          <div class="palette-item" draggable="true" data-type="date"      data-label="Date"><div class="palette-icon">📅</div><div class="palette-label">Date</div></div>
          <div class="palette-item" draggable="true" data-type="time"      data-label="Time"><div class="palette-icon">🕐</div><div class="palette-label">Time</div></div>
          <div class="palette-item" draggable="true" data-type="textarea"  data-label="Text Area"><div class="palette-icon">📄</div><div class="palette-label">Textarea</div></div>
          <div class="palette-item" draggable="true" data-type="password"  data-label="Password"><div class="palette-icon">🔒</div><div class="palette-label">Password</div></div>
        </div>
      </div>
      <div class="palette-section">
        <div class="palette-section-title">Choice Fields</div>
        <div class="palette-grid">
          <div class="palette-item" draggable="true" data-type="dropdown"  data-label="Dropdown"><div class="palette-icon">🔽</div><div class="palette-label">Dropdown</div></div>
          <div class="palette-item" draggable="true" data-type="radio"     data-label="Radio"><div class="palette-icon">🔘</div><div class="palette-label">Radio</div></div>
          <div class="palette-item" draggable="true" data-type="checkbox"  data-label="Checkbox"><div class="palette-icon">☑️</div><div class="palette-label">Checkbox</div></div>
          <div class="palette-item" draggable="true" data-type="toggle"    data-label="Toggle"><div class="palette-icon">🔄</div><div class="palette-label">Toggle</div></div>
          <div class="palette-item" draggable="true" data-type="rating"    data-label="Rating"><div class="palette-icon">⭐</div><div class="palette-label">Rating</div></div>
          <div class="palette-item" draggable="true" data-type="scale"     data-label="Scale"><div class="palette-icon">📊</div><div class="palette-label">Scale</div></div>
        </div>
      </div>
      <div class="palette-section">
        <div class="palette-section-title">Advanced Fields</div>
        <div class="palette-grid">
          <div class="palette-item" draggable="true" data-type="signature" data-label="Signature"><div class="palette-icon">✍️</div><div class="palette-label">Signature</div></div>
          <div class="palette-item" draggable="true" data-type="file"      data-label="File Upload"><div class="palette-icon">📎</div><div class="palette-label">File Upload</div></div>
          <div class="palette-item" draggable="true" data-type="address"   data-label="Address"><div class="palette-icon">📍</div><div class="palette-label">Address</div></div>
          <div class="palette-item" draggable="true" data-type="name"      data-label="Full Name"><div class="palette-icon">👤</div><div class="palette-label">Full Name</div></div>
        </div>
      </div>
      <div class="palette-section">
        <div class="palette-section-title">Layout & Content</div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <div class="palette-item-wide" draggable="true" data-type="header"    data-label="Header"><div class="palette-icon">🔤</div><div class="palette-label">Section Header</div></div>
          <div class="palette-item-wide" draggable="true" data-type="paragraph" data-label="Paragraph"><div class="palette-icon">📃</div><div class="palette-label">Paragraph Text</div></div>
          <div class="palette-item-wide" draggable="true" data-type="divider"   data-label="Divider"><div class="palette-icon">➖</div><div class="palette-label">Divider Line</div></div>
          <div class="palette-item-wide" draggable="true" data-type="image"     data-label="Image"><div class="palette-icon">🖼</div><div class="palette-label">Image / Logo</div></div>
          <div class="palette-item-wide" draggable="true" data-type="submit"    data-label="Submit Button"><div class="palette-icon">🚀</div><div class="palette-label">Submit Button</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CENTER: CANVAS -->
  <div class="canvas-wrap" id="canvasWrap">
    <div class="canvas-toolbar">
      <span class="canvas-toolbar-label">Add row:</span>
      <button class="layout-btn" onclick="addRow(1)">▬ 1 Col</button>
      <button class="layout-btn" onclick="addRow(2)">▬▬ 2 Col</button>
      <button class="layout-btn" onclick="addRow(3)">▬▬▬ 3 Col</button>
      <button class="layout-btn" onclick="addRow(4)">▬▬▬▬ 4 Col</button>
      <div class="canvas-toolbar-sep"></div>
      <div class="zoom-controls">
        <button class="zoom-btn" onclick="changeZoom(-10)">−</button>
        <span class="zoom-label" id="zoomLabel">100%</span>
        <button class="zoom-btn" onclick="changeZoom(10)">+</button>
      </div>
    </div>
    <div class="canvas-form" id="canvasForm">
      <div class="canvas-form-header">
        <div class="canvas-form-title" id="canvasFormTitle">{{ $form->name ?? 'New Form' }}</div>
        <div class="canvas-form-desc" id="canvasFormDesc">{{ $form->description ?? '' }}</div>
      </div>
      <div class="canvas-drop-zone" id="dropZone">
        <div class="drop-hint">
          <div class="drop-hint-icon">⬇️</div>
          <div style="font-weight:600;margin-bottom:4px;">Drag fields here to start building</div>
          <div style="font-size:11px;">Or click any field in the palette, or use the row buttons above</div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: PROPERTIES PANEL -->
  <div class="properties" id="propertiesPanel">
    <div class="properties-header">
      <div>
        <div class="properties-title" id="propPanelTitle">Properties</div>
        <div class="properties-subtitle" id="propPanelSubtitle">Select a field to edit</div>
      </div>
    </div>
    <div class="properties-body" id="propertiesBody">
      <div class="prop-empty" id="propEmpty">
        <div class="prop-empty-icon">🖱️</div>
        <div class="prop-empty-text">Click any field on the canvas to edit its properties</div>
      </div>
      <div id="propContent" style="display:none;"></div>
    </div>
    <div class="prop-footer" id="propFooter" style="display:none;">
      <button class="btn btn-ghost" onclick="duplicateSelected()">⧉ Duplicate</button>
      <button class="btn" style="background:var(--red-bg);color:var(--red);border:1px solid rgba(220,38,38,0.2);" onclick="deleteSelected()">🗑 Delete</button>
    </div>
  </div>
</div>

<!-- PREVIEW OVERLAY -->
<div class="preview-overlay" id="previewOverlay">
  <div class="preview-form" id="previewForm">
    <h2 id="previewTitle">{{ $form->name ?? 'Form Preview' }}</h2>
    <p id="previewDesc">{{ $form->description ?? '' }}</p>
    <div id="previewFields"></div>
    <button class="preview-submit">Submit Form</button>
  </div>
</div>

<!-- SETTINGS OVERLAY -->
<div class="preview-overlay" id="settingsOverlay" style="overflow-y:auto;">
  <div style="max-width:640px;margin:40px auto;padding:0 20px 60px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
      <h2 style="font-size:20px;font-weight:700;color:var(--text-primary);">⚙️ Form Settings</h2>
      <button onclick="switchTab('build')" style="padding:8px 16px;border-radius:8px;border:1px solid var(--border);background:var(--bg-panel);color:var(--text-secondary);cursor:pointer;font-size:13px;">✕ Close</button>
    </div>

    <!-- Public URL Card -->
    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
        <span style="font-size:20px;">🔗</span>
        <div>
          <div style="font-weight:700;font-size:15px;color:var(--text-primary);">Public Form URL</div>
          <div style="font-size:12px;color:var(--text-muted);">Share this link with patients to fill out the form</div>
        </div>
      </div>
      @if($form->slug)
      <div style="display:flex;gap:8px;align-items:center;">
        <input type="text" id="publicUrlInput" value="{{ url('/f/' . $form->slug) }}" readonly
          style="flex:1;padding:10px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--input-bg);color:var(--text-primary);font-size:13px;font-family:monospace;outline:none;">
        <button onclick="copyPublicUrl()" style="padding:10px 16px;border-radius:8px;border:none;background:var(--accent);color:#fff;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">📋 Copy</button>
        <a href="{{ url('/f/' . $form->slug) }}" target="_blank" style="padding:10px 16px;border-radius:8px;border:1px solid var(--border);background:var(--bg-panel);color:var(--text-secondary);font-size:13px;text-decoration:none;white-space:nowrap;">↗ Open</a>
      </div>
      <div id="copyMsg" style="display:none;margin-top:8px;font-size:12px;color:var(--green);">✅ URL copied to clipboard!</div>
      @else
      <div style="padding:14px;background:var(--yellow-bg);border-radius:8px;font-size:13px;color:var(--yellow);">⚠️ Save the form first to generate a public URL.</div>
      @endif
      <div style="margin-top:14px;padding:12px;background:var(--bg-card);border-radius:8px;">
        <div style="font-size:12px;color:var(--text-muted);line-height:1.6;">
          <strong style="color:var(--text-secondary);">Status:</strong>
          @if($form->is_active)
            <span style="color:var(--green);">✅ Active — Patients can fill this form</span>
          @else
            <span style="color:var(--yellow);">⚠️ Draft — Publish the form to make it accessible</span>
          @endif
        </div>
      </div>
    </div>

    <!-- Form Details Card -->
    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px;">
      <div style="font-weight:700;font-size:15px;color:var(--text-primary);margin-bottom:16px;">📋 Form Details</div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text-muted);">Form ID</span>
          <span style="font-size:13px;color:var(--text-primary);font-weight:600;">#{{ $form->id }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text-muted);">Category</span>
          <span style="font-size:13px;color:var(--text-primary);">{{ $form->category ?? 'Uncategorized' }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text-muted);">Total Submissions</span>
          <span style="font-size:13px;color:var(--text-primary);font-weight:600;">{{ $form->submission_count ?? 0 }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text-muted);">Created</span>
          <span style="font-size:13px;color:var(--text-primary);">{{ $form->created_at?->format('M d, Y') }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;">
          <span style="font-size:13px;color:var(--text-muted);">Last Updated</span>
          <span style="font-size:13px;color:var(--text-primary);">{{ $form->updated_at?->diffForHumans() }}</span>
        </div>
      </div>
    </div>

    <!-- Danger Zone -->
    <div style="background:var(--bg-panel);border:1px solid var(--red-bg);border-radius:12px;padding:24px;">
      <div style="font-weight:700;font-size:15px;color:var(--red);margin-bottom:12px;">⚠️ Danger Zone</div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div style="font-size:13px;color:var(--text-primary);font-weight:500;">Delete this form</div>
          <div style="font-size:12px;color:var(--text-muted);">This action cannot be undone. All submissions will be lost.</div>
        </div>
        <form method="POST" action="{{ route('forms.destroy', $form) }}" onsubmit="return confirm('Delete this form and all its submissions? This cannot be undone.')">
          @csrf @method('DELETE')
          <button type="submit" style="padding:8px 16px;border-radius:8px;border:1px solid var(--red);background:transparent;color:var(--red);font-size:13px;cursor:pointer;">🗑 Delete Form</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- CONTEXT MENU -->
<div class="ctx-menu" id="ctxMenu">
  <div class="ctx-item" onclick="ctxDuplicate()">⧉ Duplicate Field</div>
  <div class="ctx-item" onclick="ctxMoveUp()">⬆ Move Up</div>
  <div class="ctx-item" onclick="ctxMoveDown()">⬇ Move Down</div>
  <div class="ctx-sep"></div>
  <div class="ctx-item danger" onclick="ctxDelete()">🗑 Delete Field</div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- HIDDEN FORM FOR SAVE -->
<form id="saveFormEl" method="POST" action="{{ isset($form) ? route('forms.update', $form->id) : route('forms.store') }}" style="display:none;">
  @csrf
  @if(isset($form)) @method('PUT') @endif
  <input type="hidden" name="name" id="saveFormName">
  <input type="hidden" name="schema" id="saveFormSchema">
  <input type="hidden" name="status" id="saveFormStatus">
  <input type="hidden" name="description" id="saveFormDesc">
</form>

<script>
// ===================== THEME =====================
const savedTheme = localStorage.getItem('fb_theme') || 'light';
document.documentElement.setAttribute('data-theme', savedTheme);
document.getElementById('themeToggle').textContent = savedTheme === 'dark' ? '☀️' : '🌙';

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('fb_theme', next);
  document.getElementById('themeToggle').textContent = next === 'dark' ? '☀️' : '🌙';
  showToast(next === 'dark' ? 'Dark theme enabled' : 'Light theme enabled', 'success');
}

// ===================== INITIAL DATA FROM LARAVEL =====================
let formData = {
  title: @json($form->name ?? 'New Form'),
  description: @json($form->description ?? ''),
  status: @json($form->is_active ? 'active' : 'draft'),
  rows: @json(isset($form) && $form->fields && isset($form->fields['rows']) ? $form->fields['rows'] : [])
};

let selectedFieldId = null;
let selectedRowId = null;
let selectedColIdx = null;
let dragSourceType = null;
let dragSourcePaletteType = null;
let dragSourceRowId = null;
let dragSourceColIdx = null;
let dragSourceFieldId = null;
let ctxTargetFieldId = null;
let zoom = 100;
let fieldCounter = 0;

const FIELD_DEFS = {
  text:      { icon: '📝', label: 'Text Input',    hasOptions: false, hasPlaceholder: true },
  email:     { icon: '📧', label: 'Email',         hasOptions: false, hasPlaceholder: true },
  phone:     { icon: '📞', label: 'Phone',         hasOptions: false, hasPlaceholder: true },
  number:    { icon: '#️⃣', label: 'Number',        hasOptions: false, hasPlaceholder: true },
  date:      { icon: '📅', label: 'Date',          hasOptions: false, hasPlaceholder: false },
  time:      { icon: '🕐', label: 'Time',          hasOptions: false, hasPlaceholder: false },
  textarea:  { icon: '📄', label: 'Text Area',     hasOptions: false, hasPlaceholder: true },
  password:  { icon: '🔒', label: 'Password',      hasOptions: false, hasPlaceholder: true },
  dropdown:  { icon: '🔽', label: 'Dropdown',      hasOptions: true,  hasPlaceholder: true },
  radio:     { icon: '🔘', label: 'Radio',         hasOptions: true,  hasPlaceholder: false },
  checkbox:  { icon: '☑️', label: 'Checkbox',      hasOptions: true,  hasPlaceholder: false },
  toggle:    { icon: '🔄', label: 'Toggle',        hasOptions: false, hasPlaceholder: false },
  rating:    { icon: '⭐', label: 'Rating',        hasOptions: false, hasPlaceholder: false },
  scale:     { icon: '📊', label: 'Scale',         hasOptions: false, hasPlaceholder: false },
  signature: { icon: '✍️', label: 'Signature',     hasOptions: false, hasPlaceholder: false },
  file:      { icon: '📎', label: 'File Upload',   hasOptions: false, hasPlaceholder: false },
  address:   { icon: '📍', label: 'Address',       hasOptions: false, hasPlaceholder: false },
  name:      { icon: '👤', label: 'Full Name',     hasOptions: false, hasPlaceholder: false },
  header:    { icon: '🔤', label: 'Header',        hasOptions: false, hasPlaceholder: false },
  paragraph: { icon: '📃', label: 'Paragraph',     hasOptions: false, hasPlaceholder: false },
  divider:   { icon: '➖', label: 'Divider',       hasOptions: false, hasPlaceholder: false },
  image:     { icon: '🖼', label: 'Image',         hasOptions: false, hasPlaceholder: false },
  submit:    { icon: '🚀', label: 'Submit Button', hasOptions: false, hasPlaceholder: false },
};

function createField(type) {
  fieldCounter++;
  const def = FIELD_DEFS[type] || { icon: '📝', label: type };
  return {
    id: 'f' + fieldCounter,
    type,
    label: def.label,
    placeholder: def.hasPlaceholder ? 'Enter ' + def.label.toLowerCase() + '...' : '',
    required: false,
    helpText: '',
    width: '100%',
    cssClass: '',
    options: def.hasOptions ? ['Option 1', 'Option 2', 'Option 3'] : [],
    content: type === 'header' ? 'Section Title' : (type === 'paragraph' ? 'Add your paragraph text here...' : ''),
    buttonText: type === 'submit' ? 'Submit Form' : '',
    style: { bold: false, italic: false, fontSize: '13', color: '', bgColor: '' }
  };
}

// ===================== RENDER =====================
function render() {
  const dz = document.getElementById('dropZone');
  if (formData.rows.length === 0) {
    dz.className = 'canvas-drop-zone';
    dz.innerHTML = `<div class="drop-hint"><div class="drop-hint-icon">⬇️</div><div style="font-weight:600;margin-bottom:4px;">Drag fields here to start building</div><div style="font-size:11px;">Or click any field in the palette, or use the row buttons above</div></div>`;
    return;
  }
  dz.className = 'canvas-drop-zone has-fields';
  dz.innerHTML = '';
  formData.rows.forEach(row => dz.appendChild(renderRow(row)));
  document.getElementById('canvasFormTitle').textContent = formData.title;
  document.getElementById('canvasFormDesc').textContent = formData.description;
}

function renderRow(row) {
  const rowEl = document.createElement('div');
  rowEl.className = 'form-row' + (selectedRowId === row.id && selectedFieldId === null ? ' selected' : '');
  rowEl.dataset.rowId = row.id;
  const actions = document.createElement('div');
  actions.className = 'form-row-actions';
  actions.innerHTML = `
    <button class="row-action-btn" onclick="addColToRow('${row.id}')">+ Col</button>
    <button class="row-action-btn" onclick="removeColFromRow('${row.id}')">− Col</button>
    <button class="row-action-btn" onclick="moveRowUp('${row.id}')">↑</button>
    <button class="row-action-btn" onclick="moveRowDown('${row.id}')">↓</button>
    <button class="row-action-btn danger" onclick="deleteRow('${row.id}')">✕</button>`;
  rowEl.appendChild(actions);
  row.cols.forEach((col, colIdx) => {
    const colEl = document.createElement('div');
    colEl.className = 'form-col' + (col.fields.length === 0 ? ' empty-col' : '');
    colEl.dataset.rowId = row.id;
    colEl.dataset.colIdx = colIdx;
    if (col.fields.length === 0) colEl.innerHTML = `<span>Drop field here</span>`;
    else col.fields.forEach(field => colEl.appendChild(renderField(field, row.id, colIdx)));
    setupColDrop(colEl, row.id, colIdx);
    rowEl.appendChild(colEl);
  });
  return rowEl;
}

function renderField(field, rowId, colIdx) {
  const def = FIELD_DEFS[field.type] || {};
  const el = document.createElement('div');
  el.className = 'field-el' + (selectedFieldId === field.id ? ' selected' : '');
  el.dataset.fieldId = field.id;
  el.draggable = true;
  el.innerHTML = `
    <div class="field-el-header">
      <span class="field-drag-handle">⠿</span>
      <span class="field-label-text">${escHtml(field.label)}${field.required ? '<span class="field-required-star">*</span>' : ''}</span>
      <span class="field-type-badge">${def.icon || ''} ${field.type}</span>
      <div class="field-actions">
        <button class="field-action-btn" onclick="duplicateField('${field.id}','${rowId}',${colIdx})" title="Duplicate">⧉</button>
        <button class="field-action-btn danger" onclick="deleteField('${field.id}','${rowId}',${colIdx})" title="Delete">✕</button>
      </div>
    </div>
    <div class="field-preview">${renderFieldPreview(field)}</div>`;
  el.addEventListener('click', e => { e.stopPropagation(); selectField(field.id, rowId, colIdx); });
  el.addEventListener('contextmenu', e => { e.preventDefault(); ctxTargetFieldId = field.id; showCtxMenu(e.clientX, e.clientY); });
  el.addEventListener('dragstart', e => { dragSourceType = 'canvas'; dragSourceRowId = rowId; dragSourceColIdx = colIdx; dragSourceFieldId = field.id; el.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
  el.addEventListener('dragend', () => el.classList.remove('dragging'));
  return el;
}

function renderFieldPreview(field) {
  switch(field.type) {
    case 'text': case 'email': case 'phone': case 'password':
      return `<input type="${field.type === 'phone' ? 'tel' : field.type}" placeholder="${escHtml(field.placeholder)}" disabled>${field.helpText ? `<div class="field-help">${escHtml(field.helpText)}</div>` : ''}`;
    case 'number': return `<input type="number" placeholder="${escHtml(field.placeholder)}" disabled>`;
    case 'date': return `<input type="date" disabled>`;
    case 'time': return `<input type="time" disabled>`;
    case 'textarea': return `<textarea placeholder="${escHtml(field.placeholder)}" disabled></textarea>`;
    case 'dropdown': return `<select disabled><option>${escHtml(field.placeholder || 'Select...')}</option>${(field.options||[]).map(o=>`<option>${escHtml(o)}</option>`).join('')}</select>`;
    case 'radio': return `<div class="radio-group">${(field.options||[]).map(o=>`<label class="radio-opt"><input type="radio" disabled> ${escHtml(o)}</label>`).join('')}</div>`;
    case 'checkbox': return `<div class="check-group">${(field.options||[]).map(o=>`<label class="check-opt"><input type="checkbox" disabled> ${escHtml(o)}</label>`).join('')}</div>`;
    case 'toggle': return `<div class="toggle-wrap"><div class="toggle-track"><div class="toggle-thumb"></div></div><span style="font-size:12px;color:var(--text-muted)">Off</span></div>`;
    case 'rating': return `<div class="rating-stars">★★★★★</div>`;
    case 'scale': return `<div class="scale-wrap">${[1,2,3,4,5,6,7,8,9,10].map(n=>`<div class="scale-num">${n}</div>`).join('')}</div>`;
    case 'signature': return `<div class="sig-box">✍️ Sign here</div>`;
    case 'file': return `<div class="upload-box">📎 Click to upload or drag & drop<br><span style="font-size:10px;color:var(--text-muted)">PDF, JPG, PNG up to 10MB</span></div>`;
    case 'address': return `<div style="display:flex;flex-direction:column;gap:6px;"><input type="text" placeholder="Street Address" disabled><div style="display:flex;gap:6px;"><input type="text" placeholder="City" disabled style="flex:1;"><input type="text" placeholder="State" disabled style="width:70px;"><input type="text" placeholder="ZIP" disabled style="width:80px;"></div></div>`;
    case 'name': return `<div style="display:flex;gap:6px;"><input type="text" placeholder="First Name" disabled style="flex:1;"><input type="text" placeholder="Last Name" disabled style="flex:1;"></div>`;
    case 'header': return `<div class="header-text">${escHtml(field.content || 'Section Title')}</div>`;
    case 'paragraph': return `<div class="para-text">${escHtml(field.content || 'Paragraph text...')}</div>`;
    case 'divider': return `<div class="divider-line"></div>`;
    case 'image': return `<div style="border:2px dashed var(--border);border-radius:7px;padding:20px;text-align:center;color:var(--text-muted);font-size:11px;background:var(--input-bg);">🖼 Image placeholder</div>`;
    case 'submit': return `<button class="submit-btn">${escHtml(field.buttonText || 'Submit Form')}</button>`;
    default: return `<input type="text" placeholder="${escHtml(field.placeholder || '')}" disabled>`;
  }
}

function setupColDrop(colEl, rowId, colIdx) {
  colEl.addEventListener('dragover', e => { e.preventDefault(); colEl.classList.add('drag-over-col'); });
  colEl.addEventListener('dragleave', e => { if (!colEl.contains(e.relatedTarget)) colEl.classList.remove('drag-over-col'); });
  colEl.addEventListener('drop', e => {
    e.preventDefault(); colEl.classList.remove('drag-over-col');
    if (dragSourceType === 'palette') { const f = createField(dragSourcePaletteType); addFieldToCol(f, rowId, colIdx); }
    else if (dragSourceType === 'canvas') moveFieldToCol(dragSourceFieldId, dragSourceRowId, dragSourceColIdx, rowId, colIdx);
  });
}

document.querySelectorAll('.palette-item, .palette-item-wide').forEach(item => {
  item.addEventListener('dragstart', e => { dragSourceType = 'palette'; dragSourcePaletteType = item.dataset.type; e.dataTransfer.effectAllowed = 'copy'; item.classList.add('dragging-source'); });
  item.addEventListener('dragend', () => item.classList.remove('dragging-source'));
  item.addEventListener('click', () => {
    const field = createField(item.dataset.type);
    let placedRowId, placedColIdx;
    if (formData.rows.length === 0) {
      const row = createRow(1); row.cols[0].fields.push(field); formData.rows.push(row);
      placedRowId = row.id; placedColIdx = 0;
    } else {
      const lastRow = formData.rows[formData.rows.length - 1];
      const emptyColIdx = lastRow.cols.findIndex(c => c.fields.length === 0);
      if (emptyColIdx !== -1) {
        lastRow.cols[emptyColIdx].fields.push(field);
        placedRowId = lastRow.id; placedColIdx = emptyColIdx;
      } else {
        const newRow = createRow(1); newRow.cols[0].fields.push(field); formData.rows.push(newRow);
        placedRowId = newRow.id; placedColIdx = 0;
      }
    }
    render(); selectField(field.id, placedRowId, placedColIdx); showToast('Field added', 'success');
  });
});

document.getElementById('dropZone').addEventListener('dragover', e => { e.preventDefault(); document.getElementById('dropZone').classList.add('drag-over'); });
document.getElementById('dropZone').addEventListener('dragleave', () => document.getElementById('dropZone').classList.remove('drag-over'));
document.getElementById('dropZone').addEventListener('drop', e => { e.preventDefault(); document.getElementById('dropZone').classList.remove('drag-over'); if (dragSourceType === 'palette') addFieldToNewRow(dragSourcePaletteType); });

let rowCounter = 0;
function createRow(cols) { rowCounter++; return { id: 'r' + rowCounter, cols: Array.from({length: cols}, () => ({ fields: [] })) }; }
function addRow(cols) { formData.rows.push(createRow(cols)); render(); showToast(`${cols}-column row added`, 'success'); }
function addFieldToNewRow(type) { const f = createField(type); const r = createRow(1); r.cols[0].fields.push(f); formData.rows.push(r); render(); selectField(f.id, r.id, 0); }
function addFieldToCol(field, rowId, colIdx) { const row = formData.rows.find(r => r.id === rowId); if (row && row.cols[colIdx]) { row.cols[colIdx].fields.push(field); render(); selectField(field.id, rowId, colIdx); } }
function moveFieldToCol(fieldId, fromRowId, fromColIdx, toRowId, toColIdx) {
  if (fromRowId === toRowId && fromColIdx === toColIdx) return;
  const fromRow = formData.rows.find(r => r.id === fromRowId), toRow = formData.rows.find(r => r.id === toRowId);
  if (!fromRow || !toRow) return;
  const fromCol = fromRow.cols[fromColIdx], toCol = toRow.cols[toColIdx];
  if (!fromCol || !toCol) return;
  const idx = fromCol.fields.findIndex(f => f.id === fieldId);
  if (idx === -1) return;
  const [field] = fromCol.fields.splice(idx, 1);
  toCol.fields.push(field);
  render(); selectField(field.id, toRowId, toColIdx);
}
function addColToRow(rowId) { const row = formData.rows.find(r => r.id === rowId); if (row && row.cols.length < 4) { row.cols.push({ fields: [] }); render(); } }
function removeColFromRow(rowId) { const row = formData.rows.find(r => r.id === rowId); if (row && row.cols.length > 1) { const last = row.cols.pop(); last.fields.forEach(f => row.cols[0].fields.push(f)); render(); } }
function deleteRow(rowId) { formData.rows = formData.rows.filter(r => r.id !== rowId); if (selectedRowId === rowId) { selectedFieldId = null; selectedRowId = null; } render(); renderProperties(); }
function moveRowUp(rowId) { const i = formData.rows.findIndex(r => r.id === rowId); if (i > 0) { [formData.rows[i-1], formData.rows[i]] = [formData.rows[i], formData.rows[i-1]]; render(); } }
function moveRowDown(rowId) { const i = formData.rows.findIndex(r => r.id === rowId); if (i < formData.rows.length - 1) { [formData.rows[i], formData.rows[i+1]] = [formData.rows[i+1], formData.rows[i]]; render(); } }

function selectField(fieldId, rowId, colIdx) { selectedFieldId = fieldId; selectedRowId = rowId; selectedColIdx = colIdx; render(); renderProperties(); }
function deleteField(fieldId, rowId, colIdx) {
  const row = formData.rows.find(r => r.id === rowId); if (!row) return;
  row.cols[colIdx].fields = row.cols[colIdx].fields.filter(f => f.id !== fieldId);
  formData.rows = formData.rows.filter(r => r.cols.some(c => c.fields.length > 0));
  if (selectedFieldId === fieldId) { selectedFieldId = null; selectedRowId = null; }
  render(); renderProperties(); showToast('Field deleted');
}
function duplicateField(fieldId, rowId, colIdx) {
  const row = formData.rows.find(r => r.id === rowId); if (!row) return;
  const col = row.cols[colIdx]; const field = col.fields.find(f => f.id === fieldId); if (!field) return;
  const copy = JSON.parse(JSON.stringify(field)); fieldCounter++; copy.id = 'f' + fieldCounter;
  const idx = col.fields.findIndex(f => f.id === fieldId); col.fields.splice(idx + 1, 0, copy);
  render(); selectField(copy.id, rowId, colIdx); showToast('Field duplicated', 'success');
}
function deleteSelected() { if (!selectedFieldId || !selectedRowId) return; deleteField(selectedFieldId, selectedRowId, selectedColIdx); }
function duplicateSelected() { if (!selectedFieldId || !selectedRowId) return; duplicateField(selectedFieldId, selectedRowId, selectedColIdx); }
function getSelectedField() {
  if (!selectedFieldId || !selectedRowId) return null;
  const row = formData.rows.find(r => r.id === selectedRowId); if (!row) return null;
  const col = row.cols[selectedColIdx]; if (!col) return null;
  return col.fields.find(f => f.id === selectedFieldId) || null;
}

function renderProperties() {
  const empty = document.getElementById('propEmpty'), content = document.getElementById('propContent'), footer = document.getElementById('propFooter');
  const title = document.getElementById('propPanelTitle'), subtitle = document.getElementById('propPanelSubtitle');
  const field = getSelectedField();
  if (!field) { empty.style.display = 'flex'; content.style.display = 'none'; footer.style.display = 'none'; title.textContent = 'Properties'; subtitle.textContent = 'Select a field to edit'; return; }
  const def = FIELD_DEFS[field.type] || {};
  empty.style.display = 'none'; content.style.display = 'block'; footer.style.display = 'flex';
  title.textContent = def.label || field.type; subtitle.textContent = `Type: ${field.type} · ID: ${field.id}`;
  content.innerHTML = buildPropertiesHTML(field);
  attachPropListeners(field);
}

function buildPropertiesHTML(field) {
  const def = FIELD_DEFS[field.type] || {};
  let html = `<div class="prop-tabs"><button class="prop-tab active" onclick="switchPropTab(this,'general')">General</button><button class="prop-tab" onclick="switchPropTab(this,'style')">Style</button><button class="prop-tab" onclick="switchPropTab(this,'validation')">Validation</button></div>`;
  html += `<div id="propTabGeneral">`;
  if (!['divider'].includes(field.type)) {
    html += `<div class="prop-group"><div class="prop-group-title">Field Settings</div>`;
    html += `<div class="prop-field"><label class="prop-label">Label</label><input class="prop-input" id="pLabel" value="${escHtml(field.label)}" placeholder="Field label"></div>`;
    if (def.hasPlaceholder) html += `<div class="prop-field"><label class="prop-label">Placeholder</label><input class="prop-input" id="pPlaceholder" value="${escHtml(field.placeholder)}" placeholder="Placeholder text"></div>`;
    if (['header','paragraph'].includes(field.type)) html += `<div class="prop-field"><label class="prop-label">Content</label><textarea class="prop-textarea" id="pContent">${escHtml(field.content)}</textarea></div>`;
    if (field.type === 'submit') html += `<div class="prop-field"><label class="prop-label">Button Text</label><input class="prop-input" id="pButtonText" value="${escHtml(field.buttonText || 'Submit Form')}"></div>`;
    html += `<div class="prop-field"><label class="prop-label">Help Text</label><input class="prop-input" id="pHelpText" value="${escHtml(field.helpText)}" placeholder="Optional help text"></div>`;
    html += `<div class="prop-toggle-row"><span class="prop-toggle-label">Required Field</span><button class="toggle ${field.required ? 'on' : ''}" id="pRequired" onclick="toggleProp('required')"></button></div>`;
    html += `</div>`;
  }
  if (def.hasOptions) {
    html += `<div class="prop-group"><div class="prop-group-title">Options</div><div class="options-list" id="optionsList">${(field.options||[]).map((opt, i) => `<div class="option-item"><input type="text" value="${escHtml(opt)}" onchange="updateOption(${i}, this.value)" placeholder="Option ${i+1}"><button class="option-del-btn" onclick="removeOption(${i})">✕</button></div>`).join('')}</div><button class="add-option-btn" onclick="addOption()">+ Add Option</button></div>`;
  }
  html += `</div>`;
  html += `<div id="propTabStyle" style="display:none;">
    <div class="prop-group"><div class="prop-group-title">Typography</div>
      <div class="prop-row">
        <div class="prop-field"><label class="prop-label">Font Size</label><select class="prop-select" onchange="updateStyle('fontSize', this.value)">${['11','12','13','14','16','18','20','24','28','32'].map(s=>`<option value="${s}" ${field.style.fontSize===s?'selected':''}>${s}px</option>`).join('')}</select></div>
        <div class="prop-field"><label class="prop-label">Color</label><input type="color" class="prop-input" value="${field.style.color || '#111827'}" style="height:36px;padding:2px;" onchange="updateStyle('color', this.value)"></div>
      </div>
      <div class="prop-toggle-row"><span class="prop-toggle-label">Bold</span><button class="toggle ${field.style.bold ? 'on' : ''}" onclick="toggleStyle('bold')"></button></div>
      <div class="prop-toggle-row"><span class="prop-toggle-label">Italic</span><button class="toggle ${field.style.italic ? 'on' : ''}" onclick="toggleStyle('italic')"></button></div>
    </div>
    <div class="prop-group"><div class="prop-group-title">Layout</div>
      <div class="prop-field"><label class="prop-label">Width</label><select class="prop-select" onchange="updateFieldProp('width', this.value)"><option value="100%" ${field.width==='100%'?'selected':''}>100% (Full)</option><option value="75%" ${field.width==='75%'?'selected':''}>75%</option><option value="50%" ${field.width==='50%'?'selected':''}>50%</option><option value="33%" ${field.width==='33%'?'selected':''}>33%</option><option value="25%" ${field.width==='25%'?'selected':''}>25%</option></select></div>
      <div class="prop-field"><label class="prop-label">CSS Class</label><input class="prop-input" value="${escHtml(field.cssClass)}" placeholder="custom-class" onchange="updateFieldProp('cssClass', this.value)"></div>
    </div>
  </div>`;
  html += `<div id="propTabValidation" style="display:none;">
    <div class="prop-group"><div class="prop-group-title">Validation Rules</div>
      <div class="prop-toggle-row"><span class="prop-toggle-label">Required</span><button class="toggle ${field.required ? 'on' : ''}" onclick="toggleProp('required')"></button></div>
    </div>
  </div>`;
  return html;
}

function attachPropListeners(field) {
  const lbl = document.getElementById('pLabel'); if (lbl) lbl.addEventListener('input', () => updateFieldProp('label', lbl.value));
  const ph = document.getElementById('pPlaceholder'); if (ph) ph.addEventListener('input', () => updateFieldProp('placeholder', ph.value));
  const ht = document.getElementById('pHelpText'); if (ht) ht.addEventListener('input', () => updateFieldProp('helpText', ht.value));
  const ct = document.getElementById('pContent'); if (ct) ct.addEventListener('input', () => updateFieldProp('content', ct.value));
  const bt = document.getElementById('pButtonText'); if (bt) bt.addEventListener('input', () => updateFieldProp('buttonText', bt.value));
}

function switchPropTab(btn, tab) {
  document.querySelectorAll('.prop-tab').forEach(b => b.classList.remove('active')); btn.classList.add('active');
  ['propTabGeneral','propTabStyle','propTabValidation'].forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
  const tabEl = document.getElementById('propTab' + tab.charAt(0).toUpperCase() + tab.slice(1)); if (tabEl) tabEl.style.display = 'block';
}

function updateFieldProp(prop, value) { const field = getSelectedField(); if (!field) return; field[prop] = value; render(); }
function toggleProp(prop) { const field = getSelectedField(); if (!field) return; field[prop] = !field[prop]; render(); renderProperties(); }
function updateStyle(prop, value) { const field = getSelectedField(); if (!field) return; field.style[prop] = value; }
function toggleStyle(prop) { const field = getSelectedField(); if (!field) return; field.style[prop] = !field.style[prop]; renderProperties(); }
function updateOption(idx, value) { const field = getSelectedField(); if (!field) return; field.options[idx] = value; }
function addOption() { const field = getSelectedField(); if (!field) return; field.options.push('New Option'); renderProperties(); render(); }
function removeOption(idx) { const field = getSelectedField(); if (!field) return; field.options.splice(idx, 1); renderProperties(); render(); }

function showCtxMenu(x, y) { const m = document.getElementById('ctxMenu'); m.style.left = x + 'px'; m.style.top = y + 'px'; m.classList.add('visible'); }
function hideCtxMenu() { document.getElementById('ctxMenu').classList.remove('visible'); }
document.addEventListener('click', hideCtxMenu);

function ctxDuplicate() { if (ctxTargetFieldId) { const row = formData.rows.find(r => r.cols.some(c => c.fields.some(f => f.id === ctxTargetFieldId))); if (row) { const ci = row.cols.findIndex(c => c.fields.some(f => f.id === ctxTargetFieldId)); duplicateField(ctxTargetFieldId, row.id, ci); } } hideCtxMenu(); }
function ctxDelete() { if (ctxTargetFieldId) { const row = formData.rows.find(r => r.cols.some(c => c.fields.some(f => f.id === ctxTargetFieldId))); if (row) { const ci = row.cols.findIndex(c => c.fields.some(f => f.id === ctxTargetFieldId)); deleteField(ctxTargetFieldId, row.id, ci); } } hideCtxMenu(); }
function ctxMoveUp() { if (ctxTargetFieldId) { const row = formData.rows.find(r => r.cols.some(c => c.fields.some(f => f.id === ctxTargetFieldId))); if (row) { const col = row.cols.find(c => c.fields.some(f => f.id === ctxTargetFieldId)); const i = col.fields.findIndex(f => f.id === ctxTargetFieldId); if (i > 0) { [col.fields[i-1], col.fields[i]] = [col.fields[i], col.fields[i-1]]; render(); } } } hideCtxMenu(); }
function ctxMoveDown() { if (ctxTargetFieldId) { const row = formData.rows.find(r => r.cols.some(c => c.fields.some(f => f.id === ctxTargetFieldId))); if (row) { const col = row.cols.find(c => c.fields.some(f => f.id === ctxTargetFieldId)); const i = col.fields.findIndex(f => f.id === ctxTargetFieldId); if (i < col.fields.length - 1) { [col.fields[i], col.fields[i+1]] = [col.fields[i+1], col.fields[i]]; render(); } } } hideCtxMenu(); }

function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
  const preview = document.getElementById('previewOverlay');
  const settings = document.getElementById('settingsOverlay');
  if (tab === 'preview') { preview.classList.add('visible'); settings.classList.remove('visible'); renderPreview(); }
  else if (tab === 'settings') { settings.classList.add('visible'); preview.classList.remove('visible'); }
  else { preview.classList.remove('visible'); settings.classList.remove('visible'); }
}

function copyPublicUrl() {
  const input = document.getElementById('publicUrlInput');
  if (!input) return;
  navigator.clipboard.writeText(input.value).then(() => {
    const msg = document.getElementById('copyMsg');
    if (msg) { msg.style.display = 'block'; setTimeout(() => msg.style.display = 'none', 3000); }
  }).catch(() => { input.select(); document.execCommand('copy'); });
}

function renderPreview() {
  document.getElementById('previewTitle').textContent = formData.title;
  document.getElementById('previewDesc').textContent = formData.description;
  const container = document.getElementById('previewFields'); container.innerHTML = '';
  formData.rows.forEach(row => {
    const rowEl = document.createElement('div');
    rowEl.className = row.cols.length > 1 ? 'preview-row' : '';
    row.cols.forEach(col => col.fields.forEach(field => rowEl.appendChild(renderPreviewField(field))));
    container.appendChild(rowEl);
  });
}

function renderPreviewField(field) {
  const wrap = document.createElement('div'); wrap.className = 'preview-field';
  const label = field.required ? `${escHtml(field.label)} <span style="color:var(--red)">*</span>` : escHtml(field.label);
  switch(field.type) {
    case 'text': case 'email': case 'phone': case 'number': case 'date': case 'time': case 'password':
      wrap.innerHTML = `<label class="preview-label">${label}</label><input class="preview-input" type="${field.type === 'phone' ? 'tel' : field.type}" placeholder="${escHtml(field.placeholder)}">`;
      break;
    case 'textarea': wrap.innerHTML = `<label class="preview-label">${label}</label><textarea class="preview-textarea" placeholder="${escHtml(field.placeholder)}"></textarea>`; break;
    case 'dropdown': wrap.innerHTML = `<label class="preview-label">${label}</label><select class="preview-select"><option>Select...</option>${(field.options||[]).map(o=>`<option>${escHtml(o)}</option>`).join('')}</select>`; break;
    case 'radio': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="display:flex;flex-direction:column;gap:10px;margin-top:4px;">${(field.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text-secondary);cursor:pointer;"><input type="radio" name="${field.id}" style="width:auto;accent-color:var(--accent);"> ${escHtml(o)}</label>`).join('')}</div>`; break;
    case 'checkbox': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="display:flex;flex-direction:column;gap:10px;margin-top:4px;">${(field.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text-secondary);cursor:pointer;"><input type="checkbox" style="width:auto;accent-color:var(--accent);"> ${escHtml(o)}</label>`).join('')}</div>`; break;
    case 'signature': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="height:110px;border:2px dashed var(--border);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:13px;background:var(--input-bg);cursor:crosshair;">✍️ Click to sign</div>`; break;
    case 'file': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="border:2px dashed var(--border);border-radius:9px;padding:28px;text-align:center;color:var(--text-muted);font-size:13px;background:var(--input-bg);cursor:pointer;">📎 Click to upload or drag & drop<br><span style="font-size:11px;">PDF, JPG, PNG up to 10MB</span></div>`; break;
    case 'header': wrap.innerHTML = `<h3 style="font-size:20px;font-weight:700;color:var(--text-primary);padding-bottom:8px;border-bottom:2px solid var(--border);">${escHtml(field.content)}</h3>`; break;
    case 'paragraph': wrap.innerHTML = `<p style="font-size:13px;color:var(--text-secondary);line-height:1.7;">${escHtml(field.content)}</p>`; break;
    case 'divider': wrap.innerHTML = `<hr style="border:none;border-top:1px solid var(--border);margin:8px 0;">`; break;
    case 'rating': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="display:flex;gap:6px;font-size:28px;color:var(--border);cursor:pointer;">★★★★★</div>`; break;
    case 'toggle': wrap.innerHTML = `<div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;"><label class="preview-label" style="margin:0;">${label}</label><div style="width:48px;height:26px;border-radius:13px;background:var(--border);position:relative;cursor:pointer;"><div style="width:20px;height:20px;border-radius:50%;background:#fff;position:absolute;top:3px;left:3px;box-shadow:0 1px 3px rgba(0,0,0,0.2);transition:left 0.2s;"></div></div></div>`; break;
    case 'submit': wrap.innerHTML = `<button class="preview-submit">${escHtml(field.buttonText || 'Submit Form')}</button>`; break;
    case 'name': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="display:flex;gap:12px;"><input class="preview-input" placeholder="First Name" style="flex:1;"><input class="preview-input" placeholder="Last Name" style="flex:1;"></div>`; break;
    case 'address': wrap.innerHTML = `<label class="preview-label">${label}</label><div style="display:flex;flex-direction:column;gap:10px;"><input class="preview-input" placeholder="Street Address"><div style="display:flex;gap:10px;"><input class="preview-input" placeholder="City" style="flex:1;"><input class="preview-input" placeholder="State" style="width:80px;"><input class="preview-input" placeholder="ZIP" style="width:90px;"></div></div>`; break;
    default: wrap.innerHTML = `<label class="preview-label">${label}</label><input class="preview-input" placeholder="${escHtml(field.placeholder)}">`;
  }
  return wrap;
}

function changeZoom(delta) {
  zoom = Math.min(150, Math.max(50, zoom + delta));
  document.getElementById('zoomLabel').textContent = zoom + '%';
  document.getElementById('canvasForm').style.transform = `scale(${zoom/100})`;
}

document.getElementById('formTitleInput').addEventListener('input', function() {
  formData.title = this.value;
  document.getElementById('canvasFormTitle').textContent = this.value;
});

function clearCanvas() {
  if (formData.rows.length === 0) return;
  if (confirm('Clear all fields from the canvas?')) {
    formData.rows = []; selectedFieldId = null; selectedRowId = null;
    render(); renderProperties(); showToast('Canvas cleared');
  }
}

function exportJSON() {
  const json = JSON.stringify(formData, null, 2);
  const blob = new Blob([json], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = (formData.title || 'form').replace(/\s+/g,'-').toLowerCase() + '.json';
  a.click(); URL.revokeObjectURL(url);
  showToast('JSON exported', 'success');
}

function saveForm(showNotification = true) {
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

  const payload = {
    name:        formData.title,
    description: formData.description,
    status:      formData.status,
    schema:      JSON.stringify({ rows: formData.rows }),
  };

  const formId = {{ isset($form) ? $form->id : 'null' }};
  const url    = formId
    ? `/forms/${formId}/schema`
    : '/forms';
  const method = formId ? 'POST' : 'POST';

  fetch(url, {
    method: method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        || '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
  .then(r => r.json())
  .then(res => {
    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = '💾 Save'; }
    if (res.status === 'success' || res.id) {
      if (showNotification) {
        showToast('Form saved successfully!', 'success');
        setTimeout(function() { window.location.href = '/forms'; }, 1200);
      }
    } else {
      if (showNotification) showToast('Save failed: ' + (res.message || 'Unknown error'), 'error');
    }
  })
  .catch(err => {
    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = '💾 Save'; }
    if (showNotification) showToast('Save failed. Please try again.', 'error');
    console.error('Save error:', err);
  });
}

function showToast(message, type = 'success') {
  // unified — delegate to the container-based showToast below
  _showBuilderToast(message, type);
}

function publishForm() {
  formData.status = 'active';
  document.getElementById('formStatusBadge').textContent = 'Published';
  document.getElementById('formStatusBadge').className = 'status-badge';
  const saveBtn = document.getElementById('saveBtn');
  const publishBtn = document.querySelector('[onclick="publishForm()"]');
  if (publishBtn) { publishBtn.disabled = true; publishBtn.textContent = 'Publishing…'; }

  const payload = {
    name:        formData.title,
    description: formData.description,
    status:      'active',
    schema:      JSON.stringify({ rows: formData.rows }),
  };
  const formId = {{ isset($form) ? $form->id : 'null' }};
  const url = formId ? `/forms/${formId}/schema` : '/forms';

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
  .then(r => r.json())
  .then(res => {
    if (publishBtn) { publishBtn.disabled = false; publishBtn.textContent = '🚀 Publish'; }
    if (res.status === 'success' || res.id) {
      showToast('Form published successfully!', 'success');
      setTimeout(function() { window.location.href = '/forms'; }, 1200);
    } else {
      showToast('Publish failed: ' + (res.message || 'Unknown error'), 'error');
    }
  })
  .catch(err => {
    if (publishBtn) { publishBtn.disabled = false; publishBtn.textContent = '🚀 Publish'; }
    showToast('Publish failed. Please try again.', 'error');
    console.error('Publish error:', err);
  });
}

function filterPalette(query) {
  const q = query.toLowerCase();
  document.querySelectorAll('.palette-item, .palette-item-wide').forEach(item => {
    const label = (item.dataset.label || '').toLowerCase();
    item.style.display = (label.includes(q) || q === '') ? '' : 'none';
  });
}

function _showBuilderToast(msg, type) {
  type = type || 'success';
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = 'toast ' + type;
  const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
  toast.innerHTML = `<i class="fas fa-${icon}"></i> ${msg}`;
  container.appendChild(toast);
  setTimeout(function() {
    toast.classList.add('hide');
    setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
  }, 4000);
}

document.getElementById('canvasWrap').addEventListener('click', e => {
  if (e.target === document.getElementById('canvasWrap') || e.target === document.getElementById('canvasForm') || e.target === document.getElementById('dropZone')) {
    selectedFieldId = null; selectedRowId = null; render(); renderProperties();
  }
});

document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  if ((e.key === 'Delete' || e.key === 'Backspace') && selectedFieldId) { e.preventDefault(); deleteSelected(); }
  if (e.key === 'd' && (e.ctrlKey || e.metaKey) && selectedFieldId) { e.preventDefault(); duplicateSelected(); }
  if (e.key === 's' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); saveForm(); }
});

function escHtml(str) {
  return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===================== INIT COUNTERS FROM EXISTING DATA =====================
// Scan all existing rows/fields so new IDs never collide with saved ones
(function initCounters() {
  formData.rows.forEach(function(row) {
    // row IDs like 'r3' -> extract numeric part
    const rNum = parseInt((row.id || '').replace(/^r/, ''), 10);
    if (!isNaN(rNum) && rNum > rowCounter) rowCounter = rNum;
    (row.cols || []).forEach(function(col) {
      (col.fields || []).forEach(function(field) {
        const fNum = parseInt((field.id || '').replace(/^f/, ''), 10);
        if (!isNaN(fNum) && fNum > fieldCounter) fieldCounter = fNum;
      });
    });
  });
})();

// Load existing schema if available
if (formData.rows.length > 0) { render(); }
</script>
</body>
</html>
