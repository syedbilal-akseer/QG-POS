<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * One-off backfill: copy customers.contact_number into invoices.customer_phone
 * for invoice rows that don't already have a phone.
 *
 * Run after pulling the phone-resolution fix into production — newly imported
 * invoices already get the right number, but older rows still have NULL.
 *
 * Only touches rows where customer_phone IS NULL or '' — any manually-corrected
 * phones in the "Update Phone" UI are preserved.
 *
 *   php artisan invoices:backfill-customer-phone --dry-run    # preview only
 *   php artisan invoices:backfill-customer-phone              # actually write
 */
class BackfillInvoiceCustomerPhones extends Command
{
    protected $signature = 'invoices:backfill-customer-phone {--dry-run : Report what would change without writing}';
    protected $description = 'Fill invoices.customer_phone from customers.contact_number for rows that have no phone yet';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Invoice::query()->where(function ($q) {
            $q->whereNull('customer_phone')->orWhere('customer_phone', '');
        });

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No invoices missing a phone — nothing to do.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Scanning {$total} invoices with no phone…");

        $updated = 0;
        $noCustomer = 0;
        $unparseable = 0;

        $query->chunkById(500, function ($invoices) use (&$updated, &$noCustomer, &$unparseable, $dryRun) {
            foreach ($invoices as $invoice) {
                $raw = $this->lookupContactNumber($invoice->customer_code);
                if (!$raw) {
                    $noCustomer++;
                    continue;
                }

                $normalized = $this->normalizePakistaniPhone($raw);
                if (!$normalized) {
                    $unparseable++;
                    continue;
                }

                if (!$dryRun) {
                    $invoice->update(['customer_phone' => $normalized]);
                }
                $updated++;
            }
        });

        $this->info(($dryRun ? '[DRY RUN] ' : '') . sprintf(
            'Done — would update: %d; no customer on file: %d; unparseable phone: %d',
            $updated,
            $noCustomer,
            $unparseable
        ));

        return self::SUCCESS;
    }

    /**
     * Match the invoice's customer_code against either customers.customer_number
     * or customers.customer_id (invoices may store either form).
     */
    private function lookupContactNumber(?string $customerCode): ?string
    {
        if (!$customerCode) return null;

        $phone = Customer::where('customer_number', $customerCode)
            ->orWhere('customer_id', $customerCode)
            ->value('contact_number');

        return $phone ?: null;
    }

    /**
     * Canonicalize Pakistani numbers to "+92XXXXXXXXXX" form. Mirrors the
     * helper in InvoiceController so backfilled values match newly-imported
     * ones exactly.
     */
    private function normalizePakistaniPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') return null;

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '' || strlen($digits) < 9 || strlen($digits) > 15) return null;

        if (str_starts_with($digits, '92')) return '+' . $digits;
        if (str_starts_with($digits, '0'))  return '+92' . substr($digits, 1);
        if (preg_match('/^3\d{9}$/', $digits)) return '+92' . $digits;

        return '+' . $digits;
    }
}
