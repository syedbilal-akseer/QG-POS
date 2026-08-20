<?php

namespace App\Models\WMS;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GatePass extends Model
{
    protected $table = 'wms_gate_passes';

    protected $fillable = [
        'gate_pass_number',
        'qr_token',
        'order_id',
        'type',
        'status',
        'created_by',
        'validated_by',
        'validated_at',
        'notes',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_VALIDATED = 'validated';
    const STATUS_CANCELLED = 'cancelled';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'GP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
        } while (static::where('gate_pass_number', $number)->exists());

        return $number;
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('qr_token', $token)->exists());

        return $token;
    }
}
