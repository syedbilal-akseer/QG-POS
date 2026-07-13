<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestOracleController extends Controller
{
    /**
     * Test Oracle connection and list available tables
     */
    public function testOracleConnection(): JsonResponse
    {
        try {
            // Test basic connection
            $connection = DB::connection('oracle');
            
            // Get list of tables/views that might contain bank data
            $bankTables = $connection->select("
                SELECT table_name, owner 
                FROM all_tables
                ORDER BY table_name
            ");

            $bankViews = $connection->select("
                SELECT view_name, owner 
                FROM all_views 
                WHERE owner = 'APPS' 
                AND (UPPER(view_name) LIKE '%BANK%' OR UPPER(view_name) LIKE '%ACCOUNT%')
                ORDER BY view_name
            ");

            // Check if specific tables exist
            $specificTables = [
                'QG_BANK_MASTER',
                'BANK_MASTER',
                'GL_BANK_ACCOUNTS',
                'CE_BANK_ACCOUNTS',
                'CE_BANK_BRANCHES_V'
            ];

            $existingTables = [];
            foreach ($specificTables as $tableName) {
                try {
                    $exists = $connection->select("
                        SELECT COUNT(*) as count 
                        FROM all_objects 
                        WHERE owner = 'APPS' 
                        AND object_name = ? 
                        AND object_type IN ('TABLE', 'VIEW')
                    ", [$tableName]);
                    
                    if ($exists[0]->count > 0) {
                        $existingTables[] = $tableName;
                    }
                } catch (\Exception $e) {
                    // Continue checking other tables
                }
            }

            // Try to get sample data from any existing bank table
            $sampleData = [];
            if (!empty($existingTables)) {
                $firstTable = $existingTables[0];
                try {
                    $sampleData = $connection->select("
                        SELECT * FROM apps.{$firstTable} 
                        WHERE ROWNUM <= 5
                    ");
                } catch (\Exception $e) {
                    $sampleData = ['error' => $e->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Oracle connection successful',
                'data' => [
                    'connection_status' => 'connected',
                    'bank_tables' => $bankTables,
                    'bank_views' => $bankViews,
                    'existing_specific_tables' => $existingTables,
                    'sample_data_from_first_table' => $sampleData,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Oracle connection test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Oracle connection failed',
                'error' => $e->getMessage(),
                'suggestions' => [
                    'Check if Oracle database is running',
                    'Verify connection credentials in .env file',
                    'Ensure apps.qg_bank_master table/view exists',
                    'Check if user has proper permissions to access APPS schema'
                ]
            ], 500);
        }
    }

    /**
     * Create sample bank data in Oracle (if table exists)
     */
    public function createSampleBankData(): JsonResponse
    {
        try {
            $connection = DB::connection('oracle');
            
            // First check if table exists and get its structure
            $tableStructure = $connection->select("
                SELECT column_name, data_type, nullable
                FROM all_tab_columns 
                WHERE owner = 'APPS' 
                AND table_name = 'QG_BANK_MASTER'
                ORDER BY column_id
            ");

            if (empty($tableStructure)) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'QG_BANK_MASTER table not found',
                    'suggestion' => 'Please create the table first or use a different table name'
                ], 404);
            }

            // If table exists, try to insert sample data
            $sampleBanks = [
                [
                    'BANK_ID' => 'BNK001',
                    'BANK_CODE' => 'HBL',
                    'BANK_NAME' => 'Habib Bank Limited',
                    'BANK_SHORT_NAME' => 'HBL',
                    'BRANCH_CODE' => '0001',
                    'BRANCH_NAME' => 'Main Branch',
                    'ACCOUNT_NUMBER' => '10011234567890',
                    'ACCOUNT_TITLE' => 'QG Trading Company',
                    'ACCOUNT_TYPE' => 'CURRENT',
                    'CURRENCY' => 'PKR',
                    'IBAN' => 'PK36HABB0010011234567890',
                    'SWIFT_CODE' => 'HABBPKKA',
                    'STATUS' => 'ACTIVE',
                    'OU_ID' => 1,
                    'OU_NAME' => 'QG Trading Head Office',
                    'CREATED_BY' => 'SYSTEM',
                    'CREATION_DATE' => now(),
                ],
                // Add more sample banks as needed
            ];

            $insertedCount = 0;
            foreach ($sampleBanks as $bank) {
                try {
                    $connection->table('apps.qg_bank_master')->insert($bank);
                    $insertedCount++;
                } catch (\Exception $e) {
                    // Log but continue with other records
                    Log::warning('Failed to insert bank record: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Sample bank data creation completed',
                'data' => [
                    'table_structure' => $tableStructure,
                    'records_inserted' => $insertedCount,
                    'total_attempted' => count($sampleBanks),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to create sample bank data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to create sample bank data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Oracle database schema information
     */
    public function getOracleSchemaInfo(): JsonResponse
    {
        try {
            $connection = DB::connection('oracle');
            
            // Get current user and schema info
            $userInfo = $connection->select("SELECT USER as current_user FROM dual");
            
            // Get available schemas
            $schemas = $connection->select("
                SELECT DISTINCT owner 
                FROM all_tables 
                WHERE owner IN ('APPS', 'HR', 'GL', 'AR', 'AP', 'CE')
                ORDER BY owner
            ");

            // Get all tables in APPS schema
            $appsTables = $connection->select("
                SELECT table_name, num_rows 
                FROM all_tables 
                WHERE owner = 'APPS' 
                AND table_name LIKE '%POS%' 
                ORDER BY table_name
            ");

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Oracle schema information retrieved',
                'data' => [
                    'current_user' => $userInfo[0]->current_user ?? 'Unknown',
                    'available_schemas' => $schemas,
                    'apps_pos_tables' => $appsTables,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to get Oracle schema info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}