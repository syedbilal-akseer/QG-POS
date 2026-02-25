<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocalBank;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LocalBankController extends Controller
{
    /**
     * Get all local banks
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = LocalBank::query()->orderBy('name');

            // Filter by bank type
            if ($request->filled('type')) {
                switch ($request->type) {
                    case 'islamic':
                        $query->islamic();
                        break;
                    case 'conventional':
                        $query->conventional();
                        break;
                    case 'microfinance':
                        $query->microfinance();
                        break;
                }
            }

            // Search by name
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filter by is_islamic
            if ($request->filled('is_islamic')) {
                $query->where('is_islamic', $request->boolean('is_islamic'));
            }

            // Filter by is_microfinance
            if ($request->filled('is_microfinance')) {
                $query->where('is_microfinance', $request->boolean('is_microfinance'));
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $banks = $query->paginate($perPage);

            // Transform data for API response
            $banksData = $banks->getCollection()->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'is_islamic' => $bank->is_islamic,
                    'is_microfinance' => $bank->is_microfinance,
                    'created_at' => $bank->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $bank->updated_at?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Local banks retrieved successfully',
                'data' => $banksData,
                'pagination' => [
                    'current_page' => $banks->currentPage(),
                    'last_page' => $banks->lastPage(),
                    'per_page' => $banks->perPage(),
                    'total' => $banks->total(),
                    'from' => $banks->firstItem(),
                    'to' => $banks->lastItem(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to fetch local banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch local banks from database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Search local banks by name
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $searchTerm = $request->get('search');
            $perPage = $request->get('per_page', 20);

            $banks = LocalBank::search($searchTerm)
                ->orderBy('name')
                ->paginate($perPage);

            // Transform data for API response
            $banksData = $banks->getCollection()->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'is_islamic' => $bank->is_islamic,
                    'is_microfinance' => $bank->is_microfinance,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Search results retrieved successfully',
                'data' => $banksData,
                'search_term' => $searchTerm,
                'pagination' => [
                    'current_page' => $banks->currentPage(),
                    'last_page' => $banks->lastPage(),
                    'per_page' => $banks->perPage(),
                    'total' => $banks->total(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to search local banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to search local banks in database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get local banks formatted for select dropdown
     */
    public function select(Request $request): JsonResponse
    {
        try {
            $query = LocalBank::orderBy('name');

            // Filter by type if provided
            if ($request->filled('type')) {
                switch ($request->type) {
                    case 'islamic':
                        $query->islamic();
                        break;
                    case 'conventional':
                        $query->conventional();
                        break;
                    case 'microfinance':
                        $query->microfinance();
                        break;
                }
            }

            $banks = $query->get();

            $selectOptions = $banks->map(function ($bank) {
                $label = $bank->name;
                
                // Add type indicators to label
                $types = [];
                if ($bank->is_islamic) $types[] = 'Islamic';
                if ($bank->is_microfinance) $types[] = 'Microfinance';
                
                if (!empty($types)) {
                    $label .= ' (' . implode(', ', $types) . ')';
                }

                return [
                    'value' => $bank->id,
                    'label' => $label,
                    'name' => $bank->name,
                    'is_islamic' => $bank->is_islamic,
                    'is_microfinance' => $bank->is_microfinance,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Local banks for select dropdown retrieved successfully',
                'data' => $selectOptions,
                'total_count' => $selectOptions->count(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get local banks for select: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch local banks for dropdown',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a specific local bank by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $bank = LocalBank::findOrFail($id);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Local bank retrieved successfully',
                'data' => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'is_islamic' => $bank->is_islamic,
                    'is_microfinance' => $bank->is_microfinance,
                    'created_at' => $bank->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $bank->updated_at?->format('Y-m-d H:i:s'),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Local bank not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get local bank: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch local bank from database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get local banks statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_banks' => LocalBank::count(),
                'islamic_banks' => LocalBank::islamic()->count(),
                'conventional_banks' => LocalBank::conventional()->count(),
                'microfinance_banks' => LocalBank::microfinance()->count(),
                'regular_banks' => LocalBank::where('is_microfinance', false)->count(),
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Local banks statistics retrieved successfully',
                'data' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get local banks stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch local banks statistics',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}