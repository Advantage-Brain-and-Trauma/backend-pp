<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\FormSubmissionComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FormSubmissionNoteController extends Controller
{
    /**
     * GET /api/form-submissions/{submissionId}/comments
     * List all comments for a form submission.
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

        $comments = FormSubmissionComment::where('form_submission_id', $submissionId)
            ->with('commentedBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id'                 => $comment->id,
                    'form_submission_id' => $comment->form_submission_id,
                    'comment'            => $comment->comment,
                    'commented_by'       => $comment->commented_by,
                    'commented_by_name'  => $comment->commentedBy?->name,
                    'commented_by_email' => $comment->commentedBy?->email,
                    'created_at'         => $comment->created_at,
                    'updated_at'         => $comment->updated_at,
                ];
            });

        return response()->json([
            'status'  => true,
            'message' => 'Comments retrieved successfully.',
            'data'    => $comments,
        ], 200);
    }

    /**
     * POST /api/form-submissions/{submissionId}/comments
     * Add a new comment to a form submission.
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
            'comment' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $comment = FormSubmissionComment::create([
            'form_submission_id' => $submissionId,
            'comment'            => $request->input('comment'),
            'commented_by'       => Auth::id(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Comment added successfully.',
            'data'    => [
                'id'                 => $comment->id,
                'form_submission_id' => $comment->form_submission_id,
                'comment'            => $comment->comment,
                'commented_by'       => $comment->commented_by,
                'created_at'         => $comment->created_at,
                'updated_at'         => $comment->updated_at,
            ],
        ], 201);
    }

    /**
     * PUT /api/form-submissions/{submissionId}/comments/{commentId}
     * Update an existing comment.
     */
    public function update(Request $request, int $submissionId, int $commentId)
    {
        $comment = FormSubmissionComment::where('id', $commentId)
            ->where('form_submission_id', $submissionId)
            ->first();

        if (!$comment) {
            return response()->json([
                'status'  => false,
                'message' => 'Comment not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $comment->update([
            'comment' => $request->input('comment'),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Comment updated successfully.',
            'data'    => [
                'id'                 => $comment->id,
                'form_submission_id' => $comment->form_submission_id,
                'comment'            => $comment->comment,
                'commented_by'       => $comment->commented_by,
                'created_at'         => $comment->created_at,
                'updated_at'         => $comment->updated_at,
            ],
        ], 200);
    }

    /**
     * DELETE /api/form-submissions/{submissionId}/comments/{commentId}
     * Soft-delete a comment.
     */
    public function destroy(int $submissionId, int $commentId)
    {
        $comment = FormSubmissionComment::where('id', $commentId)
            ->where('form_submission_id', $submissionId)
            ->first();

        if (!$comment) {
            return response()->json([
                'status'  => false,
                'message' => 'Comment not found.',
            ], 404);
        }

        $comment->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Comment deleted successfully.',
        ], 200);
    }
}
