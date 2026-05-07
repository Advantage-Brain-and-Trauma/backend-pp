<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\UserFunnel;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class FunnelApiController extends Controller
{
    /**
     * GET /api/funnels
     *
     * Returns all active funnels with their name and full public URL.
     *
     * Response example:
     * {
     *   "status": true,
     *   "message": "Funnels retrieved successfully.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "akmal"
     *     }
     *   ]
     * }
     */

    public function getPatientFunnels(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Fetching patient funnels - Start', [
                'user_id' => $request->user()->id
            ]);

            $userFunnels = UserFunnel::where('user_id', $request->user()->id)
                ->pluck('funnel_id');

            Log::channel('patient_funnel')->info('User funnel IDs fetched', [
                'funnel_ids' => $userFunnels
            ]);

            $funnels = Funnel::whereIn('id', $userFunnels)
                ->where('status', 'active')
                ->get(['id', 'name']);

            $funnels->transform(function ($funnel) {
                return [
                    'id' => $funnel->id,
                    'funnel_name' => $funnel->name,
                ];
            });

            Log::channel('patient_funnel')->info('Fetching patient funnels - Success', [
                'user_id' => $request->user()->id,
                'total_funnels' => $funnels->count()
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Funnels retrieved successfully.',
                'data' => $funnels,
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_funnel')->error('Error fetching patient funnels', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error fetching patient form data'
            ], 500);
        }
    }


    public function getPatientFunnelSubmissionDetails($funnelId)
    {
        try {

            Log::channel('patient_portal')->info('Fetching patient funnel submission details', [
                'user_id' => auth()->id(),
                'funnel_id' => $funnelId
            ]);

            $funnelDetails = Funnel::where('id', $funnelId)
                ->first(['id', 'name', 'form_ids']);

            if (!$funnelDetails) {

                Log::channel('patient_portal')->warning('Funnel submission details not found', [
                    'funnel_id' => $funnelId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Funnel not found'
                ], 404);
            }

            $formIds = is_array($funnelDetails->form_ids)
                ? $funnelDetails->form_ids
                : json_decode($funnelDetails->form_ids, true);

            $formDetails = Form::whereIn('id', $formIds)
                ->get(['id', 'name', 'description', 'fields']);

            // Get all submissions
            $submissions = FormSubmission::whereIn('form_id', $formIds)
                ->where('user_id', auth()->id())
                ->where('funnel_id', $funnelId)
                ->get(['form_id', 'status']);

            // Add status inside each form
            $forms = $formDetails->map(function ($form) use ($submissions) {

                $submission = $submissions
                    ->where('form_id', $form->id)
                    ->first();

                return [
                    'id' => $form->id,
                    'name' => $form->name,
                    'description' => $form->description,
                    'submission_status' => $submission ? $submission->status : null,
                    'fields' => $form->fields,
                ];
            });

            Log::channel('patient_portal')->info('Patient funnel submission details fetched successfully', [
                'funnel_id' => $funnelId,
                'forms_count' => $forms->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Funnel submission details retrieved successfully.',
                'data' => [
                    'id' => $funnelDetails->id,
                    'funnel_name' => $funnelDetails->name,
                    'forms' => $forms
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_portal')->error('Error fetching patient funnel submission details', [
                'funnel_id' => $funnelId,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error fetching patient form data'
            ], 500);
        }
    }

    /**
     * POST /api/forms/{formId}/submit
     *
     * Store form submission data into form_submissions table.
     *
     * Request body (multipart/form-data or application/json):
     *   funnel_id  (optional) integer  - ID of the funnel this form belongs to
     *   fields     (required) object   - Key-value pairs of field_id => value
     *                                    For file fields, send the file under fields[fieldId]
     *
     * Example JSON body:
     * {
     *   "funnel_id": 1,
     *   "fields": {
     *     "f1": "John Doe",
     *     "f2": "john@example.com",
     *     "f3": "5551234567",
     *     "f4": ["Option 1", "Option 3"]
     *   }
     * }
     */
    public function submitForm(Request $request, int $formId)
    {
        // Validate the form exists
        $form = Form::find($formId);
        if (!$form) {
            return response()->json([
                'status'  => false,
                'message' => 'Form not found.',
            ], 404);
        }

        // Validate funnel if provided
        $funnelId = null;
        if ($request->filled('funnel_id')) {
            $funnel = Funnel::find($request->input('funnel_id'));
            if (!$funnel) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not found.',
                ], 404);
            }
            $funnelId = $funnel->id;
        }

        // Collect field data
        $formData = $request->input('fields', []);

        // Handle file uploads (multipart/form-data)
        if ($request->hasFile('fields')) {
            foreach ($request->file('fields') as $fieldId => $file) {
                if ($file && $file->isValid()) {
                    $path = $file->store('form-uploads/' . $formId, 'public');
                    $formData[$fieldId] = $path;
                }
            }
        }

        // Determine status: completed if any field has data, draft otherwise
        $hasData = collect($formData)->filter(fn($v) => $v !== null && $v !== '' && $v !== [])->isNotEmpty();

        $submission = FormSubmission::create([
            'user_id'    => auth()->id(),
            'form_id'    => $formId,
            'funnel_id'  => $funnelId,
            'data'       => $formData,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status'     => $hasData ? 'completed' : 'draft',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Form submitted successfully.',
            'data'    => [
                'submission_id' => $submission->id,
                'form_id'       => $submission->form_id,
                'funnel_id'     => $submission->funnel_id,
                'status'        => $submission->status,
                'submitted_at'  => $submission->created_at->toISOString(),
            ],
        ], 201);
    }
}
