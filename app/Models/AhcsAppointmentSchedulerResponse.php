<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AhcsAppointmentSchedulerResponse extends Model
{
    use HasFactory;

    protected $connection = 'ahcs';
    protected $table = 'ahcs_appointment_scheduler_response';

    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];
}
