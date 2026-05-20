<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'last_login_at',
        'patient_id',
        // New columns added from patient-portal users table
        'avatar',
        'address',
        'country',
        'messenger_color',
        'country_code',
        'phone_verified_at',
        'UniqueId',
        'dark_mode',
        'lang',
        'social_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'last_login_at'      => 'datetime',
        'phone_verified_at'  => 'datetime',
        'is_active'          => 'boolean',
        'dark_mode'          => 'boolean',
        'password'           => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function forms()
    {
        return $this->hasMany(Form::class, 'created_by');
    }

    public function funnels()
    {
        return $this->hasMany(Funnel::class, 'created_by');
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function patient()
    {
        return $this->belongsTo(AhcsPatient::class, 'patient_id', 'id');
    }

    public function getJWTCustomClaims()
    {
        return [
            'id'    => $this->id,
            'name' => $this->patient_id && $this->patient
                    ? $this->patient->patient_name
                    : $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role'  => $this->role,
            'case_id' => $this->patient_id && $this->patient
                    ? optional($this->patient->cases()->latest()->first())->case_id
                    : null,
        ];
    }

    public function cases()
    {
        return $this->hasMany(PatientCase::class, 'patient_id', 'patient_id');
    }

}
