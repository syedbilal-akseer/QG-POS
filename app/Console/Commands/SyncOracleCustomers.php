<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\OracleCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleCustomers extends Command
{
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
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting customer sync from Oracle...');

        $created = 0;
        $updated = 0;
        $errors = 0;

        DB::transaction(function () use (&$created, &$updated, &$errors) {
            // Fetch all customers from Oracle
            $oracleCustomers = OracleCustomer::all();

            $this->info("Found {$oracleCustomers->count()} customers in Oracle.");

            foreach ($oracleCustomers as $oracleCustomer) {
                try {
                    // Check if customer exists
                    $existingCustomer = Customer::where('customer_id', $oracleCustomer->customer_id)->first();

                    // Sync to MySQL (match by customer_id)
                    Customer::updateOrCreate(
                        ['customer_id' => $oracleCustomer->customer_id], // Match on customer_id
                        values: [
                            'ou_id' => $oracleCustomer->ou_id,
                            'ou_name' => $oracleCustomer->ou,
                            'customer_name' => $oracleCustomer->customer_name,
                            'customer_number' => $oracleCustomer->customer_number,
                            'customer_site_id' => $oracleCustomer->customer_site_id,
                            'salesperson' => $oracleCustomer->salesperson,
                            'city' => $oracleCustomer->city,
                            'area' => $oracleCustomer->area,
                            'address1' => $oracleCustomer->customer_address,
                            'contact_number' => $oracleCustomer->contact_number,
                            'email_address' => $oracleCustomer->email_address,
                            'overall_credit_limit' => $oracleCustomer->overall_credit_limit, // Original credit limit
                            'customer_balance' => $oracleCustomer->customer_balance, // New CUSTOMER_BALANCE column from Oracle view
                            'credit_days' => $oracleCustomer->credit_days,
                            'nic' => $oracleCustomer->nic,
                            'ntn' => $oracleCustomer->ntn,
                            'sales_tax_registration_num' => $oracleCustomer->sales_tax_registration_num,
                            'category_code' => $oracleCustomer->category_code,
                            'creation_date' => $oracleCustomer->cust_creation_date,
                            'price_list_id' => $oracleCustomer->price_list_id,
                            'price_list_name' => $oracleCustomer->price_list_name,
                            // Oracle mapping fields
                            'oracle_customer_id' => $oracleCustomer->customer_id,
                            'oracle_ou_id' => $oracleCustomer->ou_id,
                            'oracle_salesperson' => $oracleCustomer->salesperson,
                            'updated_at' => now(),
                        ]
                    );

                    if ($existingCustomer) {
                        $updated++;
                    } else {
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("Error syncing customer {$oracleCustomer->customer_id}: " . $e->getMessage());
                }
            }
        });

        $this->info('Customers synced successfully.');
        $this->info("Created: {$created}, Updated: {$updated}, Errors: {$errors}");
        $this->info('Note: Both overall_credit_limit and customer_balance are synced from Oracle view (CUSTOMER_BALANCE column added).');
    }
}
