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
}
