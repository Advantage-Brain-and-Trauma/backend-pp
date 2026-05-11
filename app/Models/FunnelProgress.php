<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunnelProgress extends Model
{
    use SoftDeletes;

    protected $table = 'funnel_progress';

    protected $fillable = [
        'user_id',
        'assignment_id',
        'patient_id',
        'funnel_id',
        'form_id',
        'step_index',
        'status',
        'data',
        'last_saved_at',
        'submitted_at',
    ];

    protected $casts = [
        'data'          => 'array',
        'last_saved_at' => 'datetime',
        'submitted_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function funnel()
    {
        return $this->belongsTo(Funnel::class);
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function assignment()
    {
        return $this->belongsTo(PatientFunnelAssignment::class);
    }
}
