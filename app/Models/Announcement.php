<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'target_type',
        'target_value',
        'sent_at',
        'recipient_count',
        'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /** Human-friendly audience label for the list view. */
    public function audienceLabel(): string
    {
        if ($this->target_type === 'all') return 'Everyone';
        if ($this->target_type === 'role') {
            return 'Role: ' . ($this->target_value ?: '—');
        }
        return ucfirst($this->target_type);
    }
}
