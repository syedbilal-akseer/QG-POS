<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OraclePriceListUpdate extends Model
{
    protected $connection = 'oracle';
    protected $table = 'apps.qg_price_list_updates';
    
    // Oracle doesn't use Laravel's created_at/updated_at by default
    public $timestamps = false;
    
    // Disable primary key for insert-only operations
    public $incrementing = false;
    protected $primaryKey = null;
    
    protected $fillable = [
        'list_header_id',
        'list_line_id',
        'item_code',
        'new_price',
        'currency_code',
        'start_date',
        'end_date',
        'processed_flag',
        'error_message',
        'processed_date',
    ];
    
    protected $casts = [
        'list_header_id' => 'integer',
        'list_line_id' => 'integer',
        'new_price' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'processed_date' => 'datetime',
    ];

    /**
     * Scope to filter by processed flag
     */
    public function scopeProcessed($query, $flag = 'Y')
    {
        return $query->where('processed_flag', $flag);
    }

    /**
     * Scope to get unprocessed records
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed_flag', 'N');
    }

    /**
     * Scope to filter by item code
     */
    public function scopeByItemCode($query, $itemCode)
    {
        return $query->where('item_code', $itemCode);
    }

    /**
     * Scope to filter by currency
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency_code', $currency);
    }

    /**
     * Scope to get records with errors
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Create multiple price update records
     */
    public static function createBulkUpdates(array $priceUpdates)
    {
        $data = [];
        
        foreach ($priceUpdates as $update) {
            $data[] = [
                'list_header_id' => $update['list_header_id'] ?? null,
                'list_line_id' => $update['list_line_id'] ?? null,
                'item_code' => $update['item_code'],
                'new_price' => $update['new_price'],
                'currency_code' => $update['currency_code'] ?? 'PKR',
                'start_date' => $update['start_date'] ?? now(),
                'end_date' => $update['end_date'] ?? null,
                'processed_flag' => 'N',
                'error_message' => null,
                'processed_date' => null,
            ];
        }
        
        return self::insert($data);
    }

    /**
     * Mark record as processed
     */
    public function markAsProcessed($errorMessage = null)
    {
        return $this->update([
            'processed_flag' => $errorMessage ? 'E' : 'Y',
            'error_message' => $errorMessage,
            'processed_date' => now(),
        ]);
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->new_price, 2) . ' ' . $this->currency_code;
    }

    /**
     * Get status text based on processed flag
     */
    public function getStatusTextAttribute()
    {
        switch ($this->processed_flag) {
            case 'Y':
                return 'Processed Successfully';
            case 'E':
                return 'Error';
            case 'N':
            default:
                return 'Pending';
        }
    }

    /**
     * Check if record has error
     */
    public function getHasErrorAttribute()
    {
        return !empty($this->error_message);
    }
}