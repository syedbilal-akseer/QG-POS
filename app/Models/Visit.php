<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_visit_report_id',
        'day_tour_plan_id',
        'customer_name',
        'area',
        'contact_person',
        'contact_no',
        'outlet_type',
        'shop_category',
        'visit_details',
        'findings_of_the_day',
        'competitors',
        'attachments',
        'status',
        'line_manager_approval',
        'hod_approval',
        'rejection_reason',
    ];

    protected $casts = [
        'competitors' => 'json',
        'line_manager_approval' => 'boolean',
        'hod_approval' => 'boolean',
        'attachments' => 'array',
        'competitors' => 'array'
    ];

    // public function getCompetitorsAttribute($value)
    // {
    //     return json_decode($value, true);
    // }

    // public function setCompetitorsAttribute($value)
    // {
    //     $this->attributes['competitors'] = json_encode($value);
    // }

    // public function getAttachmentsAttribute($value)
    // {
    //     return json_decode($value, true);
    // }

    // public function setAttachmentsAttribute($value)
    // {
    //     $this->attributes['attachments'] = json_encode($value);
    // }

    public function dayTourPlan()
    {
        return $this->belongsTo(DayTourPlan::class);
    }

    public function monthlyVisitReport()
    {
        return $this->belongsTo(MonthlyVisitReport::class);
    }

    public function expenses()
    {
        return $this->hasMany(VisitExpense::class);
    }
}
