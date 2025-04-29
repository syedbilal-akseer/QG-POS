<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayTourPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_tour_plan_id',
        'date',
        'day',
        'from_location',
        'to_location',
        'is_night_stay',
        'key_tasks',
        'transferred_to',
        'transfer_status',
        'transfer_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'key_tasks' => 'json',
            'is_night_stay' => 'boolean',
        ];
    }

    // Define relationship to the MonthlyTourPlan
    public function monthlyTourPlan()
    {
        return $this->belongsTo(MonthlyTourPlan::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    } 

    // Accessor to automatically calculate the day of the week from the date
    public function getDayAttribute()
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d', $this->attributes['date'])->format('l');
    }

    // Accessor to decode 'key_tasks' JSON string to array
    public function getKeyTasksAttribute($value)
    {
        return json_decode($value, true); // Convert JSON string to array
    }

    // Mutator to encode 'key_tasks' array as JSON string before saving
    public function setKeyTasksAttribute($value)
    {
        $this->attributes['key_tasks'] = json_encode($value);
    }

    // Accessor to format the 'date' as d/m/Y
    public function getDateAttribute($value)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d', $value)->format('d/m/Y');
    }
}
