<?php
namespace App\Services;

use Exception;
use Smalot\PdfParser\Parser;

class LedgerPdfParser
{
    protected string $text = '';

    protected array $lines = [];

    protected array $customers = [];

    protected ?array $currentCustomer = null;

    protected ?array $currentTransaction = null;

    /**
     * Parse complete PDF
     */
    public function parse(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new Exception("PDF not found : {$pdfPath}");
        }

        /*
        |--------------------------------------------------------------------------
        | Read PDF
        |--------------------------------------------------------------------------
        */

        $this->text = $this->readPdf($pdfPath);
        // dd($this->text);
        /*
        |--------------------------------------------------------------------------
        | Normalize
        |--------------------------------------------------------------------------
        */

        $this->lines = $this->normalize(
            preg_split("/\r\n|\n|\r/", $this->text)
        );

        /*
        |--------------------------------------------------------------------------
        | Parse
        |--------------------------------------------------------------------------
        */

        $this->parseLines();

        /*
        |--------------------------------------------------------------------------
        | Save remaining data
        |--------------------------------------------------------------------------
        */

        $this->finishCurrentTransaction();

        $this->finishCurrentCustomer();

        return $this->customers;
    }

    /**
     * Read PDF
     */
    protected function readPdf(string $pdfPath): string
    {
        $tempTextFile = tempnam(sys_get_temp_dir(), 'ledger_');

        $command = sprintf(
            'pdftotext -table %s %s',
            escapeshellarg($pdfPath),
            escapeshellarg($tempTextFile)
        );

        exec($command, $output, $code);

        if ($code !== 0) {
            throw new Exception('Unable to extract PDF text.');
        }

        $text = file_get_contents($tempTextFile);

        unlink($tempTextFile);
        // dd(str_replace("\r\n", "\n", $text));
        return str_replace("\r\n", "\n", $text);
    }

    /**
     * Normalize extracted text
     */
    protected function normalize(array $lines): array
    {
        $clean = [];

        foreach ($lines as $line) {

            $line = str_replace(
                ["▶", "◀", "\f"],
                '',
                $line
            );

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Remove tabs
            $line = str_replace("\t", ' ', $line);

            // Remove duplicate spaces
            $line = preg_replace('/\s+/', ' ', $line);

            // Remove non-breaking spaces
            $line = preg_replace('/\x{00A0}/u', ' ', $line);

            if ($this->isIgnoredLine($line)) {
                continue;
            }

            $clean[] = $line;
        }

        return array_values($clean);
    }

    /**
     * Ignore repeated report lines
     */
    protected function isIgnoredLine(string $line): bool
    {
        $ignore = [

            'Customer Ledger',

            'QG Hardware Building Material',

            'Printed By',

            'Please identify',

            'assumed as correct',

            'PDC in hand',

            'Opening Balance',

            'From Date',

            'To Date',

            'Page',

            'Date Inv No',

        ];

        foreach ($ignore as $text) {

            if (stripos($line, $text) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function startsWithDate(string $line): bool
    {
        return preg_match(
            '/^\d{2}-[A-Z]{3}-\d{2}/i',
            $line
        ) === 1;
    }
    protected function parseTransaction(string $line): array
    {
        preg_match(
            '/^(\d{2}-[A-Z]{3}-\d{2})\s+(\S+)\s+(Receipt|INV|CM)\s+(.*)$/i',
            $line,
            $match
        );

        $amounts = $this->extractAmounts($line);

        return [

            'transaction_date' => $match[1] ?? null,

            'document_no'      => $match[2] ?? null,

            'document_type'    => strtoupper($match[3] ?? ''),

            'description'      => $this->extractDescription($line),

            'debit'            => $amounts['debit'],

            'credit'           => $amounts['credit'],

            'balance'          => $amounts['balance'],

        ];
    }
    protected function extractAmounts(string $line): array
    {
        $result = [

            'debit'   => 0,

            'credit'  => 0,

            'balance' => 0,

        ];

        /*
    Remove Dr/Cr
    */

        $line = preg_replace('/\s+(Dr|Cr)\.?$/i', '', trim($line));

        /*
    Match ending

    amount balance

    Example

    426,600 5,876,579.36
    */

        if (! preg_match(

            '/(\d[\d,]*(?:\.\d{2})?)\s+(\d[\d,]*\.\d{2})$/',

            $line,

            $match

        )) {

            return $result;
        }

        $amount = (float) str_replace(',', '', $match[1]);

        $balance = (float) str_replace(',', '', $match[2]);

        $result['balance'] = $balance;

        if (stripos($line, 'INV') !== false) {

            $result['debit'] = $amount;

        } else {

            $result['credit'] = $amount;

        }

        return $result;
    }
    protected function extractDescription(string $line): string
    {
        /*
    Remove:
    Date + Document No + Document Type
    */

        $line = preg_replace(
            '/^\d{2}-[A-Z]{3}-\d{2}\s+\S+\s+(Receipt|INV|CM)\s+/i',
            '',
            $line
        );

        /*
    Remove trailing balance
    Example:
    5,876,579.36 Dr.
    */

        $line = preg_replace(
            '/\d[\d,]*\.\d{2}\s*(Dr|Cr)?\s*$/i',
            '',
            $line
        );

        /*
    Remove trailing debit/credit amount
    Example:
    426,600
    or
    426,600.00
    */

        $line = preg_replace(
            '/\d[\d,]*(\.\d{2})?\s*$/',
            '',
            $line
        );

        /*
    Cleanup spaces
    */

        $line = preg_replace('/\s+/', ' ', $line);

        return trim($line);
    }
    protected function appendDescription(string $line): void
    {
        if (! $this->currentTransaction) {
            return;
        }

        $line = trim($line);

        if ($line === '') {
            return;
        }

        /*
     * Ignore page artifacts
     */

        if (
            str_contains($line, 'Customer Ledger') ||
            str_contains($line, 'Printed By') ||
            str_contains($line, 'Page ')
        ) {
            return;
        }

        $this->currentTransaction['description'] .= ' ' . $line;

        $this->currentTransaction['description'] = preg_replace(
            '/\s+/',
            ' ',
            $this->currentTransaction['description']
        );

        $this->currentTransaction['description'] = trim(
            $this->currentTransaction['description']
        );
    }
    /**
     * Main parser loop
     */
    protected function parseLines(): void
    {
        foreach ($this->lines as $line) {

            /*
        |--------------------------------------------------------------------------
        | Customer Header
        |--------------------------------------------------------------------------
        */

            if ($this->isCustomerHeader($line)) {

                $this->parseCustomer($line);

                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */

            if (
                $this->currentCustomer &&
                str_starts_with($line, 'Address')
            ) {

                $this->currentCustomer['address'] = trim(
                    preg_replace('/^Address\s*:\s*/i', '', $line)
                );

                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | Customer Total
        |--------------------------------------------------------------------------
        */

            if ($this->isTotalLine($line)) {

                $this->finishCurrentTransaction();

                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | New Transaction
        |--------------------------------------------------------------------------
        */

            if ($this->startsWithDate($line)) {

                $this->finishCurrentTransaction();

                $this->currentTransaction = $this->parseTransaction($line);

                continue;
            }

            /*
        |--------------------------------------------------------------------------
        | Description Continuation
        |--------------------------------------------------------------------------
        */

            if ($this->currentTransaction) {

                $this->appendDescription($line);
            }
        }
    }

    /**
     * Customer header
     *
     * Customer : 12355 Ahmed Traders
     */
    protected function isCustomerHeader(string $line): bool
    {
        return preg_match(
            '/^Customer\s*:\s*\d+/i',
            $line
        ) === 1;
    }
    protected function parseCustomer(string $line): void
    {
        preg_match(
            '/Customer\s*:\s*(\d+)\s+(.*?)\s+Salesperson\s*:/i',
            $line,
            $match
        );

        if (! $match) {
            return;
        }

        $customerCode = trim($match[1]);

        /*
     * Same customer continued on next page
     */

        if (
            $this->currentCustomer &&
            $this->currentCustomer['customer_code'] === $customerCode
        ) {
            return;
        }

        $this->finishCurrentTransaction();

        $this->finishCurrentCustomer();

        $this->currentCustomer = [

            'customer_code' => $customerCode,

            'customer_name' => trim($match[2]),

            'address'       => '',

            'transactions'  => [],

        ];
    }
    /**
     * Date
     *
     * 01-JUN-26
     */
    protected function isTransactionStart(string $line): bool
    {
        return preg_match(
            '/^\d{2}-[A-Z]{3}-\d{2}$/i',
            strtoupper($line)
        ) === 1;
    }

    /**
     * Total Of Customer
     */
    protected function isTotalLine(string $line): bool
    {
        return stripos($line, 'Total Of') === 0;
    }

    /**
     * Numeric amount
     */
    protected function isAmount(string $line): bool
    {
        $line = str_replace(',', '', trim($line));

        return is_numeric($line);
    }

    /**
     * Convert amount
     */
    protected function amount(string $value): float
    {
        $value = str_replace(',', '', trim($value));

        return is_numeric($value)
            ? (float) $value
            : 0;
    }

    /**
     * Save current transaction
     */
    protected function finishCurrentTransaction(): void
    {
        if (! $this->currentCustomer) {
            return;
        }

        if (! $this->currentTransaction) {
            return;
        }

        $this->currentCustomer['transactions'][] =
        $this->currentTransaction;

        $this->currentTransaction = null;
    }

    /**
     * Save current customer
     */
    protected function finishCurrentCustomer(): void
    {
        if (! $this->currentCustomer) {
            return;
        }

        $this->customers[] = $this->currentCustomer;

        $this->currentCustomer = null;
    }
}
