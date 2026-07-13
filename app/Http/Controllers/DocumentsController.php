<?php

namespace App\Http\Controllers;

use App\Models\Builty;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Customer-wise document browser.
 *
 * The page renders a nested accordion (customer → document type → files),
 * modelled on Odoo / Power BI document explorers. Today it surfaces:
 *   - Invoices (rows in `invoices`, files under invoices/customers/<code>/)
 *   - Builties (rows in `builties`, files under invoices/builties/customers/<code>/)
 *
 * The shape is intentionally generic so a future document type can be added
 * with a single entry in $this->documentTypes() + a fetch closure — the view
 * iterates whatever it's given.
 */
class DocumentsController extends Controller
{
    /**
     * Top-level page. Lists customers that own at least one document of any
     * type, with per-type counts + most-recent upload date. Files themselves
     * are loaded on demand via the `files` JSON endpoint when the user
     * expands the type sub-accordion.
     */
    public function index(Request $request)
    {
        $search       = trim((string) $request->input('search', ''));
        $typeFilter   = $request->input('type'); // null | invoices | builties

        // ── Aggregate per-customer document counts ──
        // We gather counts from each source as a small per-customer subquery
        // then merge — avoids a heavy join + group across millions of rows
        // and keeps the per-source SUMs separable so the UI can show them
        // independently.
        $invoiceCounts = Invoice::query()
            ->whereNotNull('customer_code')
            ->where('customer_code', '!=', '')
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('customer_code', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            }))
            ->select(
                'customer_code',
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('MAX(uploaded_at) as last_invoice_at')
            )
            ->groupBy('customer_code')
            ->get()
            ->keyBy('customer_code');

        $builtyCounts = Builty::query()
            ->whereNotNull('customer_code')
            ->where('customer_code', '!=', '')
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('customer_code', 'like', "%{$search}%");
            }))
            ->select(
                'customer_code',
                DB::raw('COUNT(*) as builty_count'),
                DB::raw('MAX(created_at) as last_builty_at')
            )
            ->groupBy('customer_code')
            ->get()
            ->keyBy('customer_code');

        // Build the unified customer set.
        $codes = $invoiceCounts->keys()
            ->merge($builtyCounts->keys())
            ->unique()
            ->values();

        // If a type filter is active, drop customers that have none of that
        // type so the list stays focused on what the user asked for.
        if ($typeFilter === 'invoices') {
            $codes = $codes->filter(fn ($c) => isset($invoiceCounts[$c]));
        } elseif ($typeFilter === 'builties') {
            $codes = $codes->filter(fn ($c) => isset($builtyCounts[$c]));
        }

        // Pull customer names from the customers table for any code that's
        // there (so we get the official name even if no invoice row carries
        // one). Falls back to the invoice's stored customer_name.
        $customerRows = Customer::query()
            ->whereIn('customer_id', $codes->all())
            ->get(['customer_id', 'customer_name', 'customer_number'])
            ->keyBy('customer_id');

        // Compose the final row set with all the per-customer metadata the
        // accordion header needs.
        $rows = $codes->map(function ($code) use ($invoiceCounts, $builtyCounts, $customerRows) {
            $inv = $invoiceCounts->get($code);
            $blt = $builtyCounts->get($code);
            $cust = $customerRows->get($code);

            $lastInvoiceAt = $inv ? Carbon::parse($inv->last_invoice_at) : null;
            $lastBuiltyAt  = $blt ? Carbon::parse($blt->last_builty_at)  : null;

            $latest = collect([$lastInvoiceAt, $lastBuiltyAt])->filter()->max();

            return (object) [
                'customer_code'   => $code,
                'customer_name'   => $cust?->customer_name ?? $inv?->customer_name ?? '—',
                'customer_number' => $cust?->customer_number,
                'invoice_count'   => (int) ($inv?->invoice_count ?? 0),
                'builty_count'    => (int) ($blt?->builty_count ?? 0),
                'document_count'  => (int) (($inv?->invoice_count ?? 0) + ($blt?->builty_count ?? 0)),
                'last_invoice_at' => $lastInvoiceAt,
                'last_builty_at'  => $lastBuiltyAt,
                'last_at'         => $latest,
            ];
        });

        // Sort by most recently active customer first — the user almost
        // always wants to see today's uploads on top.
        $rows = $rows->sortByDesc(fn ($r) => $r->last_at?->timestamp ?? 0)->values();

        // Manual pagination (we're working with a Collection because the
        // aggregate set is small enough to materialize fully).
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $total   = $rows->count();
        $slice   = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $customersPage = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $totals = [
            'customers' => $rows->count(),
            'invoices'  => (int) $invoiceCounts->sum('invoice_count'),
            'builties'  => (int) $builtyCounts->sum('builty_count'),
        ];

        return view('admin.documents.index', [
            'customersPage' => $customersPage,
            'totals'        => $totals,
            'search'        => $search,
            'typeFilter'    => $typeFilter,
        ]);
    }

    /**
     * AJAX endpoint: returns EVERY file (invoices + builties) for a customer,
     * unified into one chronologically-sorted list. Each row carries a
     * `type` field ('invoice' or 'builty') so the UI can render a single
     * flat table instead of two stacked sub-accordions. Invoice rows also
     * carry `has_builty` so the UI can surface a "Has Builty" column without
     * a second query.
     */
    public function files(Request $request, string $customerCode): JsonResponse
    {
        // Builty → invoice attachment lookup (single query). The Builty row
        // is the canonical truth: any builty whose invoice_id matches is a
        // signal that the invoice has at least one attached cheque/lr image.
        $invoiceIdsWithBuilty = Builty::query()
            ->where('customer_code', $customerCode)
            ->whereNotNull('invoice_id')
            ->pluck('invoice_id')
            ->unique()
            ->all();

        // ── Invoices ──
        $invoiceRows = Invoice::query()
            ->with('uploader:id,name')
            ->where('customer_code', $customerCode)
            ->orderByDesc('uploaded_at')
            ->limit(500)
            ->get();

        $invoices = $invoiceRows->map(function ($r) use ($invoiceIdsWithBuilty) {
            $path = $r->pdf_path;
            return [
                'type'       => 'invoice',
                'id'         => $r->id,
                'name'       => basename($path ?: ($r->original_filename ?? 'invoice.pdf')),
                'label'      => $r->invoice_number ?: ('Invoice #' . $r->id),
                'sublabel'   => $r->original_filename,
                'path'       => $path,
                'open_url'   => $path ? url($path) : null,
                'detail_url' => route('invoices.show', $r->id),
                'size_bytes' => $path && Storage::disk('local')->exists($path)
                    ? Storage::disk('local')->size($path)
                    : null,
                'uploaded_by' => $r->uploader?->name,
                'uploaded_at' => optional($r->uploaded_at)->format('Y-m-d H:i'),
                'uploaded_ts' => optional($r->uploaded_at)->timestamp ?? 0,
                'badge'       => $r->processing_status,
                'has_builty'  => in_array($r->id, $invoiceIdsWithBuilty, true),
                // The Documents grid only surfaces the invoice amount in its
                // own column now (page_range was dropped at the user's
                // request) — value is null when the extractor couldn't
                // resolve a total so the UI can render an em-dash.
                'amount' => $r->total_amount !== null
                    ? ('Rs ' . number_format((float) $r->total_amount, 0))
                    : null,
            ];
        });

        // ── Builties ──
        $builtyRows = Builty::query()
            ->with('uploader:id,name', 'order:id,order_number', 'invoice:id,invoice_number')
            ->where('customer_code', $customerCode)
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $builties = $builtyRows->map(function ($r) {
            $path = $r->file_path;
            return [
                'type'       => 'builty',
                'id'         => $r->id,
                'name'       => basename($path ?: ($r->original_filename ?? 'builty.pdf')),
                'label'      => $r->builty_number,
                'sublabel'   => $r->original_filename,
                'path'       => $path,
                'open_url'   => route('builties.file', $r->id),
                'detail_url' => null,
                'size_bytes' => $path && Storage::disk('local')->exists($path)
                    ? Storage::disk('local')->size($path)
                    : null,
                'uploaded_by' => $r->uploader?->name,
                'uploaded_at' => optional($r->created_at)->format('Y-m-d H:i'),
                'uploaded_ts' => optional($r->created_at)->timestamp ?? 0,
                'badge'       => $r->invoice_id ? 'merged' : 'unattached',
                'has_builty'  => null, // not applicable for builty rows
                // Builties don't carry a monetary amount — the Documents grid
                // shows an em-dash in the Amount column for these rows.
                'amount'      => null,
            ];
        });

        // Merge + sort by upload time, newest first.
        $files = $invoices->concat($builties)
            ->sortByDesc('uploaded_ts')
            ->values();

        return response()->json([
            'customer_code' => $customerCode,
            'count'         => $files->count(),
            'invoice_count' => $invoices->count(),
            'builty_count'  => $builties->count(),
            'files'         => $files,
        ]);
    }
}
