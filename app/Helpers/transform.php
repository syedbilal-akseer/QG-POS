<?php

if (! function_exists('monthlyTourPlan')) {
    /**
     * Transform a MonthlyTourPlan into an array structure.
     *
     * @param  \App\Models\MonthlyTourPlan  $monthlyTourPlan
     * @return array
     */
    function monthlyTourPlan(\App\Models\MonthlyTourPlan $monthlyTourPlan): array
    {
        return [
            'id' => $monthlyTourPlan->id,
            'salesperson_id' => $monthlyTourPlan->salesperson_id,
            'month' => $monthlyTourPlan->month,
            'status' => ucwords($monthlyTourPlan->status),
            'salesperson' => $monthlyTourPlan->salesperson->name,
            'day_plans' => $monthlyTourPlan->dayTourPlans->map(function ($dayPlan) {
                return [
                    'id' => $dayPlan->id,
                    'date' => $dayPlan->date,
                    'from_location' => $dayPlan->from_location,
                    'to_location' => $dayPlan->to_location,
                    'is_night_stay' => $dayPlan->is_night_stay,
                    'key_tasks' => $dayPlan->key_tasks,
                ];
            }),
        ];
    }
}

if (!function_exists('monthlyVisitReport')) {
    /**
     * Transform a MonthlyVisitReport into an array structure.
     *
     * @param  \App\Models\MonthlyVisitReport  $monthlyVisitReport
     * @return array
     */
    function monthlyVisitReport(\App\Models\MonthlyVisitReport $monthlyVisitReport): array
    {
        return [
            'id' => $monthlyVisitReport->id,
            'salesperson_id' => $monthlyVisitReport->salesperson_id,
            'month' => $monthlyVisitReport->month,
            'status' => ucwords($monthlyVisitReport->status),
            'salesperson' => $monthlyVisitReport->salesperson->name,
            'visits' => $monthlyVisitReport->visits->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'day_tour_plan_id' => $visit->day_tour_plan_id,
                    'customer_name' => $visit->customer_name,
                    'area' => $visit->area,
                    'contact_person' => $visit->contact_person,
                    'contact_no' => $visit->contact_no,
                    'outlet_type' => $visit->outlet_type,
                    'shop_category' => $visit->shop_category,
                    'visit_details' => $visit->visit_details,
                    'findings_of_the_day' => $visit->findings_of_the_day,
                    'competitors' => collect($visit->competitors)->map(function ($competitor) {
                        return transformCompetitor($competitor);
                    }),
                    'status' => ucwords($visit->status),
                    'attachments' => collect($visit->attachments)->map(function ($filePath) {
                        return [
                            'file_name' => basename($filePath),
                            'file_url' => asset('storage/' . $filePath),
                        ];
                    }),
                    'has_expenses' => $visit->expenses->isNotEmpty(),
                ];
            }),
        ];
    }
}

if (!function_exists('visit')) {
    /**
     * Transform a Visit into an array structure.
     *
     * @param  \App\Models\Visit  $visit
     * @return array
     */
    function visit(\App\Models\Visit $visit): array
    {
        return [
            'id' => $visit->id,
            'day_tour_plan_id' => $visit->day_tour_plan_id,
            'customer_name' => $visit->customer_name,
            'area' => $visit->area,
            'contact_person' => $visit->contact_person,
            'contact_no' => $visit->contact_no,
            'outlet_type' => $visit->outlet_type,
            'shop_category' => $visit->shop_category,
            'visit_details' => $visit->visit_details,
            'findings_of_the_day' => $visit->findings_of_the_day,
            'competitors' => collect($visit->competitors)->map(function ($competitor) {
                return transformCompetitor($competitor);
            }),
            'status' => ucwords($visit->status),
            'attachments' => collect($visit->attachments)->map(function ($filePath) {
                return [
                    'file_name' => basename($filePath), // Extract file name
                    'file_url' => asset('storage/' . $filePath), // Generate full URL
                ];
            }),
        ];
    }
}

if (!function_exists('transformCompetitor')) {
    /**
     * Transform a Competitor into an array structure.
     *
     * @param  \App\Models\Competitor|array  $competitor
     * @return array
     */
    function transformCompetitor($competitor): array
    {
        return [
            'name' => $competitor['name'] ?? $competitor->name,
            'product' => $competitor['product'] ?? $competitor->product,
            'size' => $competitor['size'] ?? $competitor->size,
            'details' => $competitor['details'] ?? $competitor->details,
            'attachments' => collect($competitor['attachments'] ?? $competitor->attachments)->map(function ($filePath) {
                return [
                    'file_name' => basename($filePath),
                    'file_url' => asset('storage/' . $filePath),
                ];
            }),
        ];
    }
}
