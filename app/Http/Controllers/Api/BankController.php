<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OracleBankMaster;
use App\Models\Bank;
use App\Services\BankService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BankController extends Controller
{
    protected $bankService;

    public function __construct(BankService $bankService)
    {
        $this->bankService = $bankService;
    }

    /**
     * Get all banks from SQL database
     */
    public function getBanks(Request $request): JsonResponse
    {
        try {
            $query = Bank::query()->orderBy('bank_name');

            // Filter by organization unit if provided
            if ($request->filled('org_id')) {
                $query->where('org_id', $request->org_id);
            }

            // Filter by currency if provided
            if ($request->filled('currency')) {
                $query->byCurrency($request->currency);
            }

            // Filter by status if provided
            if ($request->filled('status')) {
                if ($request->status === 'active') {
                    $query->active();
                } else {
                    $query->where('status', $request->status);
                }
            } else {
                // Default to active banks only
                $query->active();
            }

            // Search functionality
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $banks = $query->paginate($perPage);

            // Transform data for API response
            $banksData = $banks->getCollection()->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'bank_account_id' => $bank->bank_account_id,
                    'bank_name' => $bank->bank_name,
                    'bank_account_name' => $bank->bank_account_name,
                    'bank_account_num' => $bank->bank_account_num,
                    'bank_branch_name' => $bank->bank_branch_name,
                    'account_type' => $bank->account_type,
                    'currency' => $bank->currency,
                    'country' => $bank->country,
                    'iban_number' => $bank->iban_number,
                    'org_id' => $bank->org_id,
                    'receipt_method_id' => $bank->receipt_method_id,
                    'receipt_class_id' => $bank->receipt_class_id,
                    'receipt_classes' => $bank->receipt_classes,
                    'status' => $bank->status,
                    'display_name' => $bank->display_name,
                    'created_at' => $bank->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $bank->updated_at?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Banks retrieved successfully',
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
            Log::error('Failed to fetch banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch banks from database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Search banks in SQL database
     */
    public function searchBanks(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $searchTerm = $request->get('search');
            $perPage = $request->get('per_page', 20);

            $banks = Bank::active()
                ->search($searchTerm)
                ->orderBy('bank_name')
                ->paginate($perPage);

            // Transform data for API response
            $banksData = $banks->getCollection()->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'bank_account_id' => $bank->bank_account_id,
                    'bank_name' => $bank->bank_name,
                    'bank_account_name' => $bank->bank_account_name,
                    'bank_account_num' => $bank->bank_account_num,
                    'bank_branch_name' => $bank->bank_branch_name,
                    'currency' => $bank->currency,
                    'display_name' => $bank->display_name,
                    'org_id' => $bank->org_id,
                    'status' => $bank->status,
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
            Log::error('Failed to search banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to search banks in database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get banks formatted for select dropdown from SQL database
     */
    public function getBanksForSelectDropdown(Request $request): JsonResponse
    {
        try {
            $orgId = $request->get('org_id');
            $currency = $request->get('currency');

            $query = Bank::active()->orderBy('bank_name');

            if ($orgId) {
                $query->where('org_id', $orgId);
            }

            if ($currency) {
                $query->byCurrency($currency);
            }

            $banks = $query->get();

            $selectOptions = $banks->map(function ($bank) {
                return [
                    'value' => $bank->bank_account_id,
                    'label' => $bank->display_name,
                    'bank_name' => $bank->bank_name,
                    'account_name' => $bank->bank_account_name,
                    'account_number' => $bank->bank_account_num,
                    'currency' => $bank->currency,
                    'account_type' => $bank->account_type,
                    'org_id' => $bank->org_id,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Banks for select dropdown retrieved successfully',
                'data' => $selectOptions,
                'total_count' => $selectOptions->count(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get banks for select: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch banks for dropdown',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a specific bank by ID from SQL database
     */
    public function getBank(Request $request, $id): JsonResponse
    {
        try {
            $bank = Bank::findOrFail($id);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Bank retrieved successfully',
                'data' => [
                    'id' => $bank->id,
                    'bank_account_id' => $bank->bank_account_id,
                    'bank_name' => $bank->bank_name,
                    'bank_account_name' => $bank->bank_account_name,
                    'bank_account_num' => $bank->bank_account_num,
                    'bank_branch_name' => $bank->bank_branch_name,
                    'account_type' => $bank->account_type,
                    'currency' => $bank->currency,
                    'country' => $bank->country,
                    'iban_number' => $bank->iban_number,
                    'org_id' => $bank->org_id,
                    'receipt_method_id' => $bank->receipt_method_id,
                    'receipt_class_id' => $bank->receipt_class_id,
                    'receipt_classes' => $bank->receipt_classes,
                    'status' => $bank->status,
                    'display_name' => $bank->display_name,
                    'bank_details' => $bank->bank_details,
                    'created_at' => $bank->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $bank->updated_at?->format('Y-m-d H:i:s'),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Bank not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get bank: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch bank from database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all banks from Oracle database
     */
    public function getOracleBanks(Request $request): JsonResponse
    {
        try {
            $query = OracleBankMaster::active()->orderBy('bank_name');

            // Filter by organization unit if provided
            if ($request->filled('ou_id')) {
                $query->byOrgUnit($request->ou_id);
            }

            // Filter by currency if provided
            if ($request->filled('currency')) {
                $query->where('currency', $request->currency);
            }

            // Filter by account type if provided
            if ($request->filled('account_type')) {
                $query->where('account_type', $request->account_type);
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $banks = $query->paginate($perPage);

            // Transform data for API response
            $banksData = $banks->getCollection()->map(function ($bank) {
                return [
                    'bank_id' => $bank->bank_id,
                    'bank_code' => $bank->bank_code,
                    'bank_name' => $bank->bank_name,
                    'bank_short_name' => $bank->bank_short_name,
                    'branch_code' => $bank->branch_code,
                    'branch_name' => $bank->branch_name,
                    'account_number' => $bank->account_number,
                    'account_title' => $bank->account_title,
                    'account_type' => $bank->account_type,
                    'currency' => $bank->currency,
                    'iban' => $bank->iban,
                    'swift_code' => $bank->swift_code,
                    'display_name' => $bank->display_name,
                    'city' => $bank->city,
                    'country' => $bank->country,
                    'phone_number' => $bank->phone_number,
                    'status' => $bank->status,
                    'ou_id' => $bank->ou_id,
                    'ou_name' => $bank->ou_name,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Oracle banks retrieved successfully',
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
            Log::error('Failed to fetch Oracle banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch banks from Oracle database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Search banks in Oracle database
     */
    public function searchOracleBanks(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $searchTerm = $request->get('search');
            $perPage = $request->get('per_page', 20);

            $banks = OracleBankMaster::active()
                ->search($searchTerm)
                ->orderBy('bank_name')
                ->paginate($perPage);

            // Transform data for API response
            $banksData = $banks->getCollection()->map(function ($bank) {
                return [
                    'bank_id' => $bank->bank_id,
                    'bank_code' => $bank->bank_code,
                    'bank_name' => $bank->bank_name,
                    'bank_short_name' => $bank->bank_short_name,
                    'branch_name' => $bank->branch_name,
                    'account_number' => $bank->account_number,
                    'account_title' => $bank->account_title,
                    'currency' => $bank->currency,
                    'display_name' => $bank->display_name,
                    'city' => $bank->city,
                    'status' => $bank->status,
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
            Log::error('Failed to search Oracle banks: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to search banks in Oracle database',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get banks formatted for select dropdown
     */
    public function getBanksForSelect(Request $request): JsonResponse
    {
        try {
            $ouId = $request->get('ou_id');
            $currency = $request->get('currency');

            $query = OracleBankMaster::active()->orderBy('bank_name');

            if ($ouId) {
                $query->byOrgUnit($ouId);
            }

            if ($currency) {
                $query->where('currency', $currency);
            }

            $banks = $query->get();

            $selectOptions = $banks->map(function ($bank) {
                return [
                    'value' => $bank->bank_id,
                    'label' => $bank->display_name,
                    'bank_code' => $bank->bank_code,
                    'currency' => $bank->currency,
                    'account_type' => $bank->account_type,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Banks for select dropdown retrieved successfully',
                'data' => $selectOptions,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to get banks for select: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch banks for dropdown',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Test Oracle database connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->bankService->testConnection();

            return response()->json([
                'success' => $result['success'],
                'status' => $result['success'] ? 200 : 500,
                'message' => $result['message'],
                'data' => [
                    'bank_count' => $result['count'],
                    'connection_status' => $result['success'] ? 'connected' : 'failed',
                ],
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Oracle connection test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Oracle connection test failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Connection error',
            ], 500);
        }
    }

    /**
     * Clear banks cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $cleared = $this->bankService->clearCache();

            return response()->json([
                'success' => $cleared,
                'status' => $cleared ? 200 : 500,
                'message' => $cleared ? 'Banks cache cleared successfully' : 'Failed to clear cache',
            ], $cleared ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Failed to clear banks cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to clear banks cache',
                'error' => app()->environment('local') ? $e->getMessage() : 'Cache error',
            ], 500);
        }
    }
}