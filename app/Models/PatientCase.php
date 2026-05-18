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
        'patient_id',
        'case_id',
    ];

    protected $dates = ['deleted_at'];


}
