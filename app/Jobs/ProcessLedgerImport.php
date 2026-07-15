<?php

namespace App\Jobs;

use App\Models\LedgerImport;
use App\Services\LedgerImporter;
use App\Services\LedgerPdfParser;
use App\Services\LedgerValidator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLedgerImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry attempts.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Timeout in seconds.
     *
     * @var int
     */
    public $timeout = 1800; // 30 minutes

    /**
     * @var \App\Models\LedgerImport
     */
    protected $import;

    /**
     * Create a new job instance.
     */
    public function __construct(LedgerImport $import)
    {
        $this->import = $import;
    }

    /**
     * Execute the job.
     */
    public function handle(
        LedgerPdfParser $parser,
        LedgerValidator $validator,
        LedgerImporter $importer
    ): void {

        $this->import->update([
            'status' => 'processing',
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | STEP 1
            | Parse PDF
            |--------------------------------------------------------------------------
            */

            $customers = $parser->parse(
                storage_path('app/' . $this->import->file_path)
            );

            /*
            |--------------------------------------------------------------------------
            | STEP 2
            | Validate
            |--------------------------------------------------------------------------
            */

            $validated = $validator->validate($customers);

            /*
            |--------------------------------------------------------------------------
            | STEP 3
            | Save
            |--------------------------------------------------------------------------
            */

            DB::beginTransaction();

            $result = $importer->import(
                $this->import,
                $validated
            );

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | STEP 4
            | Update Import
            |--------------------------------------------------------------------------
            */

            $this->import->update([

                'status' => 'completed',

                'total_customers' =>
                    $result['customers'],

                'total_transactions' =>
                    $result['transactions'],

                'processed_transactions' =>
                    $result['transactions'],

                'error_log' =>
                    empty($result['errors'])
                        ? null
                        : json_encode(
                            $result['errors'],
                            JSON_PRETTY_PRINT
                        ),
            ]);
        } catch (Exception $e) {

            DB::rollBack();

            Log::error(
                'Ledger Import Failed',
                [
                    'import_id' => $this->import->id,
                    'message'   => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]
            );

            $this->import->update([

                'status' => 'failed',

                'error_log' => $e->getMessage(),

            ]);

            throw $e;
        }
    }

    /**
     * Called when all retries fail.
     */
    public function failed(Exception $exception): void
    {
        $this->import->update([

            'status' => 'failed',

            'error_log' => $exception->getMessage(),

        ]);

        Log::error(
            'Ledger Import Permanently Failed',
            [
                'import_id' => $this->import->id,
                'message'   => $exception->getMessage(),
            ]
        );
    }
}
