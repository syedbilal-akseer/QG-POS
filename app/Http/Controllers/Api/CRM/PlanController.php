<?php

namespace App\Http\Controllers\Api\CRM;

use Carbon\Carbon;
use App\Models\DayTourPlan;
use Illuminate\Http\Request;
use App\Models\MonthlyTourPlan;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    /*
     * Retrieve all Plans.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyTourPlans(): JsonResponse
    {
        $cacheKey = 'monthlyTourPlans_' . request()->fingerprint();
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $monthlyTourPlans = Cache::remember($cacheKey, $cacheTime, function () {
            return MonthlyTourPlan::with(['salesperson'])->where('salesperson_id', auth()->id())->get()->map(function ($monthlyTourPlan) {
                return [
                    'id' => $monthlyTourPlan->id,
                    'salesperson_id' => $monthlyTourPlan->salesperson_id,
                    'month' => $monthlyTourPlan->month,
                    'status' => ucwords($monthlyTourPlan->status),
                    'salesperson' => $monthlyTourPlan->salesperson->name,
                ];
            });
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Monthly Tour Plan retrieved successfully',
            'data' => $monthlyTourPlans,
        ], 200);
    }

    /*
     * Retrieve a single Plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyTourPlan(Request $request): JsonResponse
    {
        // Validate the request to ensure 'plan_id' is provided and exists
        $validated = $request->validate([
            'plan_id' => 'required|exists:monthly_tour_plans,id',
        ]);

        $cacheKey = 'monthlyTourPlan_' . $validated['plan_id'];
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $monthlyTourPlan = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            return MonthlyTourPlan::with(['salesperson', 'dayTourPlans'])->where('id', $validated['plan_id'])->first();
        });

        // Check if monthly tour plan found
        if (!$monthlyTourPlan) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Monthly Tour Plan not found',
                'data' => null,
            ], 404);
        }

        // Transform the monthly tour plan data
        $monthlyTourPlan = monthlyTourPlan($monthlyTourPlan);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Monthly Tour Plan retrieved successfully',
            'data' => $monthlyTourPlan,
        ], 200);
    }

    /**
     * Store a Monthly Tour Plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeMonthlyTourPlan(Request $request): JsonResponse
    {
        // Validate the request
        $validated = $request->validate([
            'month' => [
                'required',
                'date_format:F Y',
                Rule::unique('monthly_tour_plans')->where(function ($query) {
                    return $query->where('salesperson_id', auth()->id());
                }),
            ],
            'day_plans' => 'required|array',
            'day_plans.*.date' => 'required|date_format:d/m/Y',
            'day_plans.*.from_location' => 'required|string|max:255|different:day_plans.*.to_location',
            'day_plans.*.to_location' => 'required|string|max:255|different:day_plans.*.from_location',
            'day_plans.*.is_night_stay' => 'boolean',
            'day_plans.*.key_tasks' => 'nullable|array',
            'day_plans.*.key_tasks.*' => 'nullable|string',
        ], [
            'month.required' => 'The month field is required.',
            'month.date_format' => 'The month must be in the format "F Y" (e.g., "January 2024").',
            'month.unique' => 'A tour plan for this month already exists.',
            'day_plans.required' => 'The day plans field is required.',
            'day_plans.*.date.required' => 'The date field is required.',
            'day_plans.*.date.date_format' => 'The date must be in the format "d/m/Y" (e.g., "31/12/2024").',
            'day_plans.*.from_location.required' => 'The from location field is required.',
            'day_plans.*.from_location.different' => 'The from location and to location must be different.',
            'day_plans.*.to_location.required' => 'The to location field is required.',
            'day_plans.*.to_location.different' => 'The to location and from location must be different.',
            'day_plans.*.is_night_stay.boolean' => 'The is night stay field must be a boolean.',
            'day_plans.*.key_tasks.array' => 'The key tasks field must be an array.',
        ]);

        // Create the monthly tour plan
        $monthlyTourPlan = MonthlyTourPlan::create([
            'salesperson_id' => auth()->id(),
            'month' => $validated['month'],
        ]);

        // Save day plans
        foreach ($validated['day_plans'] as $dayPlanData) {
            $dayTourPlan = new DayTourPlan([
                'date' => Carbon::createFromFormat('d/m/Y', $dayPlanData['date']),
                'from_location' => $dayPlanData['from_location'],
                'to_location' => $dayPlanData['to_location'],
                'is_night_stay' => $dayPlanData['is_night_stay'] ?? false,
                'key_tasks' => $dayPlanData['key_tasks'],
            ]);

            // Associate the day plan with the monthly tour plan
            $monthlyTourPlan->dayTourPlans()->save($dayTourPlan);
        }

        // Transform the monthly tour plan data
        $monthlyTourPlan = monthlyTourPlan($monthlyTourPlan);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Monthly Tour Plan created successfully',
            'data' => $monthlyTourPlan,
        ], 201);
    }

    /*
     * Update a Plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMonthlyTourPlan(Request $request)
    {
        // Validate the request to ensure 'plan_id' is provided and exists
        $validated = $request->validate([
            'plan_id' => 'required|exists:monthly_tour_plans,id',
            'month' => 'required|date_format:F Y',
            'day_plans' => 'required|array',
            'day_plans.*.date' => 'required|date_format:d/m/Y',
            'day_plans.*.from_location' => 'required|string|max:255|different:day_plans.*.to_location',
            'day_plans.*.to_location' => 'required|string|max:255|different:day_plans.*.from_location',
            'day_plans.*.is_night_stay' => 'boolean',
            'day_plans.*.key_tasks' => 'nullable|array',
            'day_plans.*.key_tasks.*' => 'nullable|string',
        ], [
            'month.required' => 'The month field is required.',
            'month.date_format' => 'The month must be in the format "F Y" (e.g., "January 2024").',
            'day_plans.required' => 'The day plans field is required.',
            'day_plans.*.date.required' => 'The date field is required.',
            'day_plans.*.date.date_format' => 'The date must be in the format "d/m/Y" (e.g., "31/12/2024").',
            'day_plans.*.from_location.required' => 'The from location field is required.',
            'day_plans.*.from_location.string' => 'The from location must be a string.',
            'day_plans.*.from_location.max' => 'The from location may not be greater than :max characters.',
            'day_plans.*.from_location.different' => 'The from location and to location must be different.',
            'day_plans.*.to_location.required' => 'The to location field is required.',
            'day_plans.*.to_location.string' => 'The to location must be a string.',
            'day_plans.*.to_location.max' => 'The to location may not be greater than :max characters.',
            'day_plans.*.to_location.different' => 'The to location and from location must be different.',
            'day_plans.*.is_night_stay.boolean' => 'The is night stay field must be a boolean.',
            'day_plans.*.key_tasks.array' => 'The key tasks field must be an array.',
            'day_plans.*.key_tasks.*.string' => 'The key tasks must be a string.',
        ]);

        // Retrieve the monthly tour plan
        $monthlyTourPlan = MonthlyTourPlan::find($validated['plan_id']);

        // Check if monthly tour plan found
        if (!$monthlyTourPlan) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Monthly Tour Plan not found',
                'data' => null,
            ], 404);
        }

        // Update the monthly tour plan
        $monthlyTourPlan->update([
            'month' => $validated['month'],
        ]);

        // Clear existing day plans if you want to replace them
        $monthlyTourPlan->dayTourPlans()->delete();

        // Save new day plans
        foreach ($validated['day_plans'] as $dayPlanData) {
            $dayTourPlan = new DayTourPlan([
                'date' => Carbon::createFromFormat('d/m/Y', $dayPlanData['date']),
                'from_location' => $dayPlanData['from_location'],
                'to_location' => $dayPlanData['to_location'],
                'is_night_stay' => $dayPlanData['is_night_stay'] ?? false,
                'key_tasks' => $dayPlanData['key_tasks'],
            ]);

            // Associate the day plan with the monthly tour plan
            $monthlyTourPlan->dayTourPlans()->save($dayTourPlan);
        }

        // Transform the monthly tour plan data
        $monthlyTourPlan = monthlyTourPlan($monthlyTourPlan);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Monthly Tour Plan updated successfully',
            'data' => $monthlyTourPlan,
        ], 200);
    }
}
