<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\OracleCustomer;
use App\Traits\OracleNlsSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOracleCustomers extends Command
{
    use OracleNlsSession;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync customers from Oracle database to MySQL database';

    /**
     * Resolve the salesperson_key for a raw Oracle salesperson string.
     *
     * Both this (customer master) view and qg_all_users.SALESPERON_NAME come
     * from the same Oracle-side salesperson string, so an exact match against
     * users.salesperson_name (populated by sync:oracle-users) is sufficient —
     * unlike the old approach, which bridged Oracle's free text against the
     * portal user's typed `name` via word-matching and needed hand-maintained
     * overrides for mismatches like "Zeeshan, Mr. Muhammad" vs "Zeeshan
     * Rasheed". Only whitespace is normalized here: Oracle sometimes emits a
     * non-breaking space (U+00A0) instead of a regular one.
     */
    protected function resolveSalespersonKey(?string $oracleSalesperson): string
    {
        return trim(str_replace("\xc2\xa0", ' ', (string) $oracleSalesperson));
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting customer sync from Oracle...');

        $created = 0;
        $updated = 0;
        $errors = 0;

        DB::transaction(function () use (&$created, &$updated, &$errors) {
            // Set Oracle session parameters to avoid NLS mismatch errors (ORA-01858)
            $this->setOracleNlsSession();

            // Fetch all customers from Oracle
            $oracleCustomers = OracleCustomer::all();

            $this->info("Found {$oracleCustomers->count()} customers in Oracle.");

            foreach ($oracleCustomers as $oracleCustomer) {
                $o = [];
                try {
                    // Read raw attributes once so we can safely look up every
                    // column. Strict-mode Eloquent throws if we access a column
                    // the Oracle view doesn't expose — using the raw array via
                    // `?? null` instead makes the sync resilient to missing
                    // columns regardless of which one is absent.
                    $o = $oracleCustomer->getAttributes();

                    // Match key — bail if Oracle didn't return a customer_id.
                    $oracleCustomerId = $o['customer_id'] ?? null;
                    if (!$oracleCustomerId) {
                        $this->warn('Skipping a row with no customer_id in the Oracle response.');
                        continue;
                    }

                    // Debug: surface the `area` value Oracle is returning for this
                    // customer so we can confirm exactly what's coming through.
                    $areaValue = $o['area'] ?? null;
                    $areaDebug = $areaValue === null
                        ? '(null)'
                        : ($areaValue === '' ? '(empty)' : $areaValue);
                    $customerNameDebug = $o['customer_name'] ?? 'unknown';
                    $this->line("  area for {$oracleCustomerId} ({$customerNameDebug}): {$areaDebug}");

                    // Check if customer exists
                    $existingCustomer = Customer::where('customer_id', $oracleCustomerId)->first();
                    $areaBeforeSave   = $existingCustomer->area ?? null;

                    // Sync to MySQL (match by customer_id)
                    Customer::updateOrCreate(
                        ['customer_id' => $oracleCustomerId],
                        values: [
                            'ou_id'                      => $o['ou_id'] ?? null,
                            'ou_name'                    => $o['ou'] ?? null,
                            'customer_name'              => $o['customer_name'] ?? null,
                            'customer_number'            => $o['customer_number'] ?? null,
                            'customer_site_id'           => $o['customer_site_id'] ?? null,
                            'salesperson'                => $o['salesperson'] ?? null,
                            'salesperson_key'            => $this->resolveSalespersonKey($o['salesperson'] ?? null),
                            'city'                       => $o['city'] ?? null,
                            'area'                       => $o['area'] ?? null,
                            'address1'                   => $o['customer_address'] ?? null,
                            'contact_number'             => $o['contact_number'] ?? null,
                            'email_address'              => $o['email_address'] ?? null,
                            'overall_credit_limit'       => $o['overall_credit_limit'] ?? null,
                            'customer_balance'           => $o['customer_balance'] ?? null,
                            'credit_days'                => $o['credit_days'] ?? null,
                            'nic'                        => $o['nic'] ?? null,
                            'ntn'                        => $o['ntn'] ?? null,
                            'sales_tax_registration_num' => $o['sales_tax_registration_num'] ?? null,
                            'category_code'              => $o['category_code'] ?? null,
                            'creation_date'              => !empty($o['cust_creation_date'])
                                ? Carbon::parse($o['cust_creation_date'])->format('Y-m-d H:i:s')
                                : null,
                            'price_list_id'              => $o['price_list_id'] ?? null,
                            'price_list_name'            => $o['price_list_name'] ?? null,
                            'payment_terms_id'           => $o['payment_terms_id'] ?? null,
                            'payment_terms_name'         => $o['payment_terms_name'] ?? null,
                            'oracle_customer_id'         => $oracleCustomerId,
                            'oracle_ou_id'               => $o['ou_id'] ?? null,
                            'oracle_salesperson'         => $o['salesperson'] ?? null,
                            'updated_at'                 => now(),
                        ]
                    );

                    // Verify what actually landed in MySQL — if before != after we
                    // know the row was touched, if it's still the same value we have
                    // evidence the write didn't take.
                    $areaAfterSave = Customer::where('customer_id', $oracleCustomerId)->value('area');
                    if ($areaBeforeSave !== $areaAfterSave) {
                        $this->line("    → area written: " . ($areaAfterSave ?? '(null)'));
                    }
                    Log::info('SyncOracleCustomers result', [
                        'customer_id'      => $oracleCustomerId,
                        'oracle_area'      => $areaValue,
                        'area_before_save' => $areaBeforeSave,
                        'area_after_save'  => $areaAfterSave,
                    ]);

                    if ($existingCustomer) {
                        $updated++;
                    } else {
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("Error syncing customer " . ($o['customer_id'] ?? '?') . ": " . $e->getMessage());
                    Log::error('SyncOracleCustomers failed for one row', [
                        'customer_id' => $o['customer_id'] ?? null,
                        'error'       => $e->getMessage(),
                        'trace'       => $e->getTraceAsString(),
                    ]);
                }
            }
        });

        $this->info('Customers synced successfully.');
        $this->info("Created: {$created}, Updated: {$updated}, Errors: {$errors}");
        $this->info('Note: Both overall_credit_limit and customer_balance are synced from Oracle view (CUSTOMER_BALANCE column added).');
    }
}
