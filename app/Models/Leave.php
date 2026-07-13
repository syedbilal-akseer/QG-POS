<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Leave extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'total_days',
        'lat',
        'lng',
        'status',
        'leave_type',
        'reason',
        'rejection_reason',
        'approved_by',
        'approved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be appended to arrays.
     *
     * @var array
     */
    protected $appends = ['formatted_duration', 'is_single_day'];

    /**
     * Get the user that owns the leave.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved/rejected the leave.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to filter by user ID.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by leave type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('leave_type', $type);
    }

    /**
     * Scope to get leaves for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
                     ->where('end_date', '>=', $date);
    }

    /**
     * Scope to get leaves within a date range.
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q) use ($startDate, $endDate) {
                  $q->where('start_date', '<=', $startDate)
                    ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Scope to get pending leaves.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved leaves.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected leaves.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Calculate total days automatically before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($leave) {
            if ($leave->start_date && $leave->end_date) {
                $start = Carbon::parse($leave->start_date);
                $end = Carbon::parse($leave->end_date);
                $leave->total_days = $start->diffInDays($end) + 1; // +1 to include both start and end dates
            }
        });
    }

    /**
     * Get formatted duration string.
     */
    public function getFormattedDurationAttribute()
    {
        if ($this->total_days == 1) {
            return '1 day';
        }
        return $this->total_days . ' days';
    }

    /**
     * Check if leave is for a single day.
     */
    public function getIsSingleDayAttribute()
    {
        return $this->total_days == 1;
    }

    /**
     * Check if leave can be edited (only pending leaves can be edited).
     */
    public function canBeEdited()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if leave can be cancelled (only pending and approved leaves can be cancelled).
     */
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'approved']) && 
               $this->start_date->isFuture();
    }

    /**
     * Check if leave overlaps with another leave.
     */
    public function hasOverlap($startDate, $endDate, $excludeId = null)
    {
        $query = static::where('user_id', $this->user_id)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get leave status badge color.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get leave type display name.
     */
    public function getLeaveTypeDisplayAttribute()
    {
        return match($this->leave_type) {
            'casual' => 'Casual Leave',
            'sick' => 'Sick Leave', 
            'annual' => 'Annual Leave',
            'emergency' => 'Emergency Leave',
            'other' => 'Other',
            default => ucfirst($this->leave_type)
        };
    }
}