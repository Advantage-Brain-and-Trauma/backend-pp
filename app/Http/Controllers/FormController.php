<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $query = Form::with('creator');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->status === 'active') {
            $query->where('is_active', 1);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', 0);
        }

        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100]) ? (int) $request->per_page : 10;
        $forms = $query->latest()->paginate($perPage)->withQueryString();

        return view('forms.index', compact('forms'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        return view('forms.create', compact('users'));
    }

    public function store(Request $request)
    {
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

        $validated['created_by'] = Auth::id();
        $validated['slug']       = Str::slug($validated['name']) . '-' . Str::random(6);
        $validated['fields']     = [];

        if (($validated['assign_type'] ?? '') !== 'user') {
            $validated['assign_user_id'] = null;
        }

        $form = Form::create($validated);

        return redirect()->route('forms.builder', $form)
            ->with('success', 'Form created! Start building your form below.');
    }

    public function show(Form $form)
    {
        $form->load(['creator', 'submissions']);
        return view('forms.show', compact('form'));
    }

    public function builder(Form $form)
    {
        return view('forms.builder', compact('form'));
    }

    public function edit(Form $form)
    {
        $users = \App\Models\User::orderBy('name')->get();
        return view('forms.edit', compact('form', 'users'));
    }

    public function update(Request $request, Form $form)
    {
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

        return redirect()->route('forms.builder', $form)
            ->with('success', 'Form settings saved.');
    }

    public function destroy(Form $form)
    {
        $form->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'Form deleted successfully.',
            ]);
        }

        return redirect()->route('forms.index')
            ->with('toast_success', 'Form deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'status'  => false,
                'message' => 'No forms selected.',
            ], 422);
        }

        $count = Form::whereIn('id', $ids)->delete();

        return response()->json([
            'status'  => true,
            'message' => $count . ' form(s) deleted successfully.',
            'deleted' => $count,
        ]);
    }

    /**
     * Duplicate a form — the copy is inserted directly below the original
     * by setting created_at to 1 second before the original's created_at.
     * Route: POST /forms/{form}/duplicate
     */
    public function duplicate(Form $form)
    {
        $copy = $form->replicate();
        $copy->name           = $form->name . ' (copy)';
        $copy->slug           = Str::slug($form->name . '-copy') . '-' . Str::random(6);
        $copy->submission_count = 0;
        $copy->created_by     = Auth::id();
        // Use current time so the copy appears at the top of the latest() sorted list
        $copy->created_at     = now();
        $copy->updated_at     = now();
        $copy->save();

        return redirect()->route('forms.index')
            ->with('toast_success', '"' . $form->name . '" duplicated successfully.');
    }

    /**
     * AJAX: Save the form builder schema (fields JSON) to the database
     * Route: POST /forms/{form}/schema
     */
    public function saveSchema(Request $request, Form $form)
    {
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
    }

    /**
     * AJAX: Publish a form (set status to active)
     * Route: POST /forms/{form}/publish
     */
    public function publish(Form $form)
    {
        if (empty($form->slug)) {
            $form->slug = Str::slug($form->name) . '-' . Str::random(6);
        }
        $form->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Form published successfully.',
            'url'     => url('/f/' . $form->slug),
        ]);
    }

    /**
     * AJAX: Toggle form status between active and draft
     * Route: POST /forms/{form}/toggle-status
     */
    public function toggleStatus(Form $form)
    {
        $form->is_active = $form->is_active ? 0 : 1;
        $form->save();

        return response()->json([
            'status'     => 'success',
            'is_active'  => $form->is_active,
            'message'    => 'Form ' . ($form->is_active ? 'activated' : 'deactivated') . ' successfully.',
        ]);
    }

    /**
     * Public form page — accessible by patients via the form's slug URL
     * Route: GET /f/{slug}
     */
    public function publicForm(string $slug)
    {
        $form = Form::where('slug', $slug)->firstOrFail();
        return view('forms.public', compact('form'));
    }

    /**
     * Handle public form submission from patients
     * Route: POST /f/{slug}/submit
     */
    public function submitPublicForm(Request $request, string $slug)
    {
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Form submitted successfully. Thank you!',
            ]);
        }

        return redirect()->back()->with('success', 'Form submitted successfully. Thank you!');
    }

    /**
     * Get the public URL for a form (for admin panel display)
     */
    public function getPublicUrl(Form $form)
    {
        if (empty($form->slug)) {
            $form->slug = Str::slug($form->name) . '-' . Str::random(6);
            $form->save();
        }
        $url = url('/f/' . $form->slug);
        return response()->json(['status' => 'success', 'url' => $url]);
    }
}
