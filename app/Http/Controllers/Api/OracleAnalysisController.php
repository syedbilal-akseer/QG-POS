<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OracleAnalysisController extends Controller
{
    /**
     * Get QG_SHIPPING_USERS data for user-organization analysis
     */
    public function getShippingUsers(): JsonResponse
    {
        try {
            // Get sample shipping users data
            $users = DB::connection('oracle')
                ->table('apps.qg_shipping_users')
                ->select('user_id', 'user_name', 'organization_code', 'organization_name')
                ->limit(20)
                ->get();

            // Get user organization count (check for multiple orgs per user)
            $userOrgCounts = DB::connection('oracle')
                ->table('apps.qg_shipping_users')
                ->select('user_id', DB::raw('COUNT(*) as org_count'))
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->limit(10)
                ->get();

            // Get total counts
            $totalUsers = DB::connection('oracle')
                ->table('apps.qg_shipping_users')
                ->distinct('user_id')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'sample_users' => $users,
                    'users_with_multiple_orgs' => $userOrgCounts,
                    'total_users' => $totalUsers,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch QG_SHIPPING_USERS data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping users data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get QG_ALL_USERS data for comparison
     */
    public function getAllUsers(): JsonResponse
    {
        try {
            $users = DB::connection('oracle')
                ->table('apps.qg_all_users')
                ->select('user_id', 'user_name')
                ->limit(20)
                ->get();

            $totalUsers = DB::connection('oracle')
                ->table('apps.qg_all_users')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'sample_users' => $users,
                    'total_users' => $totalUsers,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch QG_ALL_USERS data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch all users data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer data and relationships
     */
    public function getCustomerData(): JsonResponse
    {
        try {
            // Check what customer tables exist
            $customerTables = DB::connection('oracle')
                ->table('all_tables')
                ->select('table_name')
                ->where('owner', 'APPS')
                ->where('table_name', 'like', '%CUSTOMER%')
                ->get();

            // Get data from correct customer table
            $customerData = [];
            try {
                $customerData = DB::connection('oracle')
                    ->table('apps.qg_pos_customer_master')
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                $customerData = ['error' => 'QG_POS_CUSTOMER_MASTER table not accessible: ' . $e->getMessage()];
            }

            // Get distinct salesperson values
            $salespersons = [];
            try {
                $salespersons = DB::connection('oracle')
                    ->table('apps.qg_pos_customer_master')
                    ->select('salesperson')
                    ->distinct()
                    ->whereNotNull('salesperson')
                    ->limit(20)
                    ->get();
            } catch (\Exception $e) {
                $salespersons = ['error' => 'Could not fetch salesperson data: ' . $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'customer_tables' => $customerTables,
                    'sample_customers' => $customerData,
                    'sample_salespersons' => $salespersons,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch customer data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get product/item data and organization relationships
     */
    public function getProductData(): JsonResponse
    {
        try {
            // Get item tables
            $itemTables = DB::connection('oracle')
                ->table('all_tables')
                ->select('table_name')
                ->where('owner', 'APPS')
                ->where(function($query) {
                    $query->where('table_name', 'like', '%ITEM%')
                          ->orWhere('table_name', 'like', '%PRODUCT%');
                })
                ->get();

            // Get sample item data
            $itemData = [];
            try {
                $itemData = DB::connection('oracle')
                    ->table('apps.qg_pos_item_master')
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                $itemData = ['error' => 'QG_POS_ITEM_MASTER table not accessible: ' . $e->getMessage()];
            }

            // Check item master columns for organization fields
            $itemColumns = [];
            try {
                $itemColumns = DB::connection('oracle')
                    ->table('all_tab_columns')
                    ->select('column_name', 'data_type')
                    ->where('owner', 'APPS')
                    ->where('table_name', 'QG_POS_ITEM_MASTER')
                    ->where('column_name', 'like', '%ORG%')
                    ->get();
            } catch (\Exception $e) {
                $itemColumns = ['error' => 'Could not fetch item columns: ' . $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'item_tables' => $itemTables,
                    'sample_items' => $itemData,
                    'org_related_columns' => $itemColumns,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch product data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get price list data and relationships
     */
    public function getPriceListData(): JsonResponse
    {
        try {
            // Get price tables
            $priceTables = DB::connection('oracle')
                ->table('all_tables')
                ->select('table_name')
                ->where('owner', 'APPS')
                ->where('table_name', 'like', '%PRICE%')
                ->get();

            // Get sample price data
            $priceData = [];
            try {
                $priceData = DB::connection('oracle')
                    ->table('apps.qg_pos_item_price')
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                $priceData = ['error' => 'QG_POS_ITEM_PRICE table not accessible: ' . $e->getMessage()];
            }

            // Check price columns for organization/customer fields
            $priceColumns = [];
            try {
                $priceColumns = DB::connection('oracle')
                    ->table('all_tab_columns')
                    ->select('column_name', 'data_type')
                    ->where('owner', 'APPS')
                    ->where('table_name', 'QG_POS_ITEM_PRICE')
                    ->where(function($query) {
                        $query->where('column_name', 'like', '%ORG%')
                              ->orWhere('column_name', 'like', '%CUSTOMER%');
                    })
                    ->get();
            } catch (\Exception $e) {
                $priceColumns = ['error' => 'Could not fetch price columns: ' . $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'price_tables' => $priceTables,
                    'sample_prices' => $priceData,
                    'org_customer_columns' => $priceColumns,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch price list data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch price list data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get warehouse data and organization relationships
     */
    public function getWarehouseData(): JsonResponse
    {
        try {
            // Get warehouse tables
            $warehouseTables = DB::connection('oracle')
                ->table('all_tables')
                ->select('table_name')
                ->where('owner', 'APPS')
                ->where('table_name', 'like', '%WAREHOUSE%')
                ->get();

            // Get sample warehouse data
            $warehouseData = [];
            try {
                $warehouseData = DB::connection('oracle')
                    ->table('apps.qg_pos_warehouses')
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                $warehouseData = ['error' => 'QG_POS_WAREHOUSES table not accessible: ' . $e->getMessage()];
            }

            // Get warehouse columns
            $warehouseColumns = [];
            try {
                $warehouseColumns = DB::connection('oracle')
                    ->table('all_tab_columns')
                    ->select('column_name', 'data_type')
                    ->where('owner', 'APPS')
                    ->where('table_name', 'QG_POS_WAREHOUSES')
                    ->get();
            } catch (\Exception $e) {
                $warehouseColumns = ['error' => 'Could not fetch warehouse columns: ' . $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse_tables' => $warehouseTables,
                    'sample_warehouses' => $warehouseData,
                    'warehouse_columns' => $warehouseColumns,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch warehouse data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouse data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze user-customer relationships
     */
    public function getUserCustomerRelationships(): JsonResponse
    {
        try {
            // First, get customer table columns to check available fields
            $customerColumns = [];
            try {
                $customerColumns = DB::connection('oracle')
                    ->table('all_tab_columns')
                    ->select('column_name', 'data_type')
                    ->where('owner', 'APPS')
                    ->where('table_name', 'QG_POS_CUSTOMER_MASTER')
                    ->get();
            } catch (\Exception $e) {
                $customerColumns = ['error' => 'Could not fetch customer columns: ' . $e->getMessage()];
            }

            // Check salesperson-user name matching (simplified query)
            $userCustomerMatches = [];
            try {
                $userCustomerMatches = DB::connection('oracle')
                    ->table('apps.qg_shipping_users as u')
                    ->leftJoin('apps.qg_pos_customer_master as c', function($join) {
                        $join->whereRaw('UPPER(u.user_name) = UPPER(c.salesperson)');
                    })
                    ->select('u.user_id', 'u.user_name', 'c.salesperson', 'u.organization_code', 'c.ou_id')
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                $userCustomerMatches = ['error' => 'Could not analyze user-customer relationships: ' . $e->getMessage()];
            }

            // Check organization consistency with data type conversion
            $orgConsistency = [];
            try {
                $orgConsistency = DB::connection('oracle')
                    ->table('apps.qg_shipping_users as u')
                    ->join('apps.qg_pos_customer_master as c', function($join) {
                        $join->whereRaw('TO_CHAR(u.organization_code) = TO_CHAR(c.ou_id)');
                    })
                    ->select('u.organization_code', 'c.ou_id')
                    ->distinct()
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                $orgConsistency = ['error' => 'Could not check organization consistency: ' . $e->getMessage()];
            }

            // Check for orphaned relationships with data type conversion
            $orphanedUsers = [];
            $orphanedCustomers = [];
            try {
                $orphanedUsers = DB::connection('oracle')
                    ->table('apps.qg_shipping_users as u')
                    ->leftJoin('apps.qg_pos_customer_master as c', function($join) {
                        $join->whereRaw('TO_CHAR(u.organization_code) = TO_CHAR(c.ou_id)');
                    })
                    ->whereNull('c.ou_id')
                    ->select('u.user_id', 'u.user_name', 'u.organization_code')
                    ->limit(5)
                    ->get();

                $orphanedCustomers = DB::connection('oracle')
                    ->table('apps.qg_pos_customer_master as c')
                    ->leftJoin('apps.qg_shipping_users as u', function($join) {
                        $join->whereRaw('TO_CHAR(c.ou_id) = TO_CHAR(u.organization_code)');
                    })
                    ->whereNull('u.organization_code')
                    ->select('c.customer_id', 'c.customer_name', 'c.ou_id')
                    ->limit(5)
                    ->get();
            } catch (\Exception $e) {
                $orphanedUsers = ['error' => 'Could not check orphaned users: ' . $e->getMessage()];
                $orphanedCustomers = ['error' => 'Could not check orphaned customers: ' . $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'customer_table_columns' => $customerColumns,
                    'user_customer_matches' => $userCustomerMatches,
                    'organization_consistency' => $orgConsistency,
                    'orphaned_users' => $orphanedUsers,
                    'orphaned_customers' => $orphanedCustomers,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to analyze user-customer relationships', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze user-customer relationships: ' . $e->getMessage(),
            ], 500);
        }
    }
}