<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFunnel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'patient_id',
        'patient_case_id',
        'funnel_id',
        'assigned_via',
        'assigned_at',
    ];

    /**
     * When soft-deleting, stamp deleted_id = id so the unique constraint
     * (user_id, funnel_id, patient_case_id, deleted_id) is never blocked by
     * a previously deleted row. Active rows always have deleted_id = 0.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $model) {
            if (! $model->isForceDeleting()) {
                $model->deleted_id = $model->id;
                // Save only this column so we don't accidentally touch others.
                $model->saveQuietly(['deleted_id']);
            }
        });
    }

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function funnel()
    {
        return $this->belongsTo(Funnel::class);
    }

    public function patientCase()
    {
        return $this->belongsTo(PatientCase::class, 'patient_case_id','id');
    }
}

