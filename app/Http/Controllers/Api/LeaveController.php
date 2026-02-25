<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * Mark leave (single day or date range).
     */
    public function markLeave(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'leave_type' => 'required|in:casual,sick,annual,emergency,other',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $validated = $validator->validated();

        // Set end_date to start_date if not provided (single day leave)
        if (!isset($validated['end_date']) || empty($validated['end_date'])) {
            $validated['end_date'] = $validated['start_date'];
        }

        // Business logic: Prevent leaves too far in the past (more than 30 days ago)
        $startDate = Carbon::parse($validated['start_date']);
        $maxPastDays = 30; // Allow up to 30 days in the past
        
        if ($startDate->lt(Carbon::now()->subDays($maxPastDays))) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => "Leave cannot be marked more than {$maxPastDays} days in the past.",
            ], 400);
        }

        try {
            $endDate = Carbon::parse($validated['end_date']);

            // Calculate total days
            $totalDays = $startDate->diffInDays($endDate) + 1;

            // Check for overlapping leaves
            $hasOverlap = Leave::where('user_id', $user->id)
                ->where('status', '!=', 'rejected')
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
                })
                ->exists();

            if ($hasOverlap) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'You already have a leave request for the selected date range. Please choose different dates.',
                ], 400);
            }

            // Create leave record
            $leave = Leave::create([
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'lat' => $validated['lat'],
                'lng' => $validated['lng'],
                'leave_type' => $validated['leave_type'],
                'reason' => $validated['reason'] ?? null,
                'status' => 'pending', // Default status
            ]);

            // Load relationships
            $leave->load(['user:id,name,email']);

            Log::info('Leave marked successfully', [
                'user_id' => $user->id,
                'leave_id' => $leave->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_days' => $totalDays,
                'leave_type' => $validated['leave_type'],
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => $totalDays == 1 
                    ? 'Single day leave marked successfully and is pending approval'
                    : "{$totalDays} days leave marked successfully and is pending approval",
                'data' => $leave,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to mark leave', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to mark leave: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all leaves or filter by user_id, status, etc.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,approved,rejected',
            'leave_type' => 'nullable|in:casual,sick,annual,emergency,other',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $query = Leave::with(['user:id,name,email', 'approvedBy:id,name,email']);

        // Apply filters
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('leave_type')) {
            $query->byType($request->leave_type);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        } elseif ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        } elseif ($request->has('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }

        // Set per page limit (default 20, max 100)
        $perPage = min($request->get('per_page', 20), 100);
        
        $leaves = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Leaves retrieved successfully',
            'data' => $leaves->items(),
            'pagination' => [
                'total' => $leaves->total(),
                'count' => $leaves->count(),
                'per_page' => $leaves->perPage(),
                'current_page' => $leaves->currentPage(),
                'total_pages' => $leaves->lastPage(),
                'next_page_url' => $leaves->nextPageUrl(),
                'prev_page_url' => $leaves->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Get a single leave record.
     */
    public function show($id): JsonResponse
    {
        $leave = Leave::with(['user:id,name,email', 'approvedBy:id,name,email'])->find($id);

        if (!$leave) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Leave record not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Leave record retrieved successfully',
            'data' => $leave,
        ], 200);
    }

    /**
     * Update a leave record (only pending leaves can be updated).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Leave record not found',
            ], 404);
        }

        if (!$leave->canBeEdited()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Only pending leaves can be updated',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'lat' => 'sometimes|required|numeric|between:-90,90',
            'lng' => 'sometimes|required|numeric|between:-180,180',
            'leave_type' => 'sometimes|required|in:casual,sick,annual,emergency,other',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $validated = $validator->validated();
            
            // If dates are being updated, check for overlaps
            if (isset($validated['start_date']) || isset($validated['end_date'])) {
                $startDate = isset($validated['start_date']) 
                    ? Carbon::parse($validated['start_date']) 
                    : $leave->start_date;
                    
                $endDate = isset($validated['end_date']) 
                    ? Carbon::parse($validated['end_date']) 
                    : (isset($validated['start_date']) ? $startDate : $leave->end_date);

                // Check for overlapping leaves (excluding current leave)
                $hasOverlap = $leave->hasOverlap($startDate, $endDate, $leave->id);

                if ($hasOverlap) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'The updated date range overlaps with another leave request',
                    ], 400);
                }
            }

            $leave->update($validated);
            $leave->load(['user:id,name,email', 'approvedBy:id,name,email']);

            Log::info('Leave updated successfully', [
                'leave_id' => $leave->id,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Leave record updated successfully',
                'data' => $leave,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update leave', [
                'leave_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update leave record: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a leave record (cancel leave).
     */
    public function destroy($id): JsonResponse
    {
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Leave record not found',
            ], 404);
        }

        if (!$leave->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'This leave cannot be cancelled. Only future pending or approved leaves can be cancelled.',
            ], 400);
        }

        try {
            $leaveData = $leave->toArray();
            $leave->delete();

            Log::info('Leave cancelled successfully', [
                'leave_id' => $id,
                'cancelled_by' => Auth::id(),
                'leave_data' => $leaveData,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Leave cancelled successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to cancel leave', [
                'leave_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to cancel leave: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve or reject a leave (admin function).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $leave = Leave::find($id);

        if (!$leave) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Leave record not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $validated = $validator->validated();
            
            $leave->update([
                'status' => $validated['status'],
                'rejection_reason' => $validated['status'] === 'rejected' 
                    ? $validated['rejection_reason'] 
                    : null,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $leave->load(['user:id,name,email', 'approvedBy:id,name,email']);

            $message = $validated['status'] === 'approved' 
                ? 'Leave approved successfully' 
                : 'Leave rejected successfully';

            Log::info('Leave status updated', [
                'leave_id' => $leave->id,
                'new_status' => $validated['status'],
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => $message,
                'data' => $leave,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update leave status', [
                'leave_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update leave status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get leave summary/statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'year' => 'nullable|integer|min:2020|max:2050',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $query = Leave::query();

        // Apply filters
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->has('year')) {
            $year = $request->year;
            $query->whereYear('start_date', $year);
        } elseif ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        }

        $summary = [
            'total_leaves' => (clone $query)->count(),
            'pending_leaves' => (clone $query)->where('status', 'pending')->count(),
            'approved_leaves' => (clone $query)->where('status', 'approved')->count(),
            'rejected_leaves' => (clone $query)->where('status', 'rejected')->count(),
            'total_days_requested' => (clone $query)->sum('total_days'),
            'approved_days' => (clone $query)->where('status', 'approved')->sum('total_days'),
            'by_leave_type' => [
                'casual' => (clone $query)->where('leave_type', 'casual')->count(),
                'sick' => (clone $query)->where('leave_type', 'sick')->count(),
                'annual' => (clone $query)->where('leave_type', 'annual')->count(),
                'emergency' => (clone $query)->where('leave_type', 'emergency')->count(),
                'other' => (clone $query)->where('leave_type', 'other')->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Leave summary retrieved successfully',
            'data' => $summary,
        ], 200);
    }
}