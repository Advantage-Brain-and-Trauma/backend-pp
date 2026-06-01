<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class FormSubmission extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id',
        'form_id', 'funnel_id', 'user_funnel_id',
        'patient_name', 'patient_email',
        'data', 'ip_address', 'user_agent', 'status',
        'pdf_url',
    ];
    protected $casts = [
        'data' => 'array',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(FormSubmissionNote::class, 'form_submission_id');
    }

    public function userFunnel(): BelongsTo
    {
        return $this->belongsTo(UserFunnel::class, 'user_funnel_id');
    }
}
