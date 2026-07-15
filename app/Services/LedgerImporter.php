<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\LedgerImport;

class LedgerImporter
{
    /**
     * Import parsed ledger.
     */
    public function import(LedgerImport $import, array $customers): array
    {
        $customerCount    = 0;
        $transactionCount = 0;
        $errors           = [];

        foreach ($customers as $customerData) {

            $customer = Customer::where(
                'customer_code',
                $customerData['customer_code']
            )->first();

            if (! $customer) {

                $errors[] = [
                    'customer_code' => $customerData['customer_code'],
                    'message'       => 'Customer not found.',
                ];

                continue;
            }

            $customerCount++;

            foreach ($customerData['transactions'] as $transaction) {

                if ($this->isDuplicate(
                    $customer->id,
                    $transaction
                )) {
                    continue;
                }

                CustomerLedger::create([

                    'customer_id'      => $customer->id,

                    'ledger_import_id' => $import->id,

                    'transaction_date' => $transaction['transaction_date'],

                    'document_type'    => $transaction['document_type'],

                    'document_no'      => $transaction['document_no'],

                    'description'      => $transaction['description'],

                    'debit'            => $transaction['debit'],

                    'credit'           => $transaction['credit'],

                    'balance'          => $transaction['balance'],

                ]);

                $transactionCount++;

                /*
                 |--------------------------------------------
                 | Update progress
                 |--------------------------------------------
                 */

                $import->increment(
                    'processed_transactions'
                );
            }
        }

        return [

            'customers'    => $customerCount,

            'transactions' => $transactionCount,

            'errors'       => $errors,

        ];
    }

    /**
     * Check duplicate transaction.
     */
    protected function isDuplicate(
        int $customerId,
        array $transaction
    ): bool {

        return CustomerLedger::where('customer_id', $customerId)

            ->whereDate(
                'transaction_date',
                $transaction['transaction_date']
            )

            ->where(
                'document_type',
                $transaction['document_type']
            )

            ->where(
                'document_no',
                $transaction['document_no']
            )

            ->where('debit', $transaction['debit'])

            ->where('credit', $transaction['credit'])

            ->exists();
    }
}
