<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProxyAccessHistory extends Model
{
    protected $table = 'proxy_access_history';

    protected $fillable = [
        'proxy_access_id',
        'proxy_user_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function proxyAccess(): BelongsTo
    {
        return $this->belongsTo(ProxyAccess::class, 'proxy_access_id');
    }

    public function proxyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_user_id');
    }
}
