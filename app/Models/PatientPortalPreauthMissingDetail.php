<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientPortalPreauthMissingDetail extends Model
{
    use HasFactory;

    protected $table = 'patient_portal_preauth_missing_details';
    protected $connection = 'ahcs';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];
}
