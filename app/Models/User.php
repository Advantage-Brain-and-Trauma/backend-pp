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
        'patient_id',           // JSON array of patient IDs
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
        'patient_id'         => 'array',    // JSON array of patient IDs
    ];

    // -------------------------------------------------------------------------
    // Patient ID helpers
    // -------------------------------------------------------------------------

    /**
     * Return the primary (first) patient ID from the JSON array.
     * Used wherever a single integer patient ID is required (queries, JWT, etc.).
     */
    public function getPrimaryPatientId(): ?int
    {
        $ids = $this->patient_id;
        if (empty($ids) || !is_array($ids)) {
            return null;
        }
        $first = $ids[0] ?? null;
        return $first !== null ? (int) $first : null;
    }

    /**
     * Append a patient ID to this user's patient_id JSON array if it is not
     * already present.  Saves the model afterwards.
     */
    public function appendPatientId(int $patientId): void
    {
        $ids = $this->patient_id ?? [];
        if (!in_array($patientId, $ids, true)) {
            $ids[] = $patientId;
            $this->patient_id = $ids;
            $this->save();
        }
    }

    /**
     * Merge an array of patient IDs into this user's patient_id column without
     * removing any existing values.  Saves the model afterwards.
     */
    public function mergePatientIds(array $newIds): void
    {
        $existing = $this->patient_id ?? [];
        $merged   = array_values(array_unique(array_merge(
            array_map('intval', $existing),
            array_map('intval', $newIds),
        )));
        $this->patient_id = $merged;
        $this->save();
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The AhcsPatient record for this user's *primary* patient ID.
     *
     * patient_id is now a JSON array; we resolve the primary element via an
     * accessor so that $this->patient continues to work everywhere.
     */
    public function getPatientAttribute(): ?AhcsPatient
    {
        $primaryId = $this->getPrimaryPatientId();
        return $primaryId ? AhcsPatient::find($primaryId) : null;
    }

    /**
     * All PatientCase rows for this user's *primary* patient ID.
     *
     * Because patient_id is a JSON array we cannot use the standard hasMany
     * local-key binding.  This method returns a query builder scoped to the
     * primary patient, which supports both $user->cases and
     * $user->cases()->latest()->first() patterns.
     */
    public function cases()
    {
        $primaryId = $this->getPrimaryPatientId();
        return PatientCase::where('patient_id', $primaryId ?? 0);
    }

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

    // -------------------------------------------------------------------------
    // JWT
    // -------------------------------------------------------------------------

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        $primaryPatientId = $this->getPrimaryPatientId();

        return [
            'id'          => $this->id,
            'name'        => $primaryPatientId && $this->patient
                                ? $this->patient->patient_name
                                : $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'role'        => $this->role,
            // Primary patient ID (single integer) for backward compatibility.
            'patient_id'  => $primaryPatientId,
            // Full array of patient IDs associated with this user.
            'patient_ids' => $this->patient_id ?? [],
            'case_id'     => $primaryPatientId
                                ? optional(
                                    PatientCase::where('patient_id', $primaryPatientId)
                                               ->latest()
                                               ->first()
                                  )->case_id
                                : null,
        ];
    }
}
