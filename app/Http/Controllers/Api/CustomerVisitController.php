<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisit;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CustomerVisitController extends Controller
{
    /**
     * Mark a complete customer visit in a single API call.
     */
    public function visitMark(Request $request): JsonResponse
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
            'customer_id' => 'required|exists:customers,customer_id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'visit_start_time' => 'required|date',
            'visit_end_time' => 'nullable|date',
            'comments' => 'nullable|string|max:2000',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per image
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

        try {
            $images = [];
            
            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    if ($image && $image->isValid()) {
                        $imagePath = $image->store('customer-visit-images', 'public');
                        $images[] = asset('storage/' . $imagePath);
                    }
                }
            }

            // Create completed visit with all data
            $visit = CustomerVisit::create([
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'visit_start_time' => $validated['visit_start_time'],
                'visit_end_time' => $validated['visit_end_time'] ?? null,
                'comments' => $validated['comments'] ?? null,
                'images' => $images,
                'status' => 'completed',
            ]);

            // Load relationships
            $visit->load(['user:id,name,email', 'customer:customer_id,customer_name']);

            Log::info('Customer visit marked successfully', [
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'],
                'visit_id' => $visit->id,
                'duration' => $visit->duration,
                'images_uploaded' => count($images),
            ]);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Customer visit marked successfully',
                'data' => $visit,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to mark customer visit', [
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to mark customer visit: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Start a new customer visit.
     */
    public function startVisit(Request $request): JsonResponse
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
            'customer_id' => 'required|exists:customers,customer_id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'comments' => 'nullable|string|max:2000',
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

        // Check if user already has an ongoing visit
        $ongoingVisit = CustomerVisit::where('user_id', $user->id)
            ->where('status', 'ongoing')
            ->first();

        if ($ongoingVisit) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'You already have an ongoing visit. Please complete it first.',
                'data' => [
                    'ongoing_visit' => $ongoingVisit->load(['customer:customer_id,customer_name'])
                ]
            ], 400);
        }

        try {
            $visit = CustomerVisit::create([
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'visit_start_time' => now(),
                'comments' => $validated['comments'] ?? null,
                'status' => 'ongoing',
            ]);

            // Load relationships
            $visit->load(['user:id,name,email', 'customer:customer_id,customer_name']);

            Log::info('Customer visit started successfully', [
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'],
                'visit_id' => $visit->id,
            ]);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Customer visit started successfully',
                'data' => $visit,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to start customer visit', [
                'user_id' => $user->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to start customer visit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End a customer visit.
     */
    public function endVisit(Request $request, $visitId): JsonResponse
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'comments' => 'nullable|string|max:2000',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per image
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $visit = CustomerVisit::where('id', $visitId)
            ->where('user_id', $user->id)
            ->first();

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Visit not found or you do not have permission to end this visit',
            ], 404);
        }

        if (!$visit->isOngoing()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Visit is not ongoing',
            ], 400);
        }

        try {
            $images = [];
            
            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    if ($image && $image->isValid()) {
                        $imagePath = $image->store('customer-visit-images', 'public');
                        $images[] = asset('storage/' . $imagePath);
                    }
                }
            }

            // Merge existing images with new ones
            $existingImages = $visit->images ?? [];
            $allImages = array_merge($existingImages, $images);

            $visit->update([
                'visit_end_time' => now(),
                'status' => 'completed',
                'comments' => $request->comments ?? $visit->comments,
                'images' => $allImages,
            ]);

            $visit->load(['user:id,name,email', 'customer:customer_id,customer_name']);

            Log::info('Customer visit ended successfully', [
                'user_id' => $user->id,
                'visit_id' => $visit->id,
                'duration' => $visit->duration,
                'images_uploaded' => count($images),
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Customer visit ended successfully',
                'data' => $visit,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to end customer visit', [
                'user_id' => $user->id,
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to end customer visit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all customer visits with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'customer_id' => 'nullable|exists:customers,customer_id',
            'status' => 'nullable|in:ongoing,completed,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'khi' => 'nullable|boolean', // Filter by Karachi users
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $currentUser = Auth::user();

        // Check if user has permission to view all records
        $allowedEmails = ['nauman_ahmad@quadri-group.com'];
        $canViewAll = $currentUser->role === 'admin' || in_array($currentUser->email, $allowedEmails);

        // Check if khi parameter is requested
        if ($request->has('khi')) {
            // Only allow admin and nauman_ahmad@quadri-group.com to use khi filter
            if (!$canViewAll) {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'You are not authorized to use the khi filter',
                ], 403);
            }
        }

        $query = CustomerVisit::with(['user:id,name,email', 'customer:customer_id,customer_name']);

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

        // If not admin/allowed user and no specific user_id filter, show only own records
        if (!$canViewAll && !$request->has('user_id')) {
            $query->where('user_id', $currentUser->id);
        }

        // Apply filters
        if ($request->has('user_id')) {
            // Only admin/allowed users can filter by other user_id
            if (!$canViewAll && $request->user_id != $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'You can only view your own customer visits',
                ], 403);
            }
            $query->byUser($request->user_id);
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to . ' 23:59:59');
        } elseif ($request->has('date_from')) {
            $query->whereDate('visit_start_time', '>=', $request->date_from);
        } elseif ($request->has('date_to')) {
            $query->whereDate('visit_start_time', '<=', $request->date_to);
        }

        // Set per page limit (default 20, max 100)
        $perPage = min($request->get('per_page', 20), 100);

        // Order by today first, then descending
        $today = now()->toDateString();
        $visits = $query
            ->orderByRaw("CASE WHEN DATE(visit_start_time) = ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('visit_start_time', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer visits retrieved successfully',
            'data' => $visits->items(),
            'pagination' => [
                'total' => $visits->total(),
                'count' => $visits->count(),
                'per_page' => $visits->perPage(),
                'current_page' => $visits->currentPage(),
                'total_pages' => $visits->lastPage(),
                'next_page_url' => $visits->nextPageUrl(),
                'prev_page_url' => $visits->previousPageUrl(),
            ],
        ], 200);
    }

    /**
     * Get a specific customer visit.
     */
    public function show($visitId): JsonResponse
    {
        $visit = CustomerVisit::with(['user:id,name,email', 'customer:customer_id,customer_name'])->find($visitId);

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Customer visit not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer visit retrieved successfully',
            'data' => $visit,
        ], 200);
    }

    /**
     * Update a customer visit (comments and images only for ongoing visits).
     */
    public function update(Request $request, $visitId): JsonResponse
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'comments' => 'nullable|string|max:2000',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per image
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $visit = CustomerVisit::where('id', $visitId)
            ->where('user_id', $user->id)
            ->first();

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Visit not found or you do not have permission to update this visit',
            ], 404);
        }

        try {
            $updateData = [];
            
            if ($request->has('comments')) {
                $updateData['comments'] = $request->comments;
            }

            // Handle image uploads
            if ($request->hasFile('images')) {
                $images = $visit->images ?? [];
                
                foreach ($request->file('images') as $image) {
                    if ($image && $image->isValid()) {
                        $imagePath = $image->store('customer-visit-images', 'public');
                        $images[] = asset('storage/' . $imagePath);
                    }
                }
                
                $updateData['images'] = $images;
            }

            $visit->update($updateData);
            $visit->load(['user:id,name,email', 'customer:customer_id,customer_name']);

            Log::info('Customer visit updated successfully', [
                'user_id' => $user->id,
                'visit_id' => $visit->id,
                'updated_fields' => array_keys($updateData),
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Customer visit updated successfully',
                'data' => $visit,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update customer visit', [
                'user_id' => $user->id,
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update customer visit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a customer visit.
     */
    public function cancel(Request $request, $visitId): JsonResponse
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $visit = CustomerVisit::where('id', $visitId)
            ->where('user_id', $user->id)
            ->first();

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Visit not found or you do not have permission to cancel this visit',
            ], 404);
        }

        if (!$visit->isOngoing()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Only ongoing visits can be cancelled',
            ], 400);
        }

        try {
            $visit->cancelVisit($request->reason);
            $visit->load(['user:id,name,email', 'customer:customer_id,customer_name']);

            Log::info('Customer visit cancelled successfully', [
                'user_id' => $user->id,
                'visit_id' => $visit->id,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Customer visit cancelled successfully',
                'data' => $visit,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to cancel customer visit', [
                'user_id' => $user->id,
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to cancel customer visit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's current ongoing visit.
     */
    public function getCurrentVisit(): JsonResponse
    {
        $user = Auth::user();
        
        $visit = CustomerVisit::with(['user:id,name,email', 'customer:customer_id,customer_name'])
            ->where('user_id', $user->id)
            ->where('status', 'ongoing')
            ->first();

        if (!$visit) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No ongoing visit found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Current ongoing visit retrieved successfully',
            'data' => $visit,
        ], 200);
    }

    /**
     * Get list of salespersons for customer visits filtering.
     */
    public function getSalespersons(): JsonResponse
    {
        $currentUser = Auth::user();

        // Check if user has permission to view all salespersons
        $allowedEmails = ['nauman_ahmad@quadri-group.com'];
        $canViewAll = $currentUser->role === 'admin' || in_array($currentUser->email, $allowedEmails);

        if (!$canViewAll) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'You are not authorized to view salespersons list',
            ], 403);
        }

        try {
            // Get all users who have created customer visits (salespersons)
            $salespersons = \App\Models\User::whereHas('customerVisits')
                ->select('id', 'name', 'email', 'role')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Salespersons list retrieved successfully',
                'data' => $salespersons,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve salespersons list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve salespersons list: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer visit statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'customer_id' => 'nullable|exists:customers,customer_id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $query = CustomerVisit::query();

        // Apply filters
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to . ' 23:59:59');
        } elseif ($request->has('date_from')) {
            $query->whereDate('visit_start_time', '>=', $request->date_from);
        } elseif ($request->has('date_to')) {
            $query->whereDate('visit_start_time', '<=', $request->date_to);
        }

        $statistics = [
            'total_visits' => (clone $query)->count(),
            'ongoing_visits' => (clone $query)->where('status', 'ongoing')->count(),
            'completed_visits' => (clone $query)->where('status', 'completed')->count(),
            'cancelled_visits' => (clone $query)->where('status', 'cancelled')->count(),
            'unique_customers_visited' => (clone $query)->distinct('customer_id')->count('customer_id'),
            'unique_users' => (clone $query)->distinct('user_id')->count('user_id'),
            'total_visits_today' => (clone $query)->whereDate('visit_start_time', today())->count(),
            'average_visit_duration' => (clone $query)
                ->whereNotNull('visit_end_time')
                ->get()
                ->avg(function($visit) {
                    return $visit->visit_start_time->diffInMinutes($visit->visit_end_time);
                }),
        ];

        // Round average duration to 2 decimal places
        $statistics['average_visit_duration'] = $statistics['average_visit_duration'] 
            ? round($statistics['average_visit_duration'], 2) 
            : 0;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Customer visit statistics retrieved successfully',
            'data' => $statistics,
        ], 200);
    }
}