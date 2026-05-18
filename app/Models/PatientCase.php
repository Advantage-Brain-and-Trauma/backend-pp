<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patient_cases';

    protected $fillable = [
        'user_id',
        'patient_id',
        'case_id',
    ];

    protected $dates = ['deleted_at'];

    /**
     * The patient (user) this case belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
