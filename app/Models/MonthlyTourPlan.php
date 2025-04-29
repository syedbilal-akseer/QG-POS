<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyTourPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'salesperson_id',
        'month',
        'status',
        'line_manager_approval',
        'hod_approval',
        'rejection_reason',
    ];

    // Define relationship to DayTourPlans
    public function dayTourPlans()
    {
        return $this->hasMany(DayTourPlan::class);
    }

    // Define relationship to the Salesperson (User model)
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id', 'id');
    }

    public function getLineManager()
    {
        return $this->salesperson->manager;
    }

    public function getHod()
    {
        return User::where('department_id', $this->salesperson->department_id)
            ->whereHas('role', fn($q) => $q->where('name', 'hod'))
            ->orWhereHas('role', fn($q) => $q->where('name', 'admin'))
            ->first();
    }
}
