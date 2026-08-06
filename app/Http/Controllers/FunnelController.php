<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\UserFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FunnelController extends Controller
{
    public function index(Request $request)
    {
        Log::channel('admin_funnels')->info('Funnels index requested', $request->only(['search', 'sort', 'direction', 'per_page']));
        $query = Funnel::withCount('submissions');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $perPage = $request->per_page ?? 10;

        // Sorting
        $sortable = ['name' => 'name', 'created_at' => 'created_at'];
        $sortCol = $request->sort ?? 'created_at';
        $sortDir = $request->direction === 'asc' ? 'asc' : 'desc';
        if (isset($sortable[$sortCol])) {
            $query->orderBy($sortable[$sortCol], $sortDir);
        } else {
            $query->latest();
        }

        $funnels = $query->paginate($perPage)->withQueryString();
        $funnels->getCollection()->transform(function ($funnel) {
            $funnel->forms_count = count($funnel->form_ids ?? []);
            return $funnel;
        });
        $currentSort = $sortCol;
        $currentDir  = $sortDir;

        // AJAX live-search: return only the table rows + pagination info
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $rowsHtml = view('funnels.partials.table-rows', compact('funnels'))->render();
            return response()->json([
                'rows'  => $rowsHtml,
                'total' => $funnels->total(),
                'from'  => $funnels->firstItem() ?? 0,
                'to'    => $funnels->lastItem() ?? 0,
            ]);
        }

        return view('funnels.index', compact('funnels', 'currentSort', 'currentDir'));
    }

    public function create()
    {
        Log::channel('admin_funnels')->info('Funnel create form requested');
        $forms = Form::orderBy('name')->get();
        return view('funnels.create', compact('forms'));
    }

    public function store(Request $request)
    {
        Log::channel('admin_funnels')->info('Funnel store requested', $request->only(['name', 'status']));

        $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'insurance_type'   => 'nullable|array',
            'insurance_type.*' => 'in:PI,CASH,WC,DOL,COMM,MC,TriWest,ALL',
            'status'         => 'nullable|in:draft,active,archived',
            'form_ids'       => 'nullable|string',
        ]);

        try {
            $formIds = $this->decodeFormIds($request->form_ids);
            $steps   = $this->buildSteps($formIds);

            $funnel = Funnel::create([
                'name'           => $request->name,
                'description'    => $request->description,
                'insurance_type' => $request->insurance_type ?? [],
                'status'         => $request->status ?? 'draft',
                'slug'        => Str::slug($request->name) . '-' . Str::random(6),
                'form_ids'    => $formIds,
                'steps'       => $steps,
                'created_by'  => Auth::id(),
            ]);

            Log::channel('admin_funnels')->info('Funnel created successfully', ['funnel_id' => $funnel->id, 'name' => $funnel->name]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'success',
                    'id'      => $funnel->id,
                    'message' => 'Funnel saved successfully.',
                ]);
            }

            return redirect()->route('funnels.index')
                ->with('toast_success', 'Funnel "' . $funnel->name . '" created successfully.');
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error creating funnel', [
                'name'    => $request->name,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    public function show(Funnel $funnel)
    {
        Log::channel('admin_funnels')->info('Funnel show requested', ['funnel_id' => $funnel->id]);
        $funnel->load('submissions');
        $forms         = Form::orderBy('name')->get();
        $existingSteps = $this->getExistingSteps($funnel);
        return view('funnels.show', compact('funnel', 'forms', 'existingSteps'));
    }

    public function edit(Funnel $funnel)
    {
        Log::channel('admin_funnels')->info('Funnel edit form requested', ['funnel_id' => $funnel->id]);
        $forms         = Form::orderBy('name')->get();
        $existingSteps = $this->getExistingSteps($funnel);
        return view('funnels.edit', compact('funnel', 'forms', 'existingSteps'));
    }

    public function update(Request $request, Funnel $funnel)
    {
        Log::channel('admin_funnels')->info('Funnel update requested', ['funnel_id' => $funnel->id]);

        $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'insurance_type'   => 'nullable|array',
            'insurance_type.*' => 'in:PI,CASH,WC,DOL,COMM,MC,TriWest,ALL',
            'status'         => 'nullable|in:draft,active,archived',
            'form_ids'       => 'nullable|string',
        ]);

        try {
            $formIds = $this->decodeFormIds($request->form_ids);
            $steps   = $this->buildSteps($formIds);

            $funnel->update([
                'name'           => $request->name,
                'description'    => $request->description,
                'insurance_type' => $request->insurance_type ?? [],
                'status'         => $request->status ?? $funnel->status,
                'form_ids'    => $formIds,
                'steps'       => $steps,
            ]);

            Log::channel('admin_funnels')->info('Funnel updated successfully', ['funnel_id' => $funnel->id]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Funnel updated successfully.',
                ]);
            }

            return redirect()->route('funnels.index')
                ->with('toast_success', 'Funnel updated successfully.');
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error updating funnel', [
                'funnel_id' => $funnel->id,
                'message'   => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ]);
            throw $e;
        }
    }

    public function destroy(Funnel $funnel)
    {
        Log::channel('admin_funnels')->info('Funnel destroy requested', ['funnel_id' => $funnel->id]);

        try {
            $funnel->delete();

            Log::channel('admin_funnels')->info('Funnel deleted successfully', ['funnel_id' => $funnel->id]);

            return redirect()->route('funnels.index')
                ->with('toast_success', 'Funnel deleted successfully.');
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error deleting funnel', [
                'funnel_id' => $funnel->id,
                'message'   => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * AJAX: Save funnel form_ids and name/description from the builder
     * Route: POST /funnels/{funnel}/schema
     */
    public function saveSchema(Request $request, Funnel $funnel)
    {
        Log::channel('admin_funnels')->info('Funnel saveSchema requested', ['funnel_id' => $funnel->id]);

        $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'form_ids'    => 'required|array',
            'status'      => 'nullable|in:draft,active,archived',
        ]);

        try {
            $formIds = array_map('intval', $request->input('form_ids', []));
            $steps   = $this->buildSteps($formIds);

            if ($request->filled('name')) {
                $funnel->name = $request->name;
            }
            if ($request->has('description')) {
                $funnel->description = $request->description;
            }

            $funnel->form_ids = $formIds;
            $funnel->steps    = $steps;

            if ($request->has('status')) {
                $funnel->status = $request->status;
                if ($funnel->status === 'active' && empty($funnel->slug)) {
                    $funnel->slug = Str::slug($funnel->name) . '-' . Str::random(6);
                }
            }

            $funnel->save();

            Log::channel('admin_funnels')->info('Funnel schema saved successfully', ['funnel_id' => $funnel->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Funnel saved successfully.',
                'funnel'  => [
                    'id'          => $funnel->id,
                    'name'        => $funnel->name,
                    'status'      => $funnel->status,
                    'slug'        => $funnel->slug,
                    'url'         => $funnel->slug ? url('/funnel/' . $funnel->slug) : null,
                    'forms_count' => count($formIds),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error saving funnel schema', [
                'funnel_id' => $funnel->id,
                'message'   => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * AJAX: Publish a funnel (set status to active)
     * Route: POST /funnels/{funnel}/publish
     */
    public function publish(Funnel $funnel)
    {
        Log::channel('admin_funnels')->info('Funnel publish requested', ['funnel_id' => $funnel->id]);

        try {
            if (empty($funnel->slug)) {
                $funnel->slug = Str::slug($funnel->name) . '-' . Str::random(6);
            }
            $funnel->status = 'active';
            $funnel->save();

            Log::channel('admin_funnels')->info('Funnel published successfully', ['funnel_id' => $funnel->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Funnel published successfully.',
                'url'     => url('/funnel/' . $funnel->slug),
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error publishing funnel', [
                'funnel_id' => $funnel->id,
                'message'   => $e->getMessage(),
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * Public funnel page — assign funnel to user on access.
     * - If logged in: assign immediately, then show the funnel.
     * - If not logged in: store slug in session, redirect to login.
     *   After login, LoginController will complete the assignment.
     */
     public function publicFunnel(string $slug)
    {
        Log::channel('admin_funnels')->info('Public funnel page requested', ['slug' => $slug]);

        try {
            $funnel = Funnel::where('slug', $slug)->where('status', 'active')->firstOrFail();
            if (Auth::check()) {
                // Assign funnel to the logged-in user.
                // Use withTrashed()->updateOrCreate so that if a soft-deleted record exists
                // we restore it instead of inserting a new row (which would violate the unique index).
                UserFunnel::withTrashed()->updateOrCreate(
                    ['user_id' => Auth::id(), 'funnel_id' => $funnel->id],
                    ['assigned_via' => 'share_link', 'assigned_at' => now(), 'deleted_at' => null]
                );
                Log::channel('admin_funnels')->info('Funnel assigned via share link', ['funnel_id' => $funnel->id, 'user_id' => Auth::id()]);
            } else {
                // Store the funnel slug in session so we can assign after login
                session(['pending_funnel_slug' => $slug]);
                return redirect()->route('login')
                    ->with('info', 'Please log in to access this funnel.');
            }

            $formIds      = $funnel->form_ids ?? [];
            $forms        = Form::whereIn('id', $formIds)->get()->keyBy('id');
            $orderedForms = collect($formIds)->map(fn($id) => $forms->get($id))->filter()->values();

            return view('funnels.public', compact('funnel', 'orderedForms'));
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error loading public funnel', [
                'slug'    => $slug,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit a single form step within a funnel (called per-step via AJAX)
     * Route: POST /funnel/{slug}/submit-step/{formId}
     */
    public function submitFunnelStep(Request $request, string $slug, int $formId)
    {
        Log::channel('admin_funnels')->info('Funnel step submission requested', ['slug' => $slug, 'form_id' => $formId]);

        try {
            $funnel = Funnel::where('slug', $slug)->where('status', 'active')->firstOrFail();
            $form   = Form::findOrFail($formId);

            $formData = $request->input('fields', []);

            // Handle file uploads
            if ($request->hasFile('fields')) {
                foreach ($request->file('fields') as $fieldId => $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('form-uploads/' . $formId, 'public');
                        $formData[$fieldId] = $path;
                    }
                }
            }

            $hasData = collect($formData)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();

            FormSubmission::create([
                'user_id'    => auth()->id(),
                'form_id'    => $formId,
                'funnel_id'  => $funnel->id,
                'data'       => $formData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status'     => $hasData ? 'completed' : 'draft',
            ]);

            Log::channel('admin_funnels')->info('Funnel step submitted successfully', ['slug' => $slug, 'form_id' => $formId, 'funnel_id' => $funnel->id]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Step saved.',
            ]);
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error submitting funnel step', [
                'slug'    => $slug,
                'form_id' => $formId,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit a public funnel (saves each form submission)
     */
    public function submitPublicFunnel(Request $request, string $slug)
    {
        Log::channel('admin_funnels')->info('Public funnel submission requested', ['slug' => $slug]);

        try {
            $funnel  = Funnel::where('slug', $slug)->where('status', 'active')->firstOrFail();
            $formIds = $funnel->form_ids ?? [];

            foreach ($formIds as $formId) {
                $formData = $request->input('form_' . $formId, []);
                if (!empty($formData)) {
                    // 'completed' if at least one field has a real value, 'draft' if all blank
                    $hasData = collect($formData)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
                    FormSubmission::create([
                        'user_id'    => auth()->id(),
                        'form_id'    => $formId,
                        'funnel_id'  => $funnel->id,
                        'data'       => $formData,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'status'     => $hasData ? 'completed' : 'draft',
                    ]);
                }
            }

            $funnel->increment('completion_count');

            Log::channel('admin_funnels')->info('Public funnel submitted successfully', ['slug' => $slug, 'funnel_id' => $funnel->id]);

            return response()->json(['success' => true, 'message' => 'Thank you! Your forms have been submitted.']);
        } catch (\Throwable $e) {
            Log::channel('admin_funnels')->error('Error submitting public funnel', [
                'slug'    => $slug,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
            throw $e;
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function decodeFormIds(?string $json): array
    {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private function buildSteps(array $formIds): array
    {
        if (empty($formIds)) return [];
        $forms = Form::whereIn('id', $formIds)->get()->keyBy('id');
        return collect($formIds)->map(function ($id, $index) use ($forms) {
            $form = $forms->get($id);
            return [
                'order'   => $index + 1,
                'form_id' => $id,
                'name'    => $form?->name ?? 'Unknown Form',
            ];
        })->values()->toArray();
    }

    private function getExistingSteps(Funnel $funnel): array
    {
        $formIds = $funnel->form_ids ?? [];
        if (empty($formIds)) return [];
        $forms = Form::whereIn('id', $formIds)->get()->keyBy('id');
        return collect($formIds)->map(function ($id) use ($forms) {
            $form = $forms->get($id);
            return [
                'id'     => $id,
                'name'   => $form?->name ?? 'Unknown Form',
                'status' => ($form?->is_active ? 'active' : 'draft'),
                'slug'   => $form?->slug ?? '',
            ];
        })->filter(fn($s) => $s['name'] !== 'Unknown Form')->values()->toArray();
    }

}



