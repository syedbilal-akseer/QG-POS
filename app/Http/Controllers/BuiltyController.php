<?php

namespace App\Http\Controllers;

use App\Models\Builty;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BuiltyController extends Controller
{
    /**
     * Show the builty management page.
     */
    public function index(Request $request)
    {
        $q = Builty::query()->with(['order:id,order_number,customer_id', 'invoice:id,invoice_number,customer_name', 'uploader:id,name']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $q->where(function ($inner) use ($search) {
                $inner->where('builty_number', 'like', "%{$search}%")
                      ->orWhere('original_filename', 'like', "%{$search}%")
                      ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                      ->orWhereHas('invoice', fn ($iq) => $iq->where('invoice_number', 'like', "%{$search}%"));
            });
        }

        $statusFilter = $request->input('status');
        if (in_array($statusFilter, ['sent_to_accounts', 'submitted'], true)) {
            $q->where('status', $statusFilter);
        }

        $builties = $q->orderByDesc('id')->paginate(50)->withQueryString();

        return view('admin.builties.index', compact('builties', 'statusFilter'));
    }

    /**
     * Add a new builty. The user fills builty_number, picks an order_id
     * (required) and optionally an invoice_id, and uploads a PDF/PNG/JPG/JPEG.
     * Non-PDF files are converted to a single-page A4 PDF before storage.
     * When invoice_id is set we also merge the resulting builty PDF into the
     * invoice's stored PDF using the same flow as
     * App\Http\Controllers\Api\InvoiceApiController::uploadBuilty so the
     * "Send Invoices" / WhatsApp pipelines pick up the merged file unchanged.
     */
    public function store(Request $request)
    {
        $request->validate([
            // builty_number is now auto-generated server-side
            // (Builty::booted) so the modal no longer collects it.
            'builty_number' => 'nullable|string|max:64',
            // Order/Customer dropped — Add Bilty now only associates a
            // bilty with an invoice; customer_code is derived from that
            // invoice in processSingleBuilty().
            'invoice_id'    => 'required|integer|exists:invoices,id',
            'file'          => 'required|file|mimes:pdf,png,jpg,jpeg|max:25600',
        ]);

        $result = $this->processSingleBuilty(
            $request->file('file'),
            trim((string) $request->input('builty_number', '')),
            null,
            (int) $request->input('invoice_id'),
            null
        );

        notify($result['merged']
            ? 'Builty saved and merged into the invoice PDF.'
            : 'Builty saved.', 'success');

        return redirect()->route('builties.index')->with('status', 'created');
    }

    /**
     * Bulk upload up to 200 builty files in a single request. Per-row
     * metadata is sent as a `metadata[]` array where each entry has
     * builty_number / invoice_id, aligned positionally with `files[]`. Each
     * file is processed independently — a single failure doesn't abort the
     * batch; per-row outcomes are reported back in the response payload.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'files'                    => 'required|array|min:1|max:200',
            'files.*'                  => 'file|mimes:pdf,png,jpg,jpeg|max:25600',
            'metadata'                 => 'required|array|min:1',
            // builty_number is now auto-generated server-side
            // (Builty::booted) so the modal no longer collects it.
            'metadata.*.builty_number' => 'nullable|string|max:64',
            // Order/Customer dropped — Add Bilty now only associates a
            // bilty with an invoice; customer_code is derived from that
            // invoice in processSingleBuilty().
            'metadata.*.invoice_id'    => 'required|integer|exists:invoices,id',
        ]);

        $files    = $request->file('files');
        $metadata = $request->input('metadata');

        if (count($files) !== count($metadata)) {
            return response()->json([
                'success' => false,
                'message' => 'Files / metadata length mismatch.',
            ], 422);
        }

        $created = 0;
        $merged  = 0;
        $errors  = [];

        foreach ($files as $idx => $file) {
            $meta = $metadata[$idx] ?? [];
            try {
                $r = $this->processSingleBuilty(
                    $file,
                    trim((string) ($meta['builty_number'] ?? '')),
                    null,
                    (int) $meta['invoice_id'],
                    null
                );
                $created++;
                if ($r['merged']) $merged++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'index'    => $idx,
                    'filename' => $file->getClientOriginalName(),
                    'error'    => $e->getMessage(),
                ];
                Log::error('Bulk builty upload row failed', [
                    'index'    => $idx,
                    'filename' => $file->getClientOriginalName(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $msg = "Saved {$created} builty(s)"
            . ($merged > 0 ? ", merged {$merged} into invoice PDFs" : '')
            . (count($errors) > 0 ? ', ' . count($errors) . ' failed' : '')
            . '.';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'created' => $created,
                'merged'  => $merged,
                'errors'  => $errors,
                'message' => $msg,
            ]);
        }

        notify($msg, count($errors) > 0 ? 'warning' : 'success');
        return redirect()->route('builties.index')->with('status', 'created');
    }

    /**
     * Supply-chain's minimal upload form — file(s) only, no order/customer/
     * invoice picking (they usually don't have that context on hand; accounts
     * fills it in later via markSubmitted()). Deliberately a separate, much
     * simpler page from the accounts-facing Add Bilty modal.
     */
    public function quickUploadForm()
    {
        return view('admin.builties.quick-upload');
    }

    /**
     * Handles the quick-upload form. Every file becomes its own Builty row
     * with order_id/invoice_id/customer_code left null and
     * status=sent_to_accounts — it just sits in the accounts review queue
     * until someone completes it via markSubmitted().
     */
    public function quickStore(Request $request)
    {
        $request->validate([
            'files'   => 'required|array|min:1|max:50',
            'files.*' => 'file|mimes:pdf,png,jpg,jpeg|max:25600',
        ]);

        $created = 0;
        $errors  = [];

        foreach ($request->file('files') as $idx => $file) {
            try {
                $this->processSingleBuilty($file, '', null, null, null, 'sent_to_accounts');
                $created++;
            } catch (\Throwable $e) {
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                Log::error('Quick builty upload row failed', [
                    'index'    => $idx,
                    'filename' => $file->getClientOriginalName(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $msg = "Sent {$created} bilty(s) to accounts"
            . (count($errors) > 0 ? ', ' . count($errors) . ' failed' : '')
            . '.';
        notify($msg, count($errors) > 0 ? 'warning' : 'success');

        return redirect()->route('builties.quickUpload');
    }

    /**
     * Accounts completes a supply-chain-uploaded builty: fills in the
     * order/customer/invoice it belongs to, flips status to submitted, and
     * (when an invoice was picked) triggers the same merge-into-invoice-PDF
     * flow as attachToInvoice(). This is the "accounts confirms receipt" step
     * the user asked for — the audit trail is submitted_by/submitted_at.
     */
    public function markSubmitted(Request $request, Builty $builty)
    {
        $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
        ]);

        $invoice = Invoice::findOrFail($request->input('invoice_id'));

        // customer_code is derived from the invoice, not picked separately —
        // the whole point of this step is "attach to an invoice"; the
        // customer just comes along for free from that invoice.
        $builty->update([
            'invoice_id'    => $invoice->id,
            'customer_code' => $invoice->customer_code ?: $builty->customer_code,
            'status'        => 'submitted',
            'submitted_by'  => auth()->id(),
            'submitted_at'  => now(),
        ]);

        // Re-merge from ALL builties already linked to this invoice, not just
        // the one just submitted — mergeBuiltiesIntoInvoice always rebuilds
        // from the pristine original_pdf_path, so passing only the new file
        // would silently drop any builty(s) a previous attach already merged in.
        $allPaths = Builty::where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();
        $mergeResult = $this->mergeBuiltiesIntoInvoice($invoice, $allPaths);

        if ($mergeResult === 'no_invoice_pdf') {
            notify('Bilty submitted, but the invoice has no PDF on file to merge with.', 'warning');
        } else {
            notify('Bilty submitted and attached to the invoice.', 'success');
        }

        return back();
    }

    /**
     * Shared per-builty processing used by store() AND bulkStore(). Wraps the
     * image→PDF conversion, customer-folder resolution, Builty row creation,
     * and optional invoice-merge in one path.
     *
     * Returns ['builty' => Builty, 'merged' => bool].
     */
    protected function processSingleBuilty(
        \Illuminate\Http\UploadedFile $file,
        string $builtyNum,
        ?int $orderId,
        ?int $invoiceId,
        ?string $customerCode,
        string $status = 'submitted'
    ): array {
        $ext   = strtolower($file->getClientOriginalExtension());
        $isPdf = $ext === 'pdf';

        // Customer fallback: derive from the selected invoice when the
        // uploader didn't pick one explicitly. The invoice carries the
        // authoritative customer_code from the extractor.
        if (!$customerCode && $invoiceId) {
            $customerCode = \App\Models\Invoice::whereKey($invoiceId)->value('customer_code') ?: null;
        }

        // Storage path:
        //   • Customer known → invoices/customers/<code>/ (SAME folder as
        //     that customer's invoice PDFs — easier document management,
        //     mirrors what the Documents page now expects).
        //   • Customer unknown → invoices/builties/ catch-all.
        // The `_builty_` infix in the filename keeps builties visually
        // distinguishable from invoice PDFs sharing the same folder.
        //
        // The filename is NOT derived from $builtyNum because that value is
        // auto-generated by Builty::booted AFTER the row is inserted; we'd
        // have to round-trip a rename to get it in. Instead we use the
        // order id + timestamp + a short random suffix, which is unique and
        // diagnosable; the official BLT-YYYY-N identifier lives only on the
        // Builty row.
        $stamp       = now()->format('Y-m-d_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $baseName    = sprintf('builty_order_%d_%s', $orderId, $stamp);
        $storageDir  = $customerCode
            ? 'invoices/customers/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $customerCode)
            : 'invoices/builties';
        $storedPath  = $storageDir . '/' . $baseName . '.pdf';

        Storage::disk('local')->makeDirectory($storageDir);

        if ($isPdf) {
            Storage::disk('local')->put($storedPath, file_get_contents($file->getPathname()));
        } else {
            $this->wrapImageAsPdf($file->getPathname(), $ext, $storedPath);
        }

        // Empty string is filtered out so Builty::booted auto-generates the
        // BLT-YYYY-N identifier. Explicit non-empty values still win, in case
        // an integration ever needs to pin a specific number.
        $builty = Builty::create([
            'builty_number'     => $builtyNum !== '' ? $builtyNum : null,
            'order_id'          => $orderId,
            'invoice_id'        => $invoiceId,
            'customer_code'     => $customerCode,
            'file_path'         => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'original_ext'      => $ext,
            'uploaded_by'       => auth()->id(),
            'status'            => $status,
            'submitted_by'      => $status === 'submitted' ? auth()->id() : null,
            'submitted_at'      => $status === 'submitted' ? now() : null,
        ]);

        $merged = false;
        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice && $this->mergeBuiltyIntoInvoice($invoice, $storedPath) === 'merged') {
                $merged = true;
            }
        }

        return ['builty' => $builty, 'merged' => $merged];
    }

    /**
     * Wrap an image (PNG/JPG/JPEG) as a single-page A4 PDF written to
     * $outStoragePath on the local disk. TCPDF auto-scales the image to fit
     * the page while preserving aspect ratio.
     */
    protected function wrapImageAsPdf(string $sourceImagePath, string $ext, string $outStoragePath): void
    {
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->Image(
            $sourceImagePath,
            0, 0,
            $pdf->getPageWidth(), $pdf->getPageHeight(),
            strtoupper($ext === 'jpeg' ? 'JPG' : $ext),
            '', '', false, 300, '',
            false, false, 0, 'CM', false, false
        );

        $tmp = tempnam(sys_get_temp_dir(), 'builty_img_') . '.pdf';
        $pdf->Output($tmp, 'F');
        Storage::disk('local')->put($outStoragePath, file_get_contents($tmp));
        @unlink($tmp);
    }

    /**
     * Stream a stored builty file. Path-traversal protected because we read
     * from the DB row, not from the request.
     */
    public function file(Builty $builty): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($builty->file_path), 404);
        return response()->file(Storage::disk('local')->path($builty->file_path));
    }

    public function destroy(Builty $builty)
    {
        if ($builty->file_path && Storage::disk('local')->exists($builty->file_path)) {
            try { Storage::disk('local')->delete($builty->file_path); }
            catch (\Throwable $e) { Log::warning('Could not delete builty file', ['id' => $builty->id, 'path' => $builty->file_path, 'error' => $e->getMessage()]); }
        }
        $builty->delete();
        notify('Builty deleted.', 'success');
        return back();
    }

    /**
     * Searchable orders dropdown (JSON). Used by the Add Builty modal.
     * Matches order_number with a LIKE prefix; returns at most 25 rows.
     */
    public function searchOrders(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $rows = Order::query()
            ->with('customer:id,customer_id,customer_name')
            ->when($q !== '', fn ($qq) => $qq->where('order_number', 'like', '%' . $q . '%'))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'order_number', 'customer_id'])
            ->map(fn ($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'customer'     => $o->customer?->customer_name,
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Searchable invoices dropdown (JSON). invoices.invoice_number can hold
     * a comma-separated list of Oracle invoice numbers, so we LIKE-search
     * across the whole field.
     */
    public function searchInvoices(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $rows = Invoice::query()
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('invoice_number', 'like', '%' . $q . '%')
                  ->orWhere('customer_code', 'like', '%' . $q . '%')
                  ->orWhere('customer_name', 'like', '%' . $q . '%');
            }))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'invoice_number', 'customer_code', 'customer_name'])
            ->map(fn ($i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'customer'       => trim(($i->customer_code ?: '') . ' — ' . ($i->customer_name ?: ''), ' —'),
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Returns the next BLT-YYYY-N sequence the model would assign on save.
     * Used by the Add Builty modal to preview, per-row, the builty numbers
     * that will be generated when the user submits. Strictly informational —
     * the actual number is assigned in Builty::booted under a row lock, so a
     * concurrent upload can still shift these by a few.
     */
    public function nextNumberPreview(): JsonResponse
    {
        $year   = now()->format('Y');
        $prefix = "BLT-{$year}-";

        $last = \App\Models\Builty::query()
            ->where('builty_number', 'LIKE', $prefix . '%')
            ->orderByDesc('id')
            ->value('builty_number');

        $nextSeq = 1;
        if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        return response()->json([
            'year'     => $year,
            'prefix'   => $prefix,
            'next_seq' => $nextSeq,
        ]);
    }

    /**
     * Searchable customers dropdown (JSON). Powers the Customer picker on the
     * Add Builty modal. Returns customers with non-empty customer_id so the
     * resulting builty can be linked into the invoice auto-attach pipeline.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $rows = \App\Models\Customer::query()
            ->whereNotNull('customer_id')
            ->whereNotNull('customer_name')
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('customer_id', 'like', '%' . $q . '%')
                  ->orWhere('customer_number', 'like', '%' . $q . '%')
                  ->orWhere('customer_name', 'like', '%' . $q . '%');
            }))
            ->orderBy('customer_name')
            ->limit(25)
            ->get(['id', 'customer_id', 'customer_number', 'customer_name'])
            ->map(fn ($c) => [
                'id'              => $c->customer_id, // value used as builties.customer_code
                'customer_id'     => $c->customer_id,
                'customer_number' => $c->customer_number,
                'customer_name'   => $c->customer_name,
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Searchable builties dropdown (JSON). Powers the "Attach Builty later"
     * modal opened from a row on the Invoices page.
     */
    public function searchBuilties(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $rows = Builty::query()
            ->with('order:id,order_number')
            ->when($q !== '', fn ($qq) => $qq->where('builty_number', 'like', '%' . $q . '%'))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'builty_number', 'order_id'])
            ->map(fn ($b) => [
                'id'            => $b->id,
                'builty_number' => $b->builty_number,
                'order_number'  => $b->order?->order_number,
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Attach an existing Builty to an Invoice (the "attach builty later"
     * flow from the Invoices page). Re-merges the invoice PDF with the
     * builty's PDF using the same logic as the initial-upload path.
     */
    public function attachToInvoice(Request $request, Invoice $invoice)
    {
        $request->validate([
            'builty_id' => 'required|integer|exists:builties,id',
        ]);

        $builty = Builty::findOrFail($request->input('builty_id'));

        // Keep the builty linked to the invoice so the invoice page can
        // show it next to the row.
        $builty->update(['invoice_id' => $invoice->id]);

        $mergeResult = $this->mergeBuiltyIntoInvoice($invoice, $builty->file_path);

        if ($mergeResult === 'no_invoice_pdf') {
            notify("Invoice has no PDF on file to merge with.", 'danger');
            return back();
        }

        notify('Builty attached and merged into the invoice PDF.', 'success');
        return back();
    }

    /**
     * Back-compat single-builty merge — delegates to the multi-builty method.
     */
    protected function mergeBuiltyIntoInvoice(Invoice $invoice, string $builtyStoragePath): string
    {
        return $this->mergeBuiltiesIntoInvoice($invoice, [$builtyStoragePath]);
    }

    /**
     * Re-merges from the un-merged invoice (preserved in original_pdf_path
     * after the first merge) and appends EVERY builty PDF in $builtyStoragePaths
     * in order. Writes the result as *_with_builty.pdf alongside the original
     * and updates the Invoice row. Always rebuilds from the un-merged original
     * — that way calling this with a longer list of builties later doesn't
     * stack on top of an already-merged file (no double-attachment).
     *
     * Returns 'merged' on success, 'no_invoice_pdf' when the invoice has no
     * PDF on file to merge against, 'no_builties' when $builtyStoragePaths is
     * empty.
     */
    protected function mergeBuiltiesIntoInvoice(Invoice $invoice, array $builtyStoragePaths): string
    {
        if (empty($builtyStoragePaths)) {
            return 'no_builties';
        }

        $sourceInvoicePath = $invoice->original_pdf_path ?: $invoice->pdf_path;
        if (!$sourceInvoicePath || !Storage::disk('local')->exists($sourceInvoicePath)) {
            return 'no_invoice_pdf';
        }

        // Drop any builty whose file is missing on disk so a single missing
        // path doesn't take the whole merge down.
        $builtyStoragePaths = array_values(array_filter(
            $builtyStoragePaths,
            fn ($p) => $p && Storage::disk('local')->exists($p)
        ));
        if (empty($builtyStoragePaths)) {
            return 'no_builties';
        }

        $tempMergedFile = null;
        try {
            $pdf = new \setasign\Fpdi\Fpdi();

            $invoicePages = $pdf->setSourceFile(Storage::disk('local')->path($sourceInvoicePath));
            for ($i = 1; $i <= $invoicePages; $i++) {
                $tpl  = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }

            $totalBuiltyPages = 0;
            foreach ($builtyStoragePaths as $builtyPath) {
                $builtyPages = $pdf->setSourceFile(Storage::disk('local')->path($builtyPath));
                $totalBuiltyPages += $builtyPages;
                for ($i = 1; $i <= $builtyPages; $i++) {
                    $tpl  = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
            }

            $tempMergedFile = tempnam(sys_get_temp_dir(), 'inv_builty_') . '.pdf';
            $pdf->Output($tempMergedFile, 'F');

            $originalDir       = dirname($sourceInvoicePath);
            $originalBase      = pathinfo($sourceInvoicePath, PATHINFO_FILENAME);
            $mergedStoragePath = $originalDir . '/' . $originalBase . '_with_builty.pdf';
            Storage::disk('local')->put($mergedStoragePath, file_get_contents($tempMergedFile));
            @unlink($tempMergedFile);
            $tempMergedFile = null;

            // Clean up the previous merged file if any.
            if ($invoice->original_pdf_path
                && $invoice->pdf_path
                && $invoice->pdf_path !== $invoice->original_pdf_path
                && $invoice->pdf_path !== $mergedStoragePath
                && Storage::disk('local')->exists($invoice->pdf_path)) {
                try { Storage::disk('local')->delete($invoice->pdf_path); }
                catch (\Throwable $e) { Log::warning('Could not delete previous merged file', ['path' => $invoice->pdf_path, 'error' => $e->getMessage()]); }
            }

            // builty_path stores the LAST builty we merged — kept for backward
            // compatibility with the existing /api/invoices/upload-builty
            // single-cheque flow. The Builty rows themselves remain the
            // canonical list of every cheque attached.
            $updates = [
                'pdf_path'           => $mergedStoragePath,
                'builty_path'        => end($builtyStoragePaths),
                'builty_uploaded_at' => now(),
                'builty_uploaded_by' => auth()->id(),
            ];
            if (empty($invoice->original_pdf_path)) {
                $updates['original_pdf_path'] = $sourceInvoicePath;
            }
            $invoice->update($updates);

            Log::info('Builty(s) merged into invoice PDF (BuiltyController)', [
                'invoice_id'       => $invoice->id,
                'invoice_number'   => $invoice->invoice_number,
                'builty_count'     => count($builtyStoragePaths),
                'builty_paths'     => $builtyStoragePaths,
                'merged_pdf_path'  => $mergedStoragePath,
                'invoice_pages'    => $invoicePages,
                'builty_pages'     => $totalBuiltyPages,
                'uploaded_by'      => auth()->id(),
            ]);

            return 'merged';
        } catch (\Throwable $e) {
            if ($tempMergedFile && file_exists($tempMergedFile)) { @unlink($tempMergedFile); }
            Log::error('Failed to merge builty/builties into invoice PDF', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Called from InvoiceController::separateCustomerInvoices right after a
     * fresh invoice row is committed. Looks for any unmerged builties already
     * linked to that customer_code (uploaded ahead of the invoice arriving),
     * merges them ALL into the new invoice's PDF in upload order, and links
     * them via invoice_id so the same builties don't reattach on a re-run.
     *
     * Safe to call when no pending builties exist — returns the count merged
     * (zero in that case) and is otherwise a no-op.
     */
    public function autoAttachToInvoice(Invoice $invoice): int
    {
        $code = trim((string) $invoice->customer_code);
        if ($code === '') {
            return 0;
        }

        $pending = Builty::query()
            ->where('customer_code', $code)
            ->whereNull('invoice_id')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $paths = $pending->pluck('file_path')->filter()->values()->all();
        $result = $this->mergeBuiltiesIntoInvoice($invoice, $paths);

        if ($result !== 'merged') {
            Log::warning('Auto-attach builty skipped', [
                'invoice_id'  => $invoice->id,
                'customer'    => $code,
                'pending_ids' => $pending->pluck('id')->all(),
                'reason'      => $result,
            ]);
            return 0;
        }

        // Link each builty to the new invoice so they're not picked up again
        // if a second invoice is uploaded for the same customer later.
        Builty::whereIn('id', $pending->pluck('id'))->update(['invoice_id' => $invoice->id]);

        Log::info('Auto-attached pending builties to new invoice', [
            'invoice_id'   => $invoice->id,
            'customer'     => $code,
            'builty_ids'   => $pending->pluck('id')->all(),
            'builty_count' => $pending->count(),
        ]);

        return $pending->count();
    }
}
