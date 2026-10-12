<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AhcsShortUrl extends Model
{
    use HasFactory;

    protected $connection = 'ahcs';
    protected $table = 'ahcs_short_urls';

    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];
}
