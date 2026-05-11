<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionComment extends Model
{
    use SoftDeletes;

    protected $table = 'form_submission_comments';

    protected $fillable = [
        'form_submission_id',
        'comment',
        'commented_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * The form submission this comment belongs to.
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * The user who wrote the comment.
     */
    public function commentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commented_by');
    }
}
