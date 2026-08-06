<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index(Request $request)
    {
        Log::channel('admin_forms')->info('Forms index requested', $request->only(['search', 'status', 'sort', 'direction', 'per_page']));
        $query = Form::with('creator')->withCount('submissions');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->status === 'active') {
            $query->where('is_active', 1);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', 0);
        }

        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100]) ? (int) $request->per_page : 10;

        // Sorting
        $sortable = ['name' => 'name', 'status' => 'is_active', 'submissions' => 'submissions_count', 'created_by' => 'created_by', 'created_at' => 'created_at'];
        $sortCol = $request->sort ?? 'created_at';
        $sortDir = $request->direction === 'asc' ? 'asc' : 'desc';
        if (isset($sortable[$sortCol])) {
            if ($sortCol === 'submissions') {
                $query->orderBy('submissions_count', $sortDir);
            } else {
                $query->orderBy($sortable[$sortCol], $sortDir);
            }
        } else {
            $query->latest();
        }

        $forms = $query->paginate($perPage)->withQueryString();
        $currentSort = $sortCol;
        $currentDir  = $sortDir;

        // AJAX live-search: return only the table rows + pagination info
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $rowsHtml = view('forms.partials.table-rows', compact('forms'))->render();
            $paginationHtml = $forms->withQueryString()->links()->toHtml();
            return response()->json([
                'rows'       => $rowsHtml,
                'pagination' => $paginationHtml,
                'total'      => $forms->total(),
                'from'       => $forms->firstItem() ?? 0,
                'to'         => $forms->lastItem() ?? 0,
            ]);
        }

        return view('forms.index', compact('forms', 'currentSort', 'currentDir'));
    }

    public function create()
    {
        Log::channel('admin_forms')->info('Form create form requested');
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        return view('forms.create', compact('users'));
    }

    public function store(Request $request)
    {
        Log::channel('admin_forms')->info('Form store requested', $request->only(['name']));

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'success_msg'      => 'nullable|string',
            'thanks_msg'       => 'nullable|string',
            'assign_type'      => 'nullable|string|max:100',
            'assign_user_id'   => 'nullable|integer',
            'email'            => 'nullable|email|max:255',
            'ccemail'          => 'nullable|string',
            'bccemail'         => 'nullable|string',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['slug']       = Str::slug($validated['name']) . '-' . Str::random(6);
            $validated['fields']     = [];

            if (($validated['assign_type'] ?? '') !== 'user') {
                $validated['assign_user_id'] = null;
            }

            $form = Form::create($validated);

            Log::channel('admin_forms')->info('Form created successfully', ['form_id' => $form->id, 'name' => $form->name]);

            return redirect()->route('forms.builder', $form)
                ->with('success', 'Form created! Start building your form below.');
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error creating form', [
                'name'    => $request->name,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    public function show(Form $form)
    {
        Log::channel('admin_forms')->info('Form show requested', ['form_id' => $form->id]);
        $form->load('creator')->loadCount('submissions');
        return view('forms.show', compact('form'));
    }

    public function submissions(Form $form)
    {
        Log::channel('admin_forms')->info('Form submissions list requested', ['form_id' => $form->id]);
        $submissions = FormSubmission::with(['user', 'funnel'])
            ->where('form_id', $form->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('forms.submissions-index', compact('form', 'submissions'));
    }

    public function showSubmission(Form $form, FormSubmission $submission)
    {
        Log::channel('admin_forms')->info('Form submission show requested', ['form_id' => $form->id, 'submission_id' => $submission->id]);
        abort_if((int) $submission->form_id !== (int) $form->id, 404);

        $submission->load(['user', 'funnel']);
        $fields = $this->buildSubmissionDisplayFields($form, $submission);

        return view('forms.submission-show', compact('form', 'submission', 'fields'));
    }

    private function buildSubmissionDisplayFields(Form $form, FormSubmission $submission): array
    {
        $schema = is_array($form->fields) ? $form->fields : (json_decode($form->fields ?? '[]', true) ?: []);
        $rows = $schema['rows'] ?? (is_array($schema) ? $schema : []);
        $submittedData = is_array($submission->data) ? $submission->data : (json_decode($submission->data ?? '[]', true) ?: []);
        $displayFields = [];
        $knownFieldIds = [];

        foreach ($rows as $row) {
            foreach (($row['cols'] ?? []) as $col) {
                foreach (($col['fields'] ?? []) as $field) {
                    $fieldId = $field['id'] ?? $field['name'] ?? null;
                    $fieldType = $field['type'] ?? 'text';
                    $label = $field['label'] ?? $field['title'] ?? $field['placeholder'] ?? $this->humanizeFieldKey($fieldId ?: $fieldType);

                    if ($fieldId) {
                        $knownFieldIds[] = (string) $fieldId;
                    }

                    $value = $fieldId && array_key_exists($fieldId, $submittedData) ? $submittedData[$fieldId] : null;

                    $displayFields[] = [
                        'id' => $fieldId,
                        'label' => $label,
                        'type' => $fieldType,
                        'required' => (bool) ($field['required'] ?? false),
                        'value' => $this->formatSubmissionValue($value, $fieldType),
                    ];
                }
            }
        }

        foreach ($submittedData as $key => $value) {
            if (in_array((string) $key, $knownFieldIds, true)) {
                continue;
            }

            $displayFields[] = [
                'id' => $key,
                'label' => $this->humanizeFieldKey($key),
                'type' => 'text',
                'required' => false,
                'value' => $this->formatSubmissionValue($value, 'text'),
            ];
        }

        return $displayFields;
    }

    private function formatSubmissionValue($value, string $fieldType): array
    {
        if ($value === null || $value === '') {
            return ['kind' => 'empty', 'text' => '—'];
        }

        if (in_array($fieldType, ['file', 'image', 'signature'], true)) {
            if (is_array($value)) {
                $files = array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
                return [
                    'kind' => 'files',
                    'files' => array_map(fn ($item) => $this->submissionFilePayload((string) $item), $files),
                ];
            }

            if (is_string($value) && str_starts_with($value, 'data:image')) {
                return ['kind' => 'image', 'url' => $value, 'text' => 'Signature'];
            }

            return ['kind' => 'files', 'files' => [$this->submissionFilePayload((string) $value)]];
        }

        if (is_bool($value)) {
            return ['kind' => 'text', 'text' => $value ? 'Yes' : 'No'];
        }

        if (is_array($value)) {
            $flattened = [];
            foreach ($value as $key => $item) {
                if ($item === null || $item === '') {
                    continue;
                }
                $label = is_string($key) ? $this->humanizeFieldKey($key) . ': ' : '';
                $flattened[] = $label . (is_array($item) ? implode(', ', array_filter($item)) : $item);
            }

            return ['kind' => 'text', 'text' => !empty($flattened) ? implode("\n", $flattened) : '—'];
        }

        return ['kind' => 'text', 'text' => (string) $value];
    }

    private function submissionFilePayload(string $path): array
    {
        $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:'))
            ? $path
            : Storage::disk('public')->url($path);

        return [
            'name' => basename(parse_url($path, PHP_URL_PATH) ?: $path),
            'url' => $url,
            'is_image' => preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', parse_url($path, PHP_URL_PATH) ?: $path) === 1 || str_starts_with($path, 'data:image'),
        ];
    }

    private function humanizeFieldKey($key): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', (string) $key));
    }

    public function builder(Form $form)
    {
        Log::channel('admin_forms')->info('Form builder requested', ['form_id' => $form->id]);
        return view('forms.builder', compact('form'));
    }

    public function edit(Form $form)
    {
        Log::channel('admin_forms')->info('Form edit form requested', ['form_id' => $form->id]);
        $users = User::orderBy('name')->get();
        return view('forms.edit', compact('form', 'users'));
    }

    public function update(Request $request, Form $form)
    {
        Log::channel('admin_forms')->info('Form update requested', ['form_id' => $form->id]);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'success_msg'      => 'nullable|string',
            'thanks_msg'       => 'nullable|string',
            'assign_type'      => 'nullable|string|in:role,user,public,',
            'assign_role_value'=> 'nullable|string',
            'assign_user_id'   => 'nullable|integer',
            'email'            => 'nullable|email|max:255',
            'ccemail'          => 'nullable|string',
            'bccemail'         => 'nullable|string',
        ]);

        try {
            if (empty($form->slug)) {
                $form->slug = Str::slug($validated['name']) . '-' . Str::random(6);
            }

            $form->name        = $validated['name'];
            $form->description = $validated['description'] ?? null;
            $form->success_msg = $validated['success_msg'] ?? null;
            $form->thanks_msg  = $validated['thanks_msg'] ?? null;
            $form->assign_type = $validated['assign_type'] ?? null;
            $form->email       = $validated['email'] ?? null;
            $form->ccemail     = $validated['ccemail'] ?? null;
            $form->bccemail    = $validated['bccemail'] ?? null;

            if (($validated['assign_type'] ?? '') === 'user') {
                $form->assign_user_id = $validated['assign_user_id'] ?? null;
            } else {
                $form->assign_user_id = null;
            }

            $form->save();

            Log::channel('admin_forms')->info('Form updated successfully', ['form_id' => $form->id]);

            return redirect()->route('forms.builder', $form)
                ->with('success', 'Form settings saved.');
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error updating form', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    public function destroy(Form $form)
    {
        Log::channel('admin_forms')->info('Form destroy requested', ['form_id' => $form->id]);

        try {
            $form->delete();

            Log::channel('admin_forms')->info('Form deleted successfully', ['form_id' => $form->id]);

            if (request()->expectsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Form deleted successfully.',
                ]);
            }

            return redirect()->route('forms.index')
                ->with('toast_success', 'Form deleted successfully.');
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error deleting form', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        Log::channel('admin_forms')->info('Form bulkDestroy requested', ['ids' => $ids]);

        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'status'  => false,
                'message' => 'No forms selected.',
            ], 422);
        }

        try {
            $count = Form::whereIn('id', $ids)->delete();

            Log::channel('admin_forms')->info('Forms bulk deleted successfully', ['ids' => $ids, 'deleted' => $count]);

            return response()->json([
                'status'  => true,
                'message' => $count . ' form(s) deleted successfully.',
                'deleted' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error bulk deleting forms', [
                'ids'     => $ids,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * Duplicate a form — the copy is inserted directly below the original
     * by setting created_at to 1 second before the original's created_at.
     * Route: POST /forms/{form}/duplicate
     */
    public function duplicate(Form $form)
    {
        Log::channel('admin_forms')->info('Form duplicate requested', ['form_id' => $form->id]);

        try {
            $copy = $form->replicate();
            $copy->name           = $form->name . ' (copy)';
            $copy->slug           = Str::slug($form->name . '-copy') . '-' . Str::random(6);
            $copy->submission_count = 0;
            $copy->created_by     = Auth::id();
            // Use current time so the copy appears at the top of the latest() sorted list
            $copy->created_at     = now();
            $copy->updated_at     = now();
            $copy->save();

            Log::channel('admin_forms')->info('Form duplicated successfully', ['form_id' => $form->id, 'copy_id' => $copy->id]);

            return redirect()->route('forms.index')
                ->with('toast_success', '"' . $form->name . '" duplicated successfully.');
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error duplicating form', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * AJAX: Save the form builder schema (fields JSON) to the database
     * Route: POST /forms/{form}/schema
     */
    public function saveSchema(Request $request, Form $form)
    {
        Log::channel('admin_forms')->info('Form saveSchema requested', ['form_id' => $form->id]);

        try {
            // schema can arrive as a JSON string (from builder AJAX) or as an array
            $rawSchema = $request->input('schema');
            if (is_string($rawSchema)) {
                $decoded = json_decode($rawSchema, true);
                $schema  = $decoded ?? [];
            } else {
                $schema = $rawSchema ?? [];
            }

            // Save the schema (rows structure) into the fields column
            $form->fields = $schema;

            // Optionally update name and description from builder
            if ($request->has('name') && $request->name) {
                $form->name = $request->name;
            }
            if ($request->has('description')) {
                $form->description = $request->description;
            }

            // Ensure slug exists
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name) . '-' . Str::random(6);
            }

            $form->save();

            Log::channel('admin_forms')->info('Form schema saved successfully', ['form_id' => $form->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Form schema saved successfully.',
                'form'    => [
                    'id'          => $form->id,
                    'slug'        => $form->slug,
                    'url'         => $form->slug ? url('/f/' . $form->slug) : null,
                    'fields_count' => is_array($form->fields) ? count($form->fields) : 0,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error saving form schema', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * AJAX: Publish a form (set status to active)
     * Route: POST /forms/{form}/publish
     */
    public function publish(Form $form)
    {
        Log::channel('admin_forms')->info('Form publish requested', ['form_id' => $form->id]);

        try {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name) . '-' . Str::random(6);
            }
            $form->save();

            Log::channel('admin_forms')->info('Form published successfully', ['form_id' => $form->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Form published successfully.',
                'url'     => url('/f/' . $form->slug),
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error publishing form', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * AJAX: Toggle form status between active and draft
     * Route: POST /forms/{form}/toggle-status
     */
    public function toggleStatus(Form $form)
    {
        Log::channel('admin_forms')->info('Form toggleStatus requested', ['form_id' => $form->id]);

        try {
            $form->is_active = $form->is_active ? 0 : 1;
            $form->save();

            Log::channel('admin_forms')->info('Form status toggled successfully', ['form_id' => $form->id, 'is_active' => $form->is_active]);

            return response()->json([
                'status'     => 'success',
                'is_active'  => $form->is_active,
                'message'    => 'Form ' . ($form->is_active ? 'activated' : 'deactivated') . ' successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error toggling form status', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * Public form page — accessible by patients via the form's slug URL
     * Route: GET /f/{slug}
     */
    public function publicForm(string $slug)
    {
        Log::channel('admin_forms')->info('Public form page requested', ['slug' => $slug]);
        $form = Form::where('slug', $slug)->firstOrFail();
        return view('forms.public', compact('form'));
    }

    /**
     * Handle public form submission from patients
     * Route: POST /f/{slug}/submit
     */
    public function submitPublicForm(Request $request, string $slug)
    {
        Log::channel('admin_forms')->info('Public form submission requested', ['slug' => $slug]);

        try {
            $form = Form::where('slug', $slug)->firstOrFail();

            $submittedData = $request->input('fields', []);

            // Handle file uploads
            if ($request->hasFile('fields')) {
                foreach ($request->file('fields') as $fieldId => $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('form-uploads/' . $form->id, 'public');
                        $submittedData[$fieldId] = $path;
                    }
                }
            }

            // Determine status: 'completed' if at least one field has a non-null/non-empty value,
            // 'draft' if all fields are empty/null (partial or blank submission)
            $hasData = collect($submittedData)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
            $submissionStatus = $hasData ? 'completed' : 'draft';

            FormSubmission::create([
                'user_id'    => auth()->id(),
                'form_id'    => $form->id,
                'data'       => $submittedData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status'     => $submissionStatus,
            ]);

            $form->increment('submission_count');

            Log::channel('admin_forms')->info('Public form submitted successfully', ['slug' => $slug, 'form_id' => $form->id]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Form submitted successfully. Thank you!',
                ]);
            }

            return redirect()->back()->with('success', 'Form submitted successfully. Thank you!');
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error submitting public form', [
                'slug'    => $slug,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the public URL for a form (for admin panel display)
     */
    public function getPublicUrl(Form $form)
    {
        Log::channel('admin_forms')->info('Form getPublicUrl requested', ['form_id' => $form->id]);

        try {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name) . '-' . Str::random(6);
                $form->save();
            }
            $url = url('/f/' . $form->slug);
            return response()->json(['status' => 'success', 'url' => $url]);
        } catch (\Throwable $e) {
            Log::channel('admin_forms')->error('Error getting public URL for form', [
                'form_id' => $form->id,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }
}



