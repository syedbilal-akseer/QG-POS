<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorBill extends Model
{
    use HasFactory;

    public const STATUS_DRAFT                    = 'draft';
    public const STATUS_PENDING_DIRECTOR         = 'pending_director_approval';
    public const STATUS_PENDING_CMD              = 'pending_cmd_approval';
    public const STATUS_APPROVED                 = 'approved';
    public const STATUS_CLOSED                   = 'closed';
    public const STATUS_REJECTED                 = 'rejected';

    protected $fillable = [
        'vendor_id',
        'bill_number',
        'bill_date',
        'amount',
        'currency',
        'description',
        'status',
        'rejected_by_role',
        'uploaded_by',
        'director_approved_by',
        'director_approved_at',
        'cmd_approved_by',
        'cmd_approved_at',
        'cmd_deadline_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'bill_date'             => 'date',
        'amount'                => 'decimal:2',
        'director_approved_at'  => 'datetime',
        'cmd_approved_at'       => 'datetime',
        'cmd_deadline_at'       => 'datetime',
        'closed_at'             => 'datetime',
    ];

    public function vendor()           { return $this->belongsTo(Vendor::class); }
    public function uploader()         { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function directorApprover() { return $this->belongsTo(User::class, 'director_approved_by'); }
    public function cmdApprover()      { return $this->belongsTo(User::class, 'cmd_approved_by'); }
    public function closer()           { return $this->belongsTo(User::class, 'closed_by'); }
    public function attachments()      { return $this->hasMany(VendorBillAttachment::class); }
    public function approvals()        { return $this->hasMany(VendorBillApproval::class)->orderBy('acted_at'); }

    /** Pretty human-friendly status label for badges. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT            => 'Draft',
            self::STATUS_PENDING_DIRECTOR => 'Pending Director',
            self::STATUS_PENDING_CMD      => 'Pending CMD',
            self::STATUS_APPROVED         => 'Approved — awaiting admin close-out',
            self::STATUS_CLOSED           => 'Closed',
            self::STATUS_REJECTED         => 'Rejected — back to requester',
            default                       => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /** Whole hours left before the CMD SLA deadline; negative once overdue. */
    public function cmdHoursRemaining(): ?int
    {
        if (!$this->cmd_deadline_at) {
            return null;
        }
        $minutes = now()->diffInMinutes($this->cmd_deadline_at);
        $sign    = now()->lessThan($this->cmd_deadline_at) ? 1 : -1;
        return (int) floor($sign * $minutes / 60);
    }

    public function isCmdSlaBreached(): bool
    {
        return $this->status === self::STATUS_PENDING_CMD
            && $this->cmd_deadline_at
            && now()->greaterThan($this->cmd_deadline_at);
    }
}
