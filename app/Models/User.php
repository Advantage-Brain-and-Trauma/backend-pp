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
        'is_proxy_account',
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
        'is_proxy_account'   => 'boolean',
        'password'           => 'hashed',
        'patient_id'         => 'array',    // JSON array of patient IDs
    ];

    // -------------------------------------------------------------------------
    // Patient ID helpers
    // -------------------------------------------------------------------------

    /**
     * Normalise the patient_id column into a plain PHP int[].
     *
     * Handles three storage formats that may exist in the database:
     *   1. NULL                     → []
     *   2. Plain integer  (old rows where the column is still a scalar,
     *                      or JSON numeric literal 123 stored without array)
     *                               → [123]
     *   3. JSON array     [123, 456] (new format)
     *                               → [123, 456]
     */
    public function getAllPatientIds(): array
    {
        $raw = $this->patient_id; // may be null | int | array after 'array' cast

        if (is_null($raw)) {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('intval', $raw))));
        }

        // Scalar fallback: old rows stored as plain integer
        return [(int) $raw];
    }

    /**
     * Return only the patient IDs whose records are NOT soft-deleted in AhcsPatient.
     * Use this for all data-fetching queries so that deleted patients' data is excluded.
     */
    public function getActivePatientIds(): array
    {
        $allIds = $this->getAllPatientIds();

        if (empty($allIds)) {
            return [];
        }

        return AhcsPatient::whereIn('id', $allIds)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();
    }

    /**
     * Return the primary (first) patient ID.
     * Works whether patient_id is stored as a plain int or a JSON array.
     */
    public function getPrimaryPatientId(): ?int
    {
        $ids = $this->getAllPatientIds();
        return $ids[0] ?? null;
    }

    /**
     * Append a patient ID to this user's patient_id array without overwriting
     * any existing IDs.  Saves the model.
     */
    public function appendPatientId(int $patientId): void
    {
        $ids = $this->getAllPatientIds();
        if (!in_array($patientId, $ids, true)) {
            $ids[] = $patientId;
            $this->patient_id = $ids;
            $this->save();
        }
    }

    /**
     * Merge multiple patient IDs into this user's array without removing
     * any existing IDs.  Saves the model.
     */
    public function mergePatientIds(array $newIds): void
    {
        $existing = $this->getAllPatientIds();
        $merged   = array_values(array_unique(array_merge(
            $existing,
            array_map('intval', $newIds),
        )));
        $this->patient_id = $merged;
        $this->save();
    }

    /**
     * Query scope: match rows whose patient_id column contains the given ID.
     *
     * Handles both storage formats without breaking:
     *   - New format: JSON array  → MySQL JSON_CONTAINS
     *   - Old format: plain int   → direct equality
     */
    public function scopeHasPatientId(\Illuminate\Database\Eloquent\Builder $query, int $patientId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) use ($patientId) {
            $q->whereJsonContains('patient_id', $patientId)
              ->orWhere('patient_id', $patientId);
        });
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

    /** Injected at token-issue time to embed patient details into the JWT. */
    public ?array $jwtPatientDetails = null;

    /** Injected at token-issue time to embed a specific case_id into the JWT. */
    public ?int $jwtCaseId = null;

    /**
     * Injected at token-issue time when a proxy switches into a patient context.
     * Shape: [
     *   'proxy_access_id'    => int,
     *   'patient_user_id'    => int,
     *   'patient_ids'        => int[],
     *   'case_ids'           => int[],
     *   'access_level'       => string,
     * ]
     */
    public ?array $jwtProxyContext = null;

    public function getJWTCustomClaims()
    {
        $primaryPatientId = $this->getPrimaryPatientId();

        $caseId = $this->jwtCaseId
            ?? ($primaryPatientId
                ? optional(
                    PatientCase::where('patient_id', $primaryPatientId)
                               ->latest()
                               ->first()
                  )->case_id
                : null);

        // Use explicitly injected details, or auto-fetch from AhcsPatient.
        $patientDetails = $this->jwtPatientDetails;
        if ($patientDetails === null && $primaryPatientId) {
            $patient = AhcsPatient::find($primaryPatientId);
            if ($patient) {
                $patientDetails = [
                    'id'         => $patient->id,
                    'first_name' => $patient->first_name,
                    'last_name'  => $patient->last_name,
                    'full_name'  => $patient->patient_name,
                    'dob'        => $patient->dob,
                    'email'      => $patient->email,
                    'home_phone' => $patient->cell_no ?? $patient->home_ph,
                    'address1'   => $patient->address1,
                ];
            }
        }

        $claims = [
            'id'               => $this->id,
            'name'             => $patientDetails['full_name'] ?? $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'role'             => $this->role,
            'patient_id'       => $primaryPatientId,
            'patient_ids'      => $this->patient_id ?? [],
            'case_id'          => $caseId,
            'is_proxy_account' => (bool) $this->is_proxy_account,
        ];

        // Embed proxy context when a proxy switches into a patient's account.
        if ($this->jwtProxyContext !== null) {
            $claims['proxy_context'] = $this->jwtProxyContext;
        }

        return $claims;
    }
}
