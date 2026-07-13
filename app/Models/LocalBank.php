<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalBank extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'local_banks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_islamic',
        'is_microfinance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_islamic' => 'boolean',
        'is_microfinance' => 'boolean',
    ];

    /**
     * Scope to get Islamic banks only.
     */
    public function scopeIslamic($query)
    {
        return $query->where('is_islamic', true);
    }

    /**
     * Scope to get conventional banks only.
     */
    public function scopeConventional($query)
    {
        return $query->where('is_islamic', false);
    }

    /**
     * Scope to get microfinance banks only.
     */
    public function scopeMicrofinance($query)
    {
        return $query->where('is_microfinance', true);
    }

    /**
     * Scope to search banks by name.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'LIKE', "%{$term}%");
    }
}