<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Mark attendance (both check-in and check-out in single API).
     */
    public function markAttendance(Request $request): JsonResponse
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
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $currentTime = now();

            // Auto-detect if this should be check-in or check-out
            // Check if user already has an active check-in today
            $existingCheckIn = Attendance::where('user_id', $user->id)
                ->where('type', 'check_in')
                ->whereDate('created_at', $currentTime->toDateString())
                ->whereNull('check_out_time')
                ->first();

            if (!$existingCheckIn) {
                // No active check-in found, so this is a CHECK-IN
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'lat' => $validated['lat'],
                    'lng' => $validated['lng'],
                    'check_in_time' => $currentTime,
                    'type' => 'check_in',
                    'notes' => $validated['notes'] ?? null,
                ]);

                $message = 'Check-in marked successfully';
                $attendanceType = 'check_in';
            } else {
                // Active check-in found, so this is a CHECK-OUT
                // Update the check-in record with check-out time
                $existingCheckIn->update([
                    'check_out_time' => $currentTime,
                ]);

                // Create a separate check-out record for tracking
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'lat' => $validated['lat'],
                    'lng' => $validated['lng'],
                    'check_in_time' => $existingCheckIn->check_in_time,
                    'check_out_time' => $currentTime,
                    'type' => 'check_out',
                    'notes' => $validated['notes'] ?? null,
                ]);

                $message = 'Check-out marked successfully';
                $attendanceType = 'check_out';
            }

            // Load relationships
            $attendance->load(['user:id,name,email']);

            Log::info('Attendance marked successfully', [
                'user_id' => $user->id,
                'type' => $attendanceType,
                'attendance_id' => $attendance->id,
                'auto_detected' => true,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => $message,
                'data' => $attendance,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to mark attendance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to mark attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all attendances or filter by user_id.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'type' => 'nullable|in:check_in,check_out',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'khi' => 'nullable|boolean', // Filter by Karachi users
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentUser = Auth::user();

        // Check if khi parameter is requested
        if ($request->has('khi')) {
            // Only allow admin and nauman_ahmad@quadri-group.com to use khi filter
            $allowedEmails = ['nauman_ahmad@quadri-group.com'];
            $isAllowed = $currentUser->role === 'admin' || in_array($currentUser->email, $allowedEmails);

            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'You are not authorized to use the khi filter',
                ], 403);
            }
        }

        $query = Attendance::with(['user:id,name,email']);

        // Apply KHI filter if requested
        if ($request->has('khi') && $request->boolean('khi')) {
            // Get all KHI users
            $khiUserIds = \App\Models\User::all()->filter(function ($user) {
                return $user->isKHIUser();
            })->pluck('id')->toArray();

            if (!empty($khiUserIds)) {
                $query->whereIn('user_id', $khiUserIds);
            } else {
                // No KHI users found, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        // Apply filters
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to . ' 23:59:59');
        } elseif ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        } elseif ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Set per page limit (default 20, max 100)
        $perPage = min($request->get('per_page', 20), 100);

        // Order by today first, then descending
        $today = now()->toDateString();
        $attendances = $query
            ->orderByRaw("CASE WHEN DATE(created_at) = ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Attendances retrieved successfully',
            'data' => $attendances->items(),
            'pagination' => [
                'total' => $attendances->total(),
                'count' => $attendances->count(),
                'per_page' => $attendances->perPage(),
                'current_page' => $attendances->currentPage(),
                'total_pages' => $attendances->lastPage(),
                'next_page_url' => $attendances->nextPageUrl(),
                'prev_page_url' => $attendances->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Get a single attendance record.
     */
    public function show($id): JsonResponse
    {
        $attendance = Attendance::with(['user:id,name,email'])->find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Attendance record not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Attendance record retrieved successfully',
            'data' => $attendance,
        ], 200);
    }

    /**
     * Update an attendance record.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Attendance record not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'sometimes|required|numeric|between:-90,90',
            'lng' => 'sometimes|required|numeric|between:-180,180',
            'check_in_time' => 'sometimes|nullable|date',
            'check_out_time' => 'sometimes|nullable|date|after:check_in_time',
            'type' => 'sometimes|required|in:check_in,check_out',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $attendance->update($validator->validated());
            $attendance->load(['user:id,name,email']);

            Log::info('Attendance updated successfully', [
                'attendance_id' => $attendance->id,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Attendance record updated successfully',
                'data' => $attendance,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update attendance', [
                'attendance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update attendance record: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an attendance record.
     */
    public function destroy($id): JsonResponse
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Attendance record not found',
            ], 404);
        }

        try {
            $attendanceData = $attendance->toArray();
            $attendance->delete();

            Log::info('Attendance deleted successfully', [
                'attendance_id' => $id,
                'deleted_by' => Auth::id(),
                'attendance_data' => $attendanceData,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Attendance record deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to delete attendance', [
                'attendance_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete attendance record: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance summary for a user or customer.
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Attendance::query();

        // Apply filters
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }


        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to . ' 23:59:59');
        } elseif ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        } elseif ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $summary = [
            'total_check_ins' => (clone $query)->where('type', 'check_in')->count(),
            'total_check_outs' => (clone $query)->where('type', 'check_out')->count(),
            'unique_users' => (clone $query)->distinct('user_id')->count('user_id'),
            'total_records' => $query->count(),
        ];

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Attendance summary retrieved successfully',
            'data' => $summary,
        ], 200);
    }

    /**
     * Get authenticated user's attendance for today.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Unauthorized',
            ], 401);
        }

        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->with(['user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'You did not mark today\'s attendance',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Attendance retrieved successfully',
            'data' => $attendance,
        ], 200);
    }
}