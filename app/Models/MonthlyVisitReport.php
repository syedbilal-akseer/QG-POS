<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyVisitReport extends Model
{
    use HasFactory;


    protected $fillable = [
        'monthly_tour_plan_id',
        'salesperson_id',
        'month',
        'status',
        'line_manager_approval',
        'hod_approval',
        'rejection_reason',
    ];

    public function monthlyTourPlan()
    {
        return $this->belongsTo(MonthlyTourPlan::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

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
            ->first();
    }
}
