<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LedgerValidator
{
    /**
     * Validation errors.
     */
    protected array $errors = [];

    /**
     * Validation warnings.
     */
    protected array $warnings = [];

    /**
     * Validate parsed customers.
     */
    public function validate(array $customers): array
    {
        $validated = [];

        foreach ($customers as $customer) {

            if (!$this->isValidCustomer($customer)) {
                continue;
            }

            $customer['transactions'] = $this->validateTransactions(
                $customer['transactions'] ?? [],
                $customer['customer_code']
            );

            $validated[] = $customer;
        }

        return $validated;
    }

    /**
     * Validate customer.
     */
    protected function isValidCustomer(array $customer): bool
    {
        if (empty($customer['customer_code'])) {

            $this->errors[] = [
                'type' => 'customer',
                'message' => 'Customer code missing.',
            ];

            return false;
        }

        if (empty($customer['customer_name'])) {

            $this->errors[] = [
                'type' => 'customer',
                'customer' => $customer['customer_code'],
                'message' => 'Customer name missing.',
            ];

            return false;
        }

        return true;
    }

    /**
     * Validate customer transactions.
     */
    protected function validateTransactions(array $transactions, string $customerCode): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {

            if (!$this->isValidTransaction($transaction, $customerCode)) {
                continue;
            }

            $transaction['transaction_date'] = $this->normalizeDate(
                $transaction['transaction_date'] ?? null
            );

            $transaction['debit'] = $this->normalizeAmount(
                $transaction['debit'] ?? 0
            );

            $transaction['credit'] = $this->normalizeAmount(
                $transaction['credit'] ?? 0
            );

            $transaction['balance'] = $this->normalizeAmount(
                $transaction['balance'] ?? 0
            );

            $transaction['document_type'] = trim(
                $transaction['document_type'] ?? ''
            );

            $transaction['document_no'] = trim(
                $transaction['document_no'] ?? ''
            );

            $transaction['description'] = trim(
                $transaction['description'] ?? ''
            );

            $rows[] = $transaction;
        }

        return $rows;
    }

    /**
     * Validate single transaction.
     */
    protected function isValidTransaction(array $transaction, string $customerCode): bool
    {
        if (empty($transaction['transaction_date'])) {

            $this->warnings[] = [
                'customer' => $customerCode,
                'message' => 'Transaction skipped. Missing date.',
            ];

            return false;
        }

        if (
            empty($transaction['document_no']) &&
            empty($transaction['description'])
        ) {

            $this->warnings[] = [
                'customer' => $customerCode,
                'message' => 'Empty transaction skipped.',
            ];

            return false;
        }

        return true;
    }

    /**
     * Normalize amount.
     */
    protected function normalizeAmount($amount): float
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $amount = str_replace(',', '', $amount);
        $amount = trim($amount);

        return is_numeric($amount)
            ? round((float) $amount, 2)
            : 0;
    }

    /**
     * Normalize date.
     */
    protected function normalizeDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        try {

            return Carbon::createFromFormat(
                'd-M-y',
                strtoupper(trim($date))
            )->format('Y-m-d');

        } catch (\Throwable $e) {

            return null;
        }
    }

    /**
     * Get validation errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get warnings.
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Total errors.
     */
    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Total warnings.
     */
    public function warningCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Log validation result.
     */
    public function log(): void
    {
        foreach ($this->errors as $error) {
            Log::error('Ledger Validation', $error);
        }

        foreach ($this->warnings as $warning) {
            Log::warning('Ledger Validation', $warning);
        }
    }
}
