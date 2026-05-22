<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvancedmdToken extends Model
{
    protected $connection = 'ahcs';
    protected $table = 'ahcs_advancedmd_tokens';
    public $timestamps = false;

    protected $fillable = [
        'office_key',
        'token',
        'webserver',
        'created_at_timestamp',
    ];

    public function isValid(): bool
    {
        $cacheTtl = 86400 - 120;
        $age = time() - (int) $this->created_at_timestamp;

        return $age < $cacheTtl;
    }
}

