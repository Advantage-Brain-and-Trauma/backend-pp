<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'sender_name', 'sender_type',
        'subject', 'body', 'category',
        'status', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id')->orderBy('created_at');
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }
}
