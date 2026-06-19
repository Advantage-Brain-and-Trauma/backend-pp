<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Funnel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'insurance_type', 'slug', 'steps', 'form_ids', 'status', 'created_by',
        'visit_count', 'completion_count', 'published_at',
    ];

    protected $casts = [
        'steps'        => 'array',
        'form_ids'     => 'array',
        'published_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/f/' . $this->slug);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($funnel) {
            if (empty($funnel->slug)) {
                $funnel->slug = Str::slug($funnel->name) . '-' . Str::random(6);
            }
        });
    }
}
