<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerVisit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'customer_id',
        'latitude',
        'longitude',
        'visit_start_time',
        'visit_end_time',
        'comments',
        'images',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'visit_start_time' => 'datetime',
        'visit_end_time' => 'datetime',
        'images' => 'array', // JSON array of image paths
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who made the visit.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer associated with the visit.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Scope to filter by user ID.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by customer ID.
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get visits for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('visit_start_time', $date);
    }

    /**
     * Scope to get visits within a date range.
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('visit_start_time', [$startDate, $endDate]);
    }

    /**
     * Scope to get ongoing visits.
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    /**
     * Scope to get completed visits.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the visit duration in minutes.
     */
    public function getDurationAttribute()
    {
        if ($this->visit_start_time && $this->visit_end_time) {
            return $this->visit_start_time->diffInMinutes($this->visit_end_time);
        }
        
        // If visit is ongoing, calculate duration from start to now
        if ($this->visit_start_time && $this->status === 'ongoing') {
            return $this->visit_start_time->diffInMinutes(now());
        }
        
        return null;
    }

    /**
     * Get formatted duration as string.
     */
    public function getFormattedDurationAttribute()
    {
        $duration = $this->duration;
        if ($duration === null) {
            return null;
        }

        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        if ($hours > 0) {
            return sprintf('%d hr %d min', $hours, $minutes);
        } else {
            return sprintf('%d min', $minutes);
        }
    }

    /**
     * Get the visit status with human-readable text.
     */
    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Check if visit is currently ongoing.
     */
    public function isOngoing(): bool
    {
        return $this->status === 'ongoing';
    }

    /**
     * Check if visit is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * End the visit and mark as completed.
     */
    public function endVisit($comments = null): bool
    {
        if (!$this->isOngoing()) {
            return false;
        }

        $this->update([
            'visit_end_time' => now(),
            'status' => 'completed',
            'comments' => $comments ?? $this->comments,
        ]);

        return true;
    }

    /**
     * Cancel the visit.
     */
    public function cancelVisit($reason = null): bool
    {
        if (!$this->isOngoing()) {
            return false;
        }

        $this->update([
            'visit_end_time' => now(),
            'status' => 'cancelled',
            'comments' => $reason ? ($this->comments ? $this->comments . ' | Cancelled: ' . $reason : 'Cancelled: ' . $reason) : $this->comments,
        ]);

        return true;
    }
}