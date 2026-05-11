<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientFunnelAssignment extends Model
{
    use SoftDeletes;

    protected $table = 'patient_funnel_assignments';

    protected $fillable = [
        'patient_id',
        'funnel_id',
        'assigned_by',
        'token',
        'status',
        'progress_percent',
        'forms_completed',
        'forms_total',
        'last_accessed_at',
        'completed_at',
        'expires_at',
        'note',
    ];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'completed_at'     => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function funnel()
    {
        return $this->belongsTo(Funnel::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function progress()
    {
        return $this->hasMany(FunnelProgress::class, 'assignment_id');
    }
}
