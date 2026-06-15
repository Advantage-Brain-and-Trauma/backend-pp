<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProxyAccess extends Model
{
    use SoftDeletes;

    protected $table = 'proxy_access';

    protected $fillable = [
        'patient_user_id',
        'proxy_user_id',
        'proxy_email',
        'relationship',
        'access_level',
        'status',
        'invitation_token',
        'token_expires_at',
        'invited_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'invited_at'       => 'datetime',
        'accepted_at'      => 'datetime',
        'revoked_at'       => 'datetime',
    ];

    public function patientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function proxyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProxyAccessHistory::class, 'proxy_access_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
