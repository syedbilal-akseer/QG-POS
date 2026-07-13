<?php

namespace App\Http\Controllers\Api\CRM;

use App\Models\Visit;
use App\Models\DayTourPlan;
use App\Models\VisitExpense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MonthlyVisitReport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketVisitReportController extends Controller
{
    /*
     * Retrieve all Market Visit Reports.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyVisitReports(): JsonResponse
    {
        $cacheKey = 'monthlyVisitReports_' . request()->fingerprint();
        $cacheTime = 60;

        // Attempt to retrieve data from cache
        $monthlyVisitReports = Cache::remember($cacheKey, $cacheTime, function () {
            return MonthlyVisitReport::with(['salesperson'])->where('salesperson_id', auth()->id())->get()->map(function ($monthlyVisitReport) {
                return [
                    'id' => $monthlyVisitReport->id,
                    'salesperson_id' => $monthlyVisitReport->salesperson_id,
                    'month' => $monthlyVisitReport->month,
                    'salesperson' => $monthlyVisitReport->salesperson->name,
                ];
            });
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Market Visits retrieved successfully',
            'data' => $monthlyVisitReports,
        ], 200);
    }

    /*
     * Retrieve all Visits for a specific Market Visit Report.
     *
     * @param  \App\Models\MonthlyVisitReport  $monthlyVisitReport
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyVisitReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:monthly_visit_reports,id',
        ]);

        $cacheKey = 'monthlyVisitReport_' . $validated['report_id'];
        $cacheTime = 60;

        $monthlyVisitReport = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            return MonthlyVisitReport::with(['salesperson', 'visits'])->find($validated['report_id']);
        });

        if (!$monthlyVisitReport) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Market Visit Report not found',
                'data' => null,
            ], 404);
        }

        if ($monthlyVisitReport->salesperson_id != auth()->id()) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'You are not authorized to view this Market Visit Report',
                'data' => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Market Visit Report retrieved successfully',
            'data' => monthlyVisitReport($monthlyVisitReport),
        ], 200);
    }

    /*
     * Retrieve a specific Visit for a specific Market Visit Report.
     *
     * @param  \App\Models\MonthlyVisitReport  $monthlyVisitReport
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyVisitReportVisit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:monthly_visit_reports,id',
            'visit_id' => 'required|exists:visits,id',
        ]);

        $cacheKey = 'monthlyVisitReportVisit_' . $validated['visit_id'];
        $cacheTime = 60;

        $visit = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            $monthlyVisitReport = MonthlyVisitReport::find($validated['report_id']);
            $visit = $monthlyVisitReport->visits->firstWhere('id', $validated['visit_id']);

            return $visit ? visit($visit) : null;
        });

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Visit not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Visit retrieved successfully',
            'data' => $visit,
        ], 200);
    }

    /**
     * Add a new Market Visit Report with Visits.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    // public function addMarketVisitReport(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'day_tour_plan_id' => 'required|exists:day_tour_plans,id',
    //         'visits' => 'required|array',
    //         'visits.*.customer_name' => 'required|string|max:255',
    //         'visits.*.area' => 'required|string|max:255',
    //         'visits.*.contact_person' => 'required|string|max:255',
    //         'visits.*.contact_no' => 'required|string|max:255',
    //         'visits.*.outlet_type' => 'required|string|max:255',
    //         'visits.*.shop_category' => 'required|string|max:255',
    //         'visits.*.visit_details' => 'required|string',
    //         'visits.*.findings_of_the_day' => 'required|string',
    //         'visits.*.competitors' => 'nullable|array',
    //         'visits.*.competitors.*.name' => 'nullable|string|max:255',
    //         'visits.*.competitors.*.product' => 'nullable|string|max:255',
    //         'visits.*.competitors.*.size' => 'nullable|string|max:255',
    //         'visits.*.competitors.*.details' => 'nullable|string',
    //         'visit_attachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',
    //         'competitor_attachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',
    //     ]);

    //     $createdVisits = [];

    //     $monthlyVisitReport = DB::transaction(function () use ($validated, $request, &$createdVisits) {
    //         $dayTourPlan = DayTourPlan::findOrFail($validated['day_tour_plan_id']);
    //         $monthlyTourPlan = $dayTourPlan->monthlyTourPlan;

    //         if (!$monthlyTourPlan) {
    //             abort(404, 'Monthly Tour Plan not found for the selected Day Tour Plan.');
    //         }

    //         $monthlyVisitReport = MonthlyVisitReport::firstOrCreate(
    //             ['salesperson_id' => auth()->id(), 'month' => $monthlyTourPlan->month],
    //             ['monthly_tour_plan_id' => $monthlyTourPlan->id]
    //         );

    //         foreach ($validated['visits'] as $visitIndex => $visit) {
    //             $visitAttachments = $request->file("visit_attachments.{$visitIndex}", []);
    //             $visitAttachmentPaths = [];
    //             foreach ($visitAttachments as $file) {
    //                 $visitAttachmentPaths[] = $file->store('visit-attachments', 'public');
    //             }

    //             $competitors = [];
    //             if (isset($visit['competitors'])) {
    //                 foreach ($visit['competitors'] as $competitorIndex => $competitor) {
    //                     $competitorAttachments = $request->file("competitor_attachments.{$visitIndex}.{$competitorIndex}", []);
    //                     $competitorAttachmentPaths = [];
    //                     foreach ($competitorAttachments as $file) {
    //                         $competitorAttachmentPaths[] = $file->store('competitor-attachments', 'public');
    //                     }

    //                     if (!empty($competitor['name'])) {
    //                         $competitors[] = [
    //                             'name' => $competitor['name'],
    //                             'product' => $competitor['product'] ?? null,
    //                             'size' => $competitor['size'] ?? null,
    //                             'details' => $competitor['details'] ?? null,
    //                             'attachments' => $competitorAttachmentPaths,
    //                         ];
    //                     }
    //                 }
    //             }

    //             $createdVisit = Visit::create([
    //                 'monthly_visit_report_id' => $monthlyVisitReport->id,
    //                 'day_tour_plan_id' => $dayTourPlan->id,
    //                 'customer_name' => $visit['customer_name'],
    //                 'area' => $visit['area'],
    //                 'contact_person' => $visit['contact_person'],
    //                 'contact_no' => $visit['contact_no'],
    //                 'outlet_type' => $visit['outlet_type'],
    //                 'shop_category' => $visit['shop_category'],
    //                 'visit_details' => $visit['visit_details'],
    //                 'findings_of_the_day' => $visit['findings_of_the_day'],
    //                 'competitors' => $competitors,
    //                 'attachments' => $visitAttachmentPaths,
    //                 'status' => 'pending',
    //             ]);

    //             $createdVisits[] = $createdVisit;
    //         }

    //         return $monthlyVisitReport;
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'status' => 201,
    //         'message' => 'Market Visit Report added successfully',
    //         'data' => collect($createdVisits)->map(function ($visit) {
    //             return visit($visit);
    //         }),
    //     ], 201);
    // }
    public function addMarketVisitReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'day_tour_plan_id' => 'required|exists:day_tour_plans,id',
            'visits' => 'required|array',
            'visits.*.customer_name' => 'required|string|max:255',
            'visits.*.area' => 'required|string|max:255',
            'visits.*.contact_person' => 'required|string|max:255',
            'visits.*.contact_no' => 'required|string|max:255',
            'visits.*.outlet_type' => 'required|string|max:255',
            'visits.*.shop_category' => 'required|string|max:255',
            'visits.*.visit_details' => 'required|string',
            'visits.*.findings_of_the_day' => 'required|string',
            'visits.*.competitors' => 'nullable|array',
            'visits.*.competitors.*.name' => 'nullable|string|max:255',
            'visits.*.competitors.*.product' => 'nullable|string|max:255',
            'visits.*.competitors.*.size' => 'nullable|string|max:255',
            'visits.*.competitors.*.details' => 'nullable|string',
        ]);

        $createdVisits = [];

        $monthlyVisitReport = DB::transaction(function () use ($validated, $request, &$createdVisits) {
            $dayTourPlan = DayTourPlan::findOrFail($validated['day_tour_plan_id']);
            $monthlyTourPlan = $dayTourPlan->monthlyTourPlan;

            if (!$monthlyTourPlan) {
                abort(404, 'Monthly Tour Plan not found for the selected Day Tour Plan.');
            }

            $monthlyVisitReport = MonthlyVisitReport::firstOrCreate(
                ['salesperson_id' => auth()->user()->id, 'month' => $monthlyTourPlan->month],
                ['monthly_tour_plan_id' => $monthlyTourPlan->id]
            );

            foreach ($validated['visits'] as $visitIndex => $visitData) {
                // Handle visit attachments
                $visitAttachments = $request->file("visits.{$visitIndex}.attachments", []);
                $visitAttachmentPaths = [];
                foreach ($visitAttachments as $file) {
                    $visitAttachmentPaths[] = $file->store('visit-attachments', 'public');
                }

                // Handle competitors and their attachments
                $competitors = [];
                if (isset($visitData['competitors'])) {
                    foreach ($visitData['competitors'] as $competitorIndex => $competitorData) {
                        $competitorAttachments = $request->file("visits.{$visitIndex}.competitors.{$competitorIndex}.attachments", []);
                        $competitorAttachmentPaths = [];
                        foreach ($competitorAttachments as $file) {
                            $competitorAttachmentPaths[] = $file->store('competitor-attachments', 'public');
                        }

                        $competitors[] = [
                            'name' => $competitorData['name'] ?? null,
                            'product' => $competitorData['product'] ?? null,
                            'size' => $competitorData['size'] ?? null,
                            'details' => $competitorData['details'] ?? null,
                            'attachments' => $competitorAttachmentPaths,
                        ];
                    }
                }

                // Create the visit
                $createdVisit = Visit::create([
                    'monthly_visit_report_id' => $monthlyVisitReport->id,
                    'day_tour_plan_id' => $dayTourPlan->id,
                    'customer_name' => $visitData['customer_name'],
                    'area' => $visitData['area'],
                    'contact_person' => $visitData['contact_person'],
                    'contact_no' => $visitData['contact_no'],
                    'outlet_type' => $visitData['outlet_type'],
                    'shop_category' => $visitData['shop_category'],
                    'visit_details' => $visitData['visit_details'],
                    'findings_of_the_day' => $visitData['findings_of_the_day'],
                    'competitors' => $competitors, // Stored as JSON
                    'attachments' => $visitAttachmentPaths, // Stored as JSON
                    'status' => 'pending',
                ]);

                $createdVisits[] = visit($createdVisit);
            }

            return $monthlyVisitReport;
        });

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Market Visit Report added successfully',
            'data' => $createdVisits,
        ], 201);
    }


    /**
     * Update a Market Visit Report.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function updateMarketVisitReport(Request $request): JsonResponse
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'customer_name' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_no' => 'nullable|string|max:255',
            'outlet_type' => 'nullable|string|max:255',
            'shop_category' => 'nullable|string|max:255',
            'visit_details' => 'nullable|string',
            'findings_of_the_day' => 'nullable|string',
            'competitors' => 'nullable|array',
            'competitors.*.name' => 'nullable|string|max:255',
            'competitors.*.product' => 'nullable|string|max:255',
            'competitors.*.size' => 'nullable|string|max:255',
            'competitors.*.details' => 'nullable|string',
            'competitor_attachments.*.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',
            'visit_attachments.*' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240',
        ]);

        try {
            // Find the visit record
            $visit = Visit::findOrFail($validated['visit_id']);

            // Handle visit attachments
            $existingVisitAttachments = $visit->attachments ?? [];
            if ($request->hasFile('visit_attachments')) {
                foreach ($request->file('visit_attachments') as $file) {
                    $existingVisitAttachments[] = $file->store('visit-attachments', 'public');
                }
            }

            if ($request->hasFile('visit_attachments')) {
                foreach ($request->file('visit_attachments') as $file) {
                    Log::info('Uploading file: ' . $file->getClientOriginalName());
                }
            } else {
                Log::info('No visit attachments found in the request.');
            }

            // Handle competitors and their attachments
            $updatedCompetitors = [];
            if (isset($validated['competitors'])) {
                foreach ($validated['competitors'] as $competitorIndex => $competitor) {
                    $competitorAttachments = $request->file("competitor_attachments.{$competitorIndex}", []);
                    $existingCompetitorAttachments = $competitor['attachments'] ?? [];

                    foreach ($competitorAttachments as $file) {
                        $existingCompetitorAttachments[] = $file->store('competitor-attachments', 'public');
                    }

                    if (!empty($competitor['name'])) {
                        $updatedCompetitors[] = [
                            'name' => $competitor['name'],
                            'product' => $competitor['product'] ?? null,
                            'size' => $competitor['size'] ?? null,
                            'details' => $competitor['details'] ?? null,
                            'attachments' => $existingCompetitorAttachments,
                        ];
                    }
                }
            }

            // Update visit details
            $visit->update([
                'customer_name' => $validated['customer_name'] ?? $visit->customer_name,
                'area' => $validated['area'] ?? $visit->area,
                'contact_person' => $validated['contact_person'] ?? $visit->contact_person,
                'contact_no' => $validated['contact_no'] ?? $visit->contact_no,
                'outlet_type' => $validated['outlet_type'] ?? $visit->outlet_type,
                'shop_category' => $validated['shop_category'] ?? $visit->shop_category,
                'visit_details' => $validated['visit_details'] ?? $visit->visit_details,
                'findings_of_the_day' => $validated['findings_of_the_day'] ?? $visit->findings_of_the_day,
                'attachments' => $existingVisitAttachments,
                'competitors' => $updatedCompetitors,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Visit updated successfully',
                'data' => visit($visit),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update visit',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /*
     * Retrieve all Expenses for a specific Visit.
     *
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Http\JsonResponse
     */
    public function visitExpenses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visit_id' => 'required|exists:visits,id',
        ]);

        $cacheKey = 'visitExpenses_' . $validated['visit_id'];
        $cacheTime = 60;

        $visit = Visit::find($validated['visit_id']);

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Visit not found',
                'data' => null,
            ], 404);
        }

        $visitExpenses = Cache::remember($cacheKey, $cacheTime, function () use ($visit) {
            return $visit->expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'visit_id' => $expense->visit_id,
                    'expense_type' => ucwords(str_replace('_', ' ', $expense->expense_type)),
                    'expense_details' => $expense->expense_details,
                    'total' => $expense->total,
                    'status' => ucwords($expense->status),
                    'attachments' => collect($expense->attachments)->map(function ($filePath) {
                        return [
                            'file_name' => basename($filePath), // Extract file name
                            'file_url' => asset('storage/' . $filePath), // Generate full URL
                        ];
                    }),
                ];
            });
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Visit Expenses retrieved successfully',
            'data' => $visitExpenses,
        ], 200);
    }

    /*
     * Retrieve a specific Expense for a specific Visit.
     *
     * @param  \App\Models\Visit  $visit
     * @param  \App\Models\VisitExpense  $expense
     * @return \Illuminate\Http\JsonResponse
     */
    public function visitExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'expense_id' => 'required|exists:visit_expenses,id',
        ]);

        $cacheKey = 'visitExpense_' . $validated['expense_id'];
        $cacheTime = 60;

        $expense = Cache::remember($cacheKey, $cacheTime, function () use ($validated) {
            $visitExpense = Visit::find($validated['visit_id'])->expenses()->find($validated['expense_id']);

            if ($visitExpense) {
                return [
                    'id' => $visitExpense->id,
                    'visit_id' => $visitExpense->visit_id,
                    'expense_type' => ucwords(str_replace('_', ' ', $visitExpense->expense_type)),
                    'expense_details' => $visitExpense->expense_details,
                    'total' => $visitExpense->total,
                    'status' => ucwords($visitExpense->status),
                    'attachments' => collect($visitExpense->attachments)->map(function ($filePath) {
                        return [
                            'file_name' => basename($filePath),
                            'file_url' => asset('storage/' . $filePath),
                        ];
                    }),
                ];
            }

            return null; // Return null if the expense is not found
        });

        if (!$expense) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Expense not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Expense retrieved successfully',
            'data' => $expense,
        ], 200);
    }

    /**
     * Add a new expense to a visit.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function addVisitExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'expenses' => 'required|array',
            'expenses.*.expense_type' => 'required|string',
            'expenses.*.expense_details' => 'required|array',
            'expenses.*.expense_details.*.date' => 'required|date_format:d/m/Y',
            'expenses.*.expense_details.*.description' => 'required|string|max:255',
            'expenses.*.expense_details.*.amount' => 'required|numeric|min:0',
            'expenses.*.attachments' => 'nullable|array',
            'expenses.*.attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240', // Max 10MB
        ]);

        try {
            $visitId = $validated['visit_id'];
            $expenses = $validated['expenses'];

            $savedExpenses = [];

            foreach ($expenses as $expense) {
                // Calculate the total for the expense
                $total = array_reduce($expense['expense_details'], function ($carry, $item) {
                    return $carry + ($item['amount'] ?? 0);
                }, 0);

                // Handle Expense Attachments
                $attachmentPaths = [];
                if (!empty($expense['attachments'])) {
                    foreach ($expense['attachments'] as $file) {
                        $attachmentPaths[] = $file->store('expense-attachments', 'public');
                    }
                }

                // Save the expense
                $savedExpense = VisitExpense::create([
                    'visit_id' => $visitId,
                    'expense_type' => $expense['expense_type'],
                    'expense_details' => $expense['expense_details'],
                    'total' => $total,
                    'status' => 'pending',
                    'attachments' => $attachmentPaths,
                ]);

                $savedExpenses[] = $savedExpense;
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Expenses added successfully.',
                'data' => $savedExpenses,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to add expenses.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an expense for a visit.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function updateVisitExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_id' => 'required|exists:visit_expenses,id',
            'visit_id' => 'required|exists:visits,id',
            'expense_type' => 'required|string',
            'expense_details' => 'required|array',
            'expense_details.*.date' => 'required|date_format:d/m/Y',
            'expense_details.*.description' => 'required|string|max:255',
            'expense_details.*.amount' => 'required|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,bmp,pdf,doc,docx|max:10240', // Max 10MB
        ]);

        try {
            $expense = VisitExpense::findOrFail($validated['expense_id']);

            // Handle new attachments
            $existingAttachments = $expense->attachments ?? [];
            $newAttachments = $request->file('attachments', []);
            $uploadedAttachments = [];

            foreach ($newAttachments as $file) {
                $uploadedAttachments[] = $file->store('expense-attachments', 'public');
            }

            // Merge existing and new attachments
            $allAttachments = array_merge($existingAttachments, $uploadedAttachments);

            // Calculate the total for the updated expense details
            $total = array_sum(array_column($validated['expense_details'], 'amount'));

            // Update the expense record
            $expense->update([
                'visit_id' => $validated['visit_id'],
                'expense_type' => $validated['expense_type'],
                'expense_details' => $validated['expense_details'],
                'total' => $total,
                'attachments' => $allAttachments,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense updated successfully.',
                'data' => $expense,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
