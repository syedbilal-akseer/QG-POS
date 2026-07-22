<?php

namespace App\Console\Commands;

use App\Http\Controllers\InvoiceController;
use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-verify every invoice's own split PDF (invoices.pdf_path) against the
 * CURRENT parser to catch rows that a since-fixed page-attribution bug
 * misattached to the wrong customer (or that mix pages from more than one
 * customer into a single invoice row).
 *
 * Each invoice's pdf_path is already a per-customer split of the original
 * upload, so re-running extractCustomersFromPDF() on it should detect
 * exactly one customer, matching invoices.customer_code. If it detects a
 * different customer, or more than one, that row is invalid.
 *
 * Also flags (and deletes, with --delete) two other "shouldn't exist" cases
 * that don't need re-parsing at all: pdf_path pointing at a file that's no
 * longer on disk, and rows with no invoice_number at all.
 *
 * Always a dry-run report unless --delete is passed. A CSV of every
 * mismatch/mixed row is written to storage/app/ on every run (dry-run or
 * not) so there's an audit trail before anything is removed.
 *
 *   php artisan invoices:verify-customers                 # report only
 *   php artisan invoices:verify-customers --delete         # report + delete confirmed bad rows
 *   php artisan invoices:verify-customers --limit=50       # sanity-check on a small batch first
 */
class VerifyInvoiceCustomers extends Command
{
    protected $signature = 'invoices:verify-customers
        {--delete : Delete invoices confirmed mismatched/mixed (and their PDF files). Without this flag, nothing is deleted.}
        {--limit=0 : Only check this many invoices (0 = no limit)}';

    protected $description = 'Re-parse every invoice\'s own PDF and flag/delete rows whose stored customer_code disagrees with what the current parser detects';

