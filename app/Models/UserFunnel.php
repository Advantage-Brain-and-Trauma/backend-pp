<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFunnel extends Model
{
    protected $fillable = [
        'user_id',
        'funnel_id',
        'assigned_via',
        'assigned_at',
    ];

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
}
