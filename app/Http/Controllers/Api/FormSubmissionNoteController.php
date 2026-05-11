<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormSubmissionNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FormSubmissionNoteController extends Controller
{
    /**
     * GET /api/form-submissions/{submissionId}/notes
     * List all notes for a form submission.
     */
    public function index(int $submissionId)
    {
        $submission = FormSubmission::find($submissionId);

        if (!$submission) {
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
        $submission = FormSubmission::find($submissionId);

        if (!$submission) {
            return response()->json([
                'status'  => false,
                'message' => 'Form submission not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
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
        $note = FormSubmissionNote::where('id', $noteId)
            ->where('form_submission_id', $submissionId)
            ->first();

        if (!$note) {
            return response()->json([
                'status'  => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $note->update([
            'note' => $request->input('note'),
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
    public function destroy(int $submissionId, int $noteId)
    {
        $note = FormSubmissionNote::where('id', $noteId)
            ->where('form_submission_id', $submissionId)
            ->first();

        if (!$note) {
            return response()->json([
                'status'  => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $note->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Note deleted successfully.',
        ], 200);
    }
}
