<?php

namespace App\Http\Controllers;

use App\Models\Builty;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Customer-wise document browser: a split-pane file explorer.
 *
 *   /documents               → customer folders (index)
 *   /documents/{customerCode} → explorer shell for one customer — left pane is
 *                               a directory tree (Invoices, latest first, each
 *                               holding its own PDF + attached bilty(s); plus
 *                               a Builties bucket for anything unattached),
 *                               right pane is an iframe that previews
 *                               whatever file leaf was clicked.
 *   /documents/{customerCode}/tree → JSON the left pane fetches to build itself.
 */
class DocumentsController extends Controller
{
    /** Customer name/number for a code — customers table wins, falls back to whatever an invoice/builty row carries. */
    private function resolveCustomer(string $customerCode): object
    {
        $cust = Customer::where('customer_id', $customerCode)->first(['customer_id', 'customer_name', 'customer_number']);
        if ($cust) {
            return (object) ['code' => $customerCode, 'name' => $cust->customer_name, 'number' => $cust->customer_number];
        }

        $invoiceName = Invoice::where('customer_code', $customerCode)->value('customer_name');

        return (object) ['code' => $customerCode, 'name' => $invoiceName ?: $customerCode, 'number' => null];
    }

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

    /** Explorer shell — the tree itself loads via tree() over AJAX. */
    public function customer(string $customerCode)
    {
        return view('admin.documents.customer', [
            'customer' => $this->resolveCustomer($customerCode),
        ]);
    }

    /**
     * JSON tree data for one customer: Invoices (latest first, each carrying
     * its own attached bilty(s) nested inside — invoice number IS the folder
     * name) + a flat Builties bucket for anything not yet linked to an
     * invoice. The left-hand tree in customer.blade.php renders this
     * directly; clicking a file leaf just points the iframe at its open_url.
     */
    public function tree(string $customerCode): JsonResponse
    {
        $builtiesByInvoice = Builty::where('customer_code', $customerCode)
            ->whereNotNull('invoice_id')
            ->with('uploader:id,name')
            ->orderBy('id')
            ->get()
            ->groupBy('invoice_id');

        $invoices = Invoice::where('customer_code', $customerCode)
            ->with('uploader:id,name')
            ->orderByDesc('uploaded_at')
            ->limit(500)
            ->get()
            ->map(function ($inv) use ($builtiesByInvoice) {
                $pdf = $inv->pdf_path ? [
                    'kind'        => 'invoice',
                    'name'        => basename($inv->pdf_path),
                    'open_url'    => url($inv->pdf_path),
                    'size'        => $this->formatSize($inv->pdf_path),
                    'uploaded_at' => optional($inv->uploaded_at)->format('Y-m-d H:i'),
                    'uploaded_by' => $inv->uploader?->name,
                ] : null;

                $builties = $builtiesByInvoice->get($inv->id, collect())->map(fn ($b) => $this->builtyLeaf($b))->values();

                return [
                    'id'                => $inv->id,
                    'label'             => $inv->invoice_number ?: ('Invoice #' . $inv->id),
                    'processing_status' => $inv->processing_status,
                    'amount'            => $inv->total_amount !== null ? ('Rs ' . number_format((float) $inv->total_amount, 0)) : null,
                    'pdf'               => $pdf,
                    'builties'          => $builties,
                ];
            })
            ->values();

        $unattachedBuilties = Builty::where('customer_code', $customerCode)
            ->whereNull('invoice_id')
            ->with('uploader:id,name')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($b) => $this->builtyLeaf($b))
            ->values();

        return response()->json([
            'invoices'             => $invoices,
            'unattached_builties'  => $unattachedBuilties,
        ]);
    }

    private function builtyLeaf(Builty $b): array
    {
        return [
            'kind'          => 'builty',
            'id'            => $b->id,
            'label'         => $b->builty_number,
            'status'        => $b->status,
            'open_url'      => route('builties.file', $b->id),
            'size'          => $this->formatSize($b->file_path),
            'uploaded_at'   => optional($b->created_at)->format('Y-m-d H:i'),
            'uploaded_by'   => $b->uploader?->name,
        ];
    }

    private function formatSize(?string $path): ?string
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->size($path);
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
