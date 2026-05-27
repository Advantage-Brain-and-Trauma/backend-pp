<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'created_at',
        'updated_at',
    ];

    public function isValid(): bool
    {
        $cacheTtl = 86400 - 120;
        $issuedAt = $this->issuedAtUnix();
        if ($issuedAt <= 0) {
            return false;
        }

        $age = time() - $issuedAt;

        return $age < $cacheTtl;
    }

    protected function issuedAtUnix(): int
    {
        if (!empty($this->created_at_timestamp)) {
            return (int) $this->created_at_timestamp;
        }

        if (!empty($this->updated_at)) {
            $updatedAt = (string) $this->updated_at;
            if (ctype_digit($updatedAt)) {
                return (int) $updatedAt;
            }

            return Carbon::parse($this->updated_at)->timestamp;
        }

        if (!empty($this->created_at)) {
            $createdAt = (string) $this->created_at;
            if (ctype_digit($createdAt)) {
                return (int) $createdAt;
            }

            return Carbon::parse($this->created_at)->timestamp;
        }

        return 0;
    }
}
