<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\UserFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FunnelController extends Controller
{
    public function index(Request $request)
    {
        $query = Funnel::withCount('submissions');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $perPage = $request->per_page ?? 10;
        $funnels = $query->latest()->paginate($perPage)->withQueryString();

        $funnels->getCollection()->transform(function ($funnel) {
            $funnel->forms_count = count($funnel->form_ids ?? []);
            return $funnel;
        });

        return view('funnels.index', compact('funnels'));
    }

    public function create()
    {
        $forms = Form::orderBy('name')->get();
        return view('funnels.create', compact('forms'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:draft,active,archived',
            'form_ids'    => 'nullable|string',
        ]);

        $formIds = $this->decodeFormIds($request->form_ids);
        $steps   = $this->buildSteps($formIds);

        $funnel = Funnel::create([
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => $request->status ?? 'draft',
            'slug'        => Str::slug($request->name) . '-' . Str::random(6),
            'form_ids'    => $formIds,
            'steps'       => $steps,
            'created_by'  => Auth::id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'id'      => $funnel->id,
                'message' => 'Funnel saved successfully.',
            ]);
        }

        return redirect()->route('funnels.index')
            ->with('toast_success', 'Funnel "' . $funnel->name . '" created successfully.');
    }

    public function show(Funnel $funnel)
    {
        $funnel->load('submissions');
        $forms         = Form::orderBy('name')->get();
        $existingSteps = $this->getExistingSteps($funnel);
        return view('funnels.show', compact('funnel', 'forms', 'existingSteps'));
    }

    public function edit(Funnel $funnel)
    {
        $forms         = Form::orderBy('name')->get();
        $existingSteps = $this->getExistingSteps($funnel);
        return view('funnels.edit', compact('funnel', 'forms', 'existingSteps'));
    }

    public function update(Request $request, Funnel $funnel)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:draft,active,archived',
            'form_ids'    => 'nullable|string',
        ]);

        $formIds = $this->decodeFormIds($request->form_ids);
        $steps   = $this->buildSteps($formIds);

        $funnel->update([
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => $request->status ?? $funnel->status,
            'form_ids'    => $formIds,
            'steps'       => $steps,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Funnel updated successfully.',
            ]);
        }

        return redirect()->route('funnels.index')
            ->with('toast_success', 'Funnel updated successfully.');
    }

    public function destroy(Funnel $funnel)
    {
        $funnel->delete();
        return redirect()->route('funnels.index')
            ->with('toast_success', 'Funnel deleted successfully.');
    }

    /**
     * AJAX: Save funnel form_ids and name/description from the builder
     * Route: POST /funnels/{funnel}/schema
     */
    public function saveSchema(Request $request, Funnel $funnel)
    {
        $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'form_ids'    => 'required|array',
            'status'      => 'nullable|in:draft,active,archived',
        ]);

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
    }

    /**
     * AJAX: Publish a funnel (set status to active)
     * Route: POST /funnels/{funnel}/publish
     */
    public function publish(Funnel $funnel)
    {
        if (empty($funnel->slug)) {
            $funnel->slug = Str::slug($funnel->name) . '-' . Str::random(6);
        }
        $funnel->status = 'active';
        $funnel->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Funnel published successfully.',
            'url'     => url('/funnel/' . $funnel->slug),
        ]);
    }

    /**
     * Public funnel page — assign funnel to user on access.
     * - If logged in: assign immediately, then show the funnel.
     * - If not logged in: store slug in session, redirect to login.
     *   After login, LoginController will complete the assignment.
     */
    public function publicFunnel(string $slug)
    {
        $funnel = Funnel::where('slug', $slug)->where('status', 'active')->firstOrFail();

        if (Auth::check()) {
            // Assign funnel to the logged-in user (ignore if already assigned)
            UserFunnel::firstOrCreate(
                ['user_id' => Auth::id(), 'funnel_id' => $funnel->id],
                ['assigned_via' => 'share_link', 'assigned_at' => now()]
            );
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
    }

    /**
     * Submit a public funnel (saves each form submission)
     */
    public function submitPublicFunnel(Request $request, string $slug)
    {
        $funnel  = Funnel::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $formIds = $funnel->form_ids ?? [];

        foreach ($formIds as $formId) {
            $formData = $request->input('form_' . $formId, []);
            if (!empty($formData)) {
                FormSubmission::create([
                    'user_id'    => auth()->id(),
                    'form_id'    => $formId,
                    'funnel_id'  => $funnel->id,
                    'data'       => $formData,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        $funnel->increment('completion_count');

        return response()->json(['success' => true, 'message' => 'Thank you! Your forms have been submitted.']);
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
