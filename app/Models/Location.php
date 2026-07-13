<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'location',
        'parent_location_id',
    ];

    /**
     * Get users belonging to this location
     */
    public function users()
    {
        return $this->hasMany(User::class, 'location_id');
    }

    /**
     * Get parent location
     */
    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_location_id');
    }

    /**
     * Get child locations
     */
    public function children()
    {
        return $this->hasMany(Location::class, 'parent_location_id');
    }

    /**
     * Get OU IDs associated with this location
     */
    public function getOuIds()
    {
        switch ($this->id) {
            case 1: // KHI
                return [102, 103, 104, 105, 106];
            case 2: // LHR
                return [108, 109];
            default:
                return [];
        }
    }
}