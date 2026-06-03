<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AhcsCase;
use App\Models\FormSubmission;
use App\Models\FormSubmissionNote;
use App\Models\UserFunnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RecentActivityController extends Controller
{
    /**
     * GET /api/recent-activity?case_id={caseId}&limit={limit}
     *
     * Returns a merged, time-sorted list of recent activities for the
     * authenticated patient:
     *   - form_submission  : patient submitted a funnel form
     *   - funnel_assigned  : a funnel was assigned to the patient
     *   - submission_note  : a note was added to one of their form submissions
     *
     * Query Params:
     *   - case_id  (required, int)
     *   - limit    (optional, int, default 20, max 50)
     *
     * Response 200:
     * {
     *   status: true,
     *   message: string,
     *   data: [
     *     {
     *       type: 'form_submission'|'funnel_assigned'|'submission_note',
     *       title: string,
     *       description: string,
     *       timestamp: ISO8601,
     *       meta: object   // type-specific extra fields
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId     = auth()->id();
            $patientIds = auth()->user()->getAllPatientIds();
            $caseId     = $request->query('case_id');
            $limit      = min((int) $request->query('limit', 20), 50);

            if (empty($caseId)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Case Id is required.',
                ], 422);
            }

            $isValidCase = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->exists();

            if (!$isValidCase) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid Case Id for this patient.',
                ], 422);
            }

            // Resolve the exact patient_id for this case for sub-queries.
            $patientId = AhcsCase::where('id', $caseId)->value('patient_id');

            Log::channel('patient_funnel')->info('Fetching recent activity', [
                'user_id'    => $userId,
                'patient_ids'=> $patientIds,
                'case_id'    => $caseId,
            ]);

            $activities = collect();

            // --- Form Submissions ---
            $submissions = FormSubmission::with(['form:id,name', 'funnel:id,name'])
                ->where('user_id', $userId)
                ->whereHas('userFunnel', function ($q) use ($caseId, $patientId) {
                    $q->whereHas('patientCase', function ($q2) use ($caseId, $patientId) {
                        $q2->where('case_id', $caseId)
                           ->where('patient_id', $patientId);
                    });
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            foreach ($submissions as $sub) {
                $activities->push([
                    'type'        => 'form_submission',
                    'title'       => 'Form Submitted',
                    'description' => 'You submitted the form: ' . ($sub->form->name ?? 'Unknown Form'),
                    'timestamp'   => $sub->created_at->toISOString(),
                    'meta'        => [
                        'submission_id' => $sub->id,
                        'form_id'       => $sub->form_id,
                        'form_name'     => $sub->form->name ?? null,
                        'funnel_id'     => $sub->funnel_id,
                        'funnel_name'   => $sub->funnel->name ?? null,
                        'status'        => $sub->status,
                        'pdf_url'       => $sub->pdf_url,
                    ],
                ]);
            }

            // --- Funnel Assignments ---
            $assignments = UserFunnel::with('funnel:id,name')
                ->where('user_id', $userId)
                ->whereHas('patientCase', function ($q) use ($caseId, $patientId) {
                    $q->where('case_id', $caseId)
                      ->where('patient_id', $patientId);
                })
                ->orderByDesc('assigned_at')
                ->limit($limit)
                ->get();

            foreach ($assignments as $uf) {
                $activities->push([
                    'type'        => 'funnel_assigned',
                    'title'       => 'Funnel Assigned',
                    'description' => 'A funnel was assigned to you: ' . ($uf->funnel->name ?? 'Unknown Funnel'),
                    'timestamp'   => ($uf->assigned_at ?? $uf->created_at)->toISOString(),
                    'meta'        => [
                        'user_funnel_id' => $uf->id,
                        'funnel_id'      => $uf->funnel_id,
                        'funnel_name'    => $uf->funnel->name ?? null,
                        'assigned_via'   => $uf->assigned_via,
                    ],
                ]);
            }

            // --- Submission Notes ---
            $submissionIds = FormSubmission::where('user_id', $userId)
                ->whereHas('userFunnel', function ($q) use ($caseId, $patientId) {
                    $q->whereHas('patientCase', function ($q2) use ($caseId, $patientId) {
                        $q2->where('case_id', $caseId)
                           ->where('patient_id', $patientId);
                    });
                })
                ->pluck('id');

            if ($submissionIds->isNotEmpty()) {
                $notes = FormSubmissionNote::with([
                        'formSubmission:id,form_id',
                        'formSubmission.form:id,name',
                        'notedBy:id,name',
                    ])
                    ->whereIn('form_submission_id', $submissionIds)
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get();

                foreach ($notes as $note) {
                    $activities->push([
                        'type'        => 'submission_note',
                        'title'       => 'Note Added',
                        'description' => 'A note was added to your form submission'
                            . ($note->formSubmission?->form?->name
                                ? ' (' . $note->formSubmission->form->name . ')'
                                : '') . '.',
                        'timestamp'   => $note->created_at->toISOString(),
                        'meta'        => [
                            'note_id'       => $note->id,
                            'note'          => $note->note,
                            'submission_id' => $note->form_submission_id,
                            'form_name'     => $note->formSubmission?->form?->name,
                            'noted_by'      => $note->notedBy?->name,
                        ],
                    ]);
                }
            }

            // Sort all activities newest first and apply the limit
            $result = $activities
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values();

            Log::channel('patient_funnel')->info('Recent activity fetched', [
                'user_id' => $userId,
                'count'   => $result->count(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Recent activity retrieved successfully.',
                'data'    => $result,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('patient_funnel')->error('Error fetching recent activity', [
                'user_id' => auth()->id() ?? null,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Error fetching recent activity.',
            ], 500);
        }
    }
}
