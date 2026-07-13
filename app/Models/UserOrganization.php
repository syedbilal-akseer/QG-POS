<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOrganization extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_organizations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'oracle_organization_code',
        'oracle_organization_name',
        'oracle_ou_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the organization relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by active organizations only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by organization code.
     */
    public function scopeByOrganization($query, string $organizationCode)
    {
        return $query->where('oracle_organization_code', $organizationCode);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this organization relationship is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the formatted organization display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->oracle_organization_name 
            ? "{$this->oracle_organization_code} - {$this->oracle_organization_name}"
            : $this->oracle_organization_code;
    }
}