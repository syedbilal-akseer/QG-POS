<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarcodeScanLog extends Model
{
    protected $fillable = [
        'user_id', 'raw_input',
        'matched_item_code', 'matched_level',
        'success', 'failure_reason',
        'device', 'app_version',
    ];

    protected $casts = [
        'success'       => 'boolean',
        'matched_level' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
