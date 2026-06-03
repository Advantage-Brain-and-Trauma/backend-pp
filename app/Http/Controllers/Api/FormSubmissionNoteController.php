<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AhcsCase;
use App\Models\FormSubmission;
use App\Models\FormSubmissionNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FormSubmissionNoteController extends Controller
{
    private function resolveSubmissionForPatientCase(int $submissionId, int $patientId, int $caseId): ?FormSubmission
    {
        return FormSubmission::where('id', $submissionId)
            ->where('user_id', Auth::id())
            ->whereHas('userFunnel.patientCase', function ($q) use ($patientId, $caseId) {
                $q->where('patient_id', $patientId)->where('case_id', $caseId);
            })
            ->first();
    }

    /**
     * GET /api/form-submissions/{submissionId}/notes
     * List all notes for a form submission.
     */
    public function index(Request $request, int $submissionId)
    {
        $caseId = $request->input('case_id');
        $patientIds = auth()->user()->getAllPatientIds();

        if (empty($caseId)) {
            return response()->json([
                'status'  => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->whereIn('patient_id', $patientIds)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        $patientId = AhcsCase::where('id', $caseId)->value('patient_id');

        Log::channel('patient_form')->info('Fetching form submission notes started', [
            'submission_id' => $submissionId,
            'user_id'       => Auth::id(),
            'case_id'       => $caseId,
        ]);

        $submission = $this->resolveSubmissionForPatientCase((int) $submissionId, (int) $patientId, (int) $caseId);

        if (!$submission) {
            Log::channel('patient_form')->warning('Form submission not found while fetching notes', [
                'submission_id' => $submissionId,
                'user_id'       => Auth::id(),
                'case_id'       => $caseId,
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Form submission not found.',
            ], 404);
        }

        $notes = FormSubmissionNote::where('form_submission_id', $submissionId)
            ->with('notedBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($note) {
                return [
                    'id'                 => $note->id,
                    'form_submission_id' => $note->form_submission_id,
                    'note'               => $note->note,
                    'noted_by'           => $note->noted_by,
                    'noted_by_name'      => $note->notedBy?->name,
                    'noted_by_email'     => $note->notedBy?->email,
                    'created_at'         => $note->created_at,
                    'updated_at'         => $note->updated_at,
                ];
            });

        Log::channel('patient_form')->info('Form submission notes fetched successfully', [
            'submission_id' => $submissionId,
            'notes_count'   => $notes->count(),
            'user_id'       => Auth::id(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Notes retrieved successfully.',
            'data'    => $notes,
        ], 200);
    }

    /**
     * POST /api/form-submissions/{submissionId}/notes
     * Add a new note to a form submission.
     */
    public function store(Request $request, int $submissionId)
    {
        $caseId = $request->input('case_id');
        $patientIds = auth()->user()->getAllPatientIds();

        if (empty($caseId)) {
            return response()->json([
                'status'  => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->whereIn('patient_id', $patientIds)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        $patientId = AhcsCase::where('id', $caseId)->value('patient_id');

        Log::channel('patient_form')->info('Creating form submission note started', [
            'submission_id' => $submissionId,
            'user_id'       => Auth::id(),
            'case_id'       => $caseId,
        ]);

        $submission = $this->resolveSubmissionForPatientCase((int) $submissionId, (int) $patientId, (int) $caseId);

        if (!$submission) {
            Log::channel('patient_form')->warning('Form submission not found while creating note', [
                'submission_id' => $submissionId,
                'user_id'       => Auth::id(),
                'case_id'       => $caseId,
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Form submission not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            Log::channel('patient_form')->warning('Validation failed while creating form submission note', [
                'submission_id' => $submissionId,
                'user_id'       => Auth::id(),
                'errors'        => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $note = FormSubmissionNote::create([
            'form_submission_id' => $submissionId,
            'note'               => $request->input('note'),
            'noted_by'           => Auth::id(),
        ]);

        Log::channel('patient_form')->info('Form submission note created successfully', [
            'submission_id' => $submissionId,
            'note_id'       => $note->id,
            'user_id'       => Auth::id(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Note added successfully.',
            'data'    => [
                'id'                 => $note->id,
                'form_submission_id' => $note->form_submission_id,
                'note'               => $note->note,
                'noted_by'           => $note->noted_by,
                'created_at'         => $note->created_at,
                'updated_at'         => $note->updated_at,
            ],
        ], 201);
    }

    /**
     * PUT /api/form-submissions/{submissionId}/notes/{noteId}
     * Update an existing note.
     */
    public function update(Request $request, int $submissionId, int $noteId)
    {
        $caseId = $request->input('case_id');
        $patientIds = auth()->user()->getAllPatientIds();

        if (empty($caseId)) {
            return response()->json([
                'status'  => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->whereIn('patient_id', $patientIds)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        $patientId = AhcsCase::where('id', $caseId)->value('patient_id');

        Log::channel('patient_form')->info('Updating form submission note started', [
            'submission_id' => $submissionId,
            'note_id'       => $noteId,
            'user_id'       => Auth::id(),
            'case_id'       => $caseId,
        ]);

        $submission = $this->resolveSubmissionForPatientCase((int) $submissionId, (int) $patientId, (int) $caseId);
        if (!$submission) {
            return response()->json([
                'status'  => false,
                'message' => 'Form submission not found.',
            ], 404);
        }

        $note = FormSubmissionNote::where('id', $noteId)
            ->where('form_submission_id', $submissionId)
            ->first();

        if (!$note) {
            Log::channel('patient_form')->warning('Form submission note not found while updating note', [
                'submission_id' => $submissionId,
                'note_id'       => $noteId,
                'user_id'       => Auth::id(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            Log::channel('patient_form')->warning('Validation failed while updating form submission note', [
                'submission_id' => $submissionId,
                'note_id'       => $noteId,
                'user_id'       => Auth::id(),
                'errors'        => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $note->update([
            'note' => $request->input('note'),
        ]);

        Log::channel('patient_form')->info('Form submission note updated successfully', [
            'submission_id' => $submissionId,
            'note_id'       => $noteId,
            'user_id'       => Auth::id(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Note updated successfully.',
            'data'    => [
                'id'                 => $note->id,
                'form_submission_id' => $note->form_submission_id,
                'note'               => $note->note,
                'noted_by'           => $note->noted_by,
                'created_at'         => $note->created_at,
                'updated_at'         => $note->updated_at,
            ],
        ], 200);
    }

    /**
     * DELETE /api/form-submissions/{submissionId}/notes/{noteId}
     * Soft-delete a note.
     */
    public function destroy(Request $request, int $submissionId, int $noteId)
    {
        $caseId = $request->input('case_id');
        $patientIds = auth()->user()->getAllPatientIds();

        if (empty($caseId)) {
            return response()->json([
                'status'  => false,
                'message' => 'Case Id is required.',
            ], 422);
        }

        $isValidCaseForPatient = AhcsCase::where('id', $caseId)
            ->whereIn('patient_id', $patientIds)
            ->exists();

        if (!$isValidCaseForPatient) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid Case Id for this patient.',
            ], 422);
        }

        $patientId = AhcsCase::where('id', $caseId)->value('patient_id');

        Log::channel('patient_form')->info('Deleting form submission note started', [
            'submission_id' => $submissionId,
            'note_id'       => $noteId,
            'user_id'       => Auth::id(),
            'case_id'       => $caseId,
        ]);

        $submission = $this->resolveSubmissionForPatientCase((int) $submissionId, (int) $patientId, (int) $caseId);
        if (!$submission) {
            return response()->json([
                'status'  => false,
                'message' => 'Form submission not found.',
            ], 404);
        }

        $note = FormSubmissionNote::where('id', $noteId)
            ->where('form_submission_id', $submissionId)
            ->first();

        if (!$note) {
            Log::channel('patient_form')->warning('Form submission note not found while deleting note', [
                'submission_id' => $submissionId,
                'note_id'       => $noteId,
                'user_id'       => Auth::id(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->delete();

        Log::channel('patient_form')->info('Form submission note deleted successfully', [
            'submission_id' => $submissionId,
            'note_id'       => $noteId,
            'user_id'       => Auth::id(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Note deleted successfully.',
        ], 200);
    }
}
