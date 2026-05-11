<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionNote extends Model
{
    use SoftDeletes;

    protected $table = 'form_submission_notes';

    protected $fillable = [
        'form_submission_id',
        'note',
        'noted_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * The form submission this note belongs to.
     */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * The user who wrote the note.
     */
    public function notedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noted_by');
    }
}
