<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListOracleViews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracle:list-views 
                            {--schema=APPS : Oracle schema to search (default: APPS)}
                            {--search= : Search term to filter views/tables}
                            {--type=all : Type to list (views, tables, all)}
                            {--limit=50 : Maximum number of results to show}
                            {--export : Export results to file}
                            {--describe= : Describe columns of specific table/view}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available views and tables from Oracle database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $schema = strtoupper($this->option('schema'));
            $search = $this->option('search');
            $type = $this->option('type');
            $limit = $this->option('limit');
            $export = $this->option('export');
            $describe = $this->option('describe');

            $this->info("Connecting to Oracle database...");

            // Test Oracle connection
            if (!$this->testOracleConnection()) {
                $this->error('Oracle connection failed. Please check your database configuration.');
                return Command::FAILURE;
            }

            $this->info("Connected successfully to Oracle database.");

            // If describe option is provided, show columns for specific table/view
            if ($describe) {
                return $this->describeObject($schema, $describe);
            }

            $this->line("Schema: {$schema}");
            if ($search) {
                $this->line("Search filter: {$search}");
            }
            $this->line("Type filter: {$type}");
            $this->line('');

            $results = [];

            // Get Views
            if ($type === 'all' || $type === 'views') {
                $views = $this->getViews($schema, $search, $limit);
                $results['views'] = $views;
                $this->displayViews($views);
            }

            // Get Tables
            if ($type === 'all' || $type === 'tables') {
                $tables = $this->getTables($schema, $search, $limit);
                $results['tables'] = $tables;
                $this->displayTables($tables);
            }

            // Export to file if requested
            if ($export) {
                $this->exportResults($results, $schema, $search, $type);
            }

            $this->info('Oracle views/tables listing completed successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Oracle list views command failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Test Oracle database connection
     */
    private function testOracleConnection(): bool
    {
        try {
            DB::connection('oracle')->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error('Oracle connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get views from Oracle
     */
    private function getViews(string $schema, ?string $search, int $limit): array
    {
        try {
            $sql = "
                SELECT 
                    view_name,
                    owner,
                    text_length,
                    type_text_length,
                    oid_text_length,
                    view_type_owner,
                    view_type
                FROM all_views 
                WHERE owner = :schema
            ";

            $params = ['schema' => $schema];

            if ($search) {
                $sql .= " AND UPPER(view_name) LIKE UPPER(:search)";
                $params['search'] = "%{$search}%";
            }

            $sql .= " ORDER BY view_name";

            if ($limit > 0) {
                $sql .= " FETCH FIRST :limit ROWS ONLY";
                $params['limit'] = $limit;
            }

            return DB::connection('oracle')->select($sql, $params);

        } catch (\Exception $e) {
            $this->error("Failed to get views: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get tables from Oracle
     */
    private function getTables(string $schema, ?string $search, int $limit): array
    {
        try {
            $sql = "
                SELECT 
                    table_name,
                    owner,
                    tablespace_name,
                    cluster_name,
                    iot_name,
                    status,
                    pct_free,
                    pct_used,
                    ini_trans,
                    max_trans,
                    initial_extent,
                    next_extent,
                    min_extents,
                    max_extents,
                    pct_increase,
                    freelists,
                    freelist_groups,
                    logging,
                    backed_up,
                    num_rows,
                    blocks,
                    empty_blocks,
                    avg_space,
                    chain_cnt,
                    avg_row_len,
                    avg_space_freelist_blocks,
                    num_freelist_blocks,
                    degree,
                    instances,
                    cache,
                    table_lock,
                    sample_size,
                    last_analyzed,
                    partitioned,
                    iot_type,
                    temporary,
                    secondary,
                    nested,
                    buffer_pool,
                    flash_cache,
                    cell_flash_cache,
                    row_movement,
                    global_stats,
                    user_stats,
                    duration,
                    skip_corrupt,
                    monitoring,
                    cluster_owner,
                    dependencies,
                    compression,
                    compress_for,
                    dropped,
                    read_only,
                    segment_created,
                    result_cache
                FROM all_tables 
                WHERE owner = :schema
            ";

            $params = ['schema' => $schema];

            if ($search) {
                $sql .= " AND UPPER(table_name) LIKE UPPER(:search)";
                $params['search'] = "%{$search}%";
            }

            $sql .= " ORDER BY table_name";

            if ($limit > 0) {
                $sql .= " FETCH FIRST :limit ROWS ONLY";
                $params['limit'] = $limit;
            }

            return DB::connection('oracle')->select($sql, $params);

        } catch (\Exception $e) {
            $this->error("Failed to get tables: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Display views in a formatted table
     */
    private function displayViews(array $views): void
    {
        if (empty($views)) {
            $this->warn('No views found.');
            return;
        }

        $this->info("📋 VIEWS ({" . count($views) . "} found)");
        $this->line('');

        $headers = ['View Name', 'Owner', 'Text Length', 'Type'];
        $rows = [];

        foreach ($views as $view) {
            $rows[] = [
                $view->view_name,
                $view->owner,
                $view->text_length ?? 'N/A',
                $view->view_type ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    /**
     * Display tables in a formatted table
     */
    private function displayTables(array $tables): void
    {
        if (empty($tables)) {
            $this->warn('No tables found.');
            return;
        }

        $this->info("🗃️  TABLES (" . count($tables) . " found)");
        $this->line('');

        $headers = ['Table Name', 'Owner', 'Rows', 'Status', 'Partitioned', 'Temporary'];
        $rows = [];

        foreach ($tables as $table) {
            $rows[] = [
                $table->table_name,
                $table->owner,
                number_format($table->num_rows ?? 0),
                $table->status ?? 'N/A',
                $table->partitioned ?? 'NO',
                $table->temporary ?? 'N',
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    /**
     * Export results to file
     */
    private function exportResults(array $results, string $schema, ?string $search, string $type): void
    {
        try {
            $filename = storage_path('app/oracle_objects_' . strtolower($schema) . '_' . date('Y-m-d_H-i-s') . '.json');
            
            $exportData = [
                'export_info' => [
                    'schema' => $schema,
                    'search_filter' => $search,
                    'type_filter' => $type,
                    'exported_at' => now()->toDateTimeString(),
                    'total_views' => count($results['views'] ?? []),
                    'total_tables' => count($results['tables'] ?? []),
                ],
                'data' => $results,
            ];

            file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT));
            
            $this->info("Results exported to: {$filename}");
            
        } catch (\Exception $e) {
            $this->error("Failed to export results: " . $e->getMessage());
        }
    }

    /**
     * Describe columns of a specific table or view
     */
    private function describeObject(string $schema, string $objectName): int
    {
        try {
            $objectName = strtoupper($objectName);
            $this->line("Schema: {$schema}");
            $this->line("Object: {$objectName}");
            $this->line('');

            // Get column information
            $columns = $this->getColumns($schema, $objectName);
            
            if (empty($columns)) {
                $this->warn("No columns found for {$schema}.{$objectName}");
                $this->info("Note: Make sure the table/view exists and you have access to it.");
                return Command::FAILURE;
            }

            $this->displayColumns($objectName, $columns);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to describe object: ' . $e->getMessage());
            Log::error('Oracle describe object failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Get columns for a specific table or view
     */
    private function getColumns(string $schema, string $objectName): array
    {
        try {
            $sql = "
                SELECT 
                    column_name,
                    data_type,
                    data_length,
                    data_precision,
                    data_scale,
                    nullable,
                    data_default,
                    column_id,
                    char_length,
                    char_used
                FROM all_tab_columns 
                WHERE owner = :schema 
                AND table_name = :object_name
                ORDER BY column_id
            ";

            $params = [
                'schema' => $schema,
                'object_name' => $objectName
            ];

            return DB::connection('oracle')->select($sql, $params);

        } catch (\Exception $e) {
            $this->error("Failed to get columns: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Display columns in a formatted table
     */
    private function displayColumns(string $objectName, array $columns): void
    {
        $this->info("📊 COLUMNS for {$objectName} (" . count($columns) . " found)");
        $this->line('');

        $headers = ['#', 'Column Name', 'Data Type', 'Length/Precision', 'Scale', 'Nullable', 'Default'];
        $rows = [];

        foreach ($columns as $column) {
            // Format data type with length/precision
            $dataType = $column->data_type;
            $lengthInfo = '';
            
            if ($column->data_type === 'NUMBER') {
                if ($column->data_precision) {
                    $lengthInfo = $column->data_precision;
                    if ($column->data_scale) {
                        $lengthInfo .= ',' . $column->data_scale;
                    }
                }
            } elseif (in_array($column->data_type, ['VARCHAR2', 'CHAR', 'NVARCHAR2', 'NCHAR'])) {
                $lengthInfo = $column->char_length ?? $column->data_length;
            } elseif ($column->data_length) {
                $lengthInfo = $column->data_length;
            }

            $rows[] = [
                $column->column_id ?? '',
                $column->column_name,
                $dataType,
                $lengthInfo,
                $column->data_scale ?? '',
                $column->nullable === 'Y' ? 'YES' : 'NO',
                $column->data_default ? substr(trim($column->data_default), 0, 20) . '...' : '',
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
    }
}