    public function handle(): int
    {
        $delete = (bool) $this->option('delete');
        $limit  = (int) $this->option('limit');

        $query = Invoice::query()
            ->where('processing_status', '!=', 'failed')
            ->where('pdf_path', '!=', '')
            ->whereNotNull('pdf_path')
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($limit > 0) {
            $total = min($total, $limit);
        }

        if ($total === 0) {
            $this->info('No invoices to check.');
            return self::SUCCESS;
        }

        $this->info(($delete ? '[DELETE MODE] ' : '[DRY RUN] ') . "Checking {$total} invoice(s)…");

        $checked          = 0;
        $ok               = 0;
        $mismatched       = 0;
        $mixed            = 0;
        $fileMissing      = 0;
        $noInvoiceNumber  = 0;
        $unverifiable     = 0;
        $deleted          = 0;
        $flagged          = [];

        $controller = app(InvoiceController::class);
        $bar        = $this->output->createProgressBar($total);
        $bar->start();

        $processRow = function (Invoice $invoice) use (
            $controller, $delete,
            &$checked, &$ok, &$mismatched, &$mixed, &$fileMissing, &$noInvoiceNumber, &$unverifiable, &$deleted, &$flagged, $bar
        ) {
            $bar->advance();

            // No physical file on disk — nothing to verify or send, just a
            // dangling DB row. Flag it (and delete outright with --delete).
            if (!Storage::disk('local')->exists($invoice->pdf_path)) {
                $fileMissing++;
                $flagged[] = [
                    'id'             => $invoice->id,
                    'issue'          => 'file_missing',
                    'stored_code'    => $invoice->customer_code,
                    'stored_name'    => $invoice->customer_name,
                    'detected_codes' => '—',
                    'pdf_path'       => $invoice->pdf_path,
                    'uploaded_at'    => $invoice->uploaded_at,
                ];
                if ($delete) {
                    $this->deleteInvoice($invoice, 'pdf_path missing on disk — no file to verify');
                    $deleted++;
                }
                return;
            }

            // No invoice_number at all — can't be sent or reconciled against
            // Oracle either way, same "shouldn't be here" bucket.
            if (empty($invoice->invoice_number)) {
                $noInvoiceNumber++;
                $flagged[] = [
                    'id'             => $invoice->id,
                    'issue'          => 'no_invoice_number',
                    'stored_code'    => $invoice->customer_code,
                    'stored_name'    => $invoice->customer_name,
                    'detected_codes' => '—',
                    'pdf_path'       => $invoice->pdf_path,
                    'uploaded_at'    => $invoice->uploaded_at,
                ];
                if ($delete) {
                    $this->deleteInvoice($invoice, 'missing invoice_number');
                    $deleted++;
                }
                return;
            }

            $checked++;
            $fullPath = Storage::disk('local')->path($invoice->pdf_path);
            $detected = $controller->extractCustomersFromPDF($fullPath);

            if (empty($detected)) {
                $unverifiable++;
                return;
            }

            if (count($detected) > 1) {
                $mixed++;
                $flagged[] = [
                    'id'             => $invoice->id,
                    'issue'          => 'mixed',
                    'stored_code'    => $invoice->customer_code,
                    'stored_name'    => $invoice->customer_name,
                    'detected_codes' => implode('; ', array_map(
                        fn ($c) => $c['customer_code'] . ' (' . $c['customer_name'] . ')',
                        $detected
                    )),
                    'pdf_path'    => $invoice->pdf_path,
                    'uploaded_at' => $invoice->uploaded_at,
                ];
                if ($delete) {
                    $this->deleteInvoice($invoice, 'mixed pages from multiple customers');
                    $deleted++;
                }
                return;
            }

            $detectedCode = $detected[0]['customer_code'] ?? null;
            $detectedName = $detected[0]['customer_name'] ?? null;

            if ($detectedCode === $invoice->customer_code) {
                $ok++;
                return;
            }

            $mismatched++;
            $flagged[] = [
                'id'             => $invoice->id,
                'issue'          => 'mismatch',
                'stored_code'    => $invoice->customer_code,
                'stored_name'    => $invoice->customer_name,
                'detected_codes' => $detectedCode . ' (' . $detectedName . ')',
                'pdf_path'       => $invoice->pdf_path,
                'uploaded_at'    => $invoice->uploaded_at,
            ];
            if ($delete) {
                $this->deleteInvoice($invoice, "misattached — belongs to {$detectedCode} ({$detectedName})");
                $deleted++;
            }
        };

        if ($limit > 0) {
            $query->limit($limit)->get()->each($processRow);
        } else {
            $query->chunkById(200, fn ($invoices) => $invoices->each($processRow));
        }

        $bar->finish();
        $this->newLine(2);

        if (!empty($flagged)) {
            $this->table(
                ['ID', 'Issue', 'Stored', 'Detected', 'Uploaded'],
                collect($flagged)->map(fn ($f) => [
                    $f['id'],
                    $f['issue'],
                    "{$f['stored_code']} ({$f['stored_name']})",
                    $f['detected_codes'],
                    $f['uploaded_at'],
                ])
            );

            $csvPath = 'invoice-customer-mismatches-' . now()->format('Y-m-d_His') . '.csv';
            $fh = fopen(Storage::disk('local')->path($csvPath), 'w');
            fputcsv($fh, ['id', 'issue', 'stored_code', 'stored_name', 'detected', 'pdf_path', 'uploaded_at']);
            foreach ($flagged as $f) {
                fputcsv($fh, [
                    $f['id'], $f['issue'], $f['stored_code'], $f['stored_name'],
                    $f['detected_codes'], $f['pdf_path'], $f['uploaded_at'],
                ]);
            }
            fclose($fh);
            $this->info("Full report written to storage/app/{$csvPath}");
        }

        $this->info(sprintf(
            'Checked: %d | OK: %d | Mismatched: %d | Mixed: %d | Unverifiable: %d | File missing: %d | No invoice number: %d%s',
            $checked, $ok, $mismatched, $mixed, $unverifiable, $fileMissing, $noInvoiceNumber,
            $delete ? " | Deleted: {$deleted}" : ''
        ));

        if (!$delete && ($mismatched > 0 || $mixed > 0 || $fileMissing > 0 || $noInvoiceNumber > 0)) {
            $this->warn('This was a dry run — nothing was deleted. Review the CSV above, then re-run with --delete to remove the confirmed bad rows.');
        }

        return self::SUCCESS;
    }

    private function deleteInvoice(Invoice $invoice, string $reason): void
    {
        if (Storage::disk('local')->exists($invoice->pdf_path)) {
            Storage::disk('local')->delete($invoice->pdf_path);
        }

        ActivityLog::create([
            'user_id'     => null,
            'user_name'   => 'system (invoices:verify-customers)',
            'action'      => 'delete',
            'module'      => 'Invoices',
            'description' => "Auto-deleted invoice #{$invoice->id} for {$invoice->customer_code} ({$invoice->customer_name}) — {$reason}",
            'ip_address'  => 'cli',
        ]);

        $invoice->delete();
    }
}
