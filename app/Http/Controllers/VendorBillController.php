<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillApproval;
use App\Models\VendorBillAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vendors AP — admin-originated bill request + two-stage approval
 * (Director → CMD), with a manual admin close-out step at the end.
 *
 * Flow:
 *   1. Admin picks a vendor, fills in bill #, amount, optional description,
 *      attaches supporting documents and submits. Bill lands at
 *      status=pending_director_approval.
 *   2. Any Director opens it from their queue → Approve (status →
 *      pending_cmd_approval, cmd_deadline_at = now+24h) or Reject with
 *      remarks (status → rejected, rejected_by_role='director').
 *   3. Any CMD user opens it from their queue → Approve (status → approved,
 *      awaiting admin close-out) or Reject with remarks (status → rejected,
 *      rejected_by_role='cmd'). cmd_deadline_at is purely an informational
 *      24h SLA badge — nothing auto-escalates if it's missed.
 *   4. On rejection the bill returns to Admin's queue (Admin is always the
 *      uploader); they edit and re-submit, which flips status back to
 *      pending_director_approval and restarts the chain.
 *   5. Once approved by both stages, Admin reviews and manually closes the
 *      bill out (status → closed, terminal). Every action is recorded in
 *      vendor_bill_approvals so the show page renders a full history of
 *      approvals/rejections/resubmissions/close-outs.
 */
class VendorBillController extends Controller
{
    /**
     * Listing. Default scoping per role:
     *   • Director  → bills awaiting director approval.
     *   • CMD       → bills awaiting CMD approval.
     *   • Admin     → everything; can override scope via
     *                 ?queue=all|mine|director|cmd|approved|closed|rejected.
     */
    public function index(Request $request)
    {
        $user  = auth()->user();
        $queue = $request->input('queue');

        $q = VendorBill::query()
            ->with(['vendor:id,vendor_code,vendor_name', 'uploader:id,name'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $q->where(function ($w) use ($s) {
                $w->where('bill_number', 'like', "%{$s}%")
                  ->orWhereHas('vendor', fn ($vq) =>
                      $vq->where('vendor_name', 'like', "%{$s}%")
                         ->orWhere('vendor_code', 'like', "%{$s}%")
                  );
            });
        }

        $status = $request->input('status');
        if ($status) {
            $q->where('status', $status);
        }

        if ($user->isAdmin()) {
            $effectiveQueue = $queue ?: 'all';
        } elseif ($user->isDirector()) {
            // Director doesn't create bills and has no business in CMD's
            // queue — restrict to the tabs they're actually shown.
            $allowed = ['director', 'approved', 'closed', 'rejected'];
            $effectiveQueue = in_array($queue, $allowed, true) ? $queue : 'director';
        } elseif ($user->isCmd()) {
            $allowed = ['cmd', 'approved', 'closed', 'rejected'];
            $effectiveQueue = in_array($queue, $allowed, true) ? $queue : 'cmd';
        } else {
            abort(403);
        }
        $this->applyQueueScope($q, $effectiveQueue, $user);

        $bills = $q->paginate(20)->withQueryString();

        $counts = [
            'mine'     => VendorBill::where('uploaded_by', $user->id)->count(),
            'director' => VendorBill::where('status', VendorBill::STATUS_PENDING_DIRECTOR)->count(),
            'cmd'      => VendorBill::where('status', VendorBill::STATUS_PENDING_CMD)->count(),
            'approved' => VendorBill::where('status', VendorBill::STATUS_APPROVED)->count(),
            'closed'   => VendorBill::where('status', VendorBill::STATUS_CLOSED)->count(),
            'rejected' => VendorBill::where('status', VendorBill::STATUS_REJECTED)->count(),
        ];

        // 24h CMD SLA buckets — informational only, drives the sidebar tracker.
        $pendingCmd = VendorBill::where('status', VendorBill::STATUS_PENDING_CMD)
            ->whereNotNull('cmd_deadline_at')
            ->get(['id', 'cmd_deadline_at']);
        $slaBuckets = ['overdue' => 0, 'urgent' => 0, 'dueSoon' => 0, 'onTrack' => 0];
        foreach ($pendingCmd as $b) {
            $hrs = $b->cmdHoursRemaining();
            if ($hrs === null) continue;
            if ($hrs < 0)        $slaBuckets['overdue']++;
            elseif ($hrs < 8)    $slaBuckets['urgent']++;
            elseif ($hrs < 12)   $slaBuckets['dueSoon']++;
            else                 $slaBuckets['onTrack']++;
        }

        $workflowSummary = [
            'created'  => VendorBill::count(),
            'director' => VendorBill::whereNotNull('director_approved_at')->count(),
            'cmd'      => VendorBill::whereNotNull('cmd_approved_at')->count(),
            'closed'   => VendorBill::where('status', VendorBill::STATUS_CLOSED)->count(),
        ];

        return view('admin.vendor-bills.index', [
            'bills'           => $bills,
            'counts'          => $counts,
            'slaBuckets'      => $slaBuckets,
            'workflowSummary' => $workflowSummary,
            'activeQueue'     => $effectiveQueue,
            'search'          => $request->input('search'),
            'statusFilter'    => $status,
        ]);
    }

    /**
     * Bill form: standalone create OR edit-after-rejection (same view).
     * Only Admin may submit bills in this flow.
     */
    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('admin.vendor-bills.form', [
            'bill'    => null,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('vendor_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // Currency is intentionally NOT validated/accepted from the form —
        // every vendor bill is in PKR. The column stays on the table with
        // a 'PKR' default so the data model can absorb other currencies
        // later without a migration.
        $data = $request->validate([
            'vendor_id'    => 'required|integer|exists:vendors,id',
            'bill_number'  => 'required|string|max:64',
            'bill_date'    => 'nullable|date',
            'amount'       => 'required|numeric|min:0',
            'description'  => 'nullable|string|max:2000',
            'attachments'                => 'nullable|array|max:20',
            'attachments.*'              => 'file|mimes:pdf,png,jpg,jpeg|max:25600',
        ]);

        $bill = DB::transaction(function () use ($data, $request) {
            $bill = VendorBill::create([
                'vendor_id'    => $data['vendor_id'],
                'bill_number'  => trim($data['bill_number']),
                'bill_date'    => $data['bill_date'] ?? null,
                'amount'       => $data['amount'],
                'currency'     => 'PKR',
                'description'  => $data['description'] ?? null,
                'status'       => VendorBill::STATUS_PENDING_DIRECTOR,
                'uploaded_by'  => auth()->id(),
            ]);

            $this->storeAttachments($bill, $request->file('attachments') ?? []);

            $this->logAction($bill, 'submitted', 'submitted', null);

            return $bill;
        });

        notify('Bill submitted — now awaiting Director approval.', 'success');
        return redirect()->route('vendor-bills.show', $bill);
    }

    public function show(VendorBill $vendorBill)
    {
        $this->authorizeView($vendorBill);

        $vendorBill->load(['vendor', 'uploader', 'directorApprover', 'cmdApprover', 'closer',
            'attachments.uploader', 'approvals.user']);

        return view('admin.vendor-bills.show', [
            'bill' => $vendorBill,
        ]);
    }

    public function edit(VendorBill $vendorBill)
    {
        $this->authorizeEdit($vendorBill);
        return view('admin.vendor-bills.form', [
            'bill'    => $vendorBill->load('attachments'),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('vendor_name')->get(),
        ]);
    }

    /**
     * Update + automatic resubmission. Only the uploader (Admin) may update,
     * and only when the bill is in 'rejected' or 'draft' state. After update,
     * status flips back to pending_director_approval and the chain restarts.
     */
    public function update(Request $request, VendorBill $vendorBill)
    {
        $this->authorizeEdit($vendorBill);

        $data = $request->validate([
            'vendor_id'                  => 'required|integer|exists:vendors,id',
            'bill_number'                => 'required|string|max:64',
            'bill_date'                  => 'nullable|date',
            'amount'                     => 'required|numeric|min:0',
            'description'                => 'nullable|string|max:2000',
            'attachments'                => 'nullable|array|max:20',
            'attachments.*'              => 'file|mimes:pdf,png,jpg,jpeg|max:25600',
            'remove_attachment_ids'      => 'nullable|array',
            'remove_attachment_ids.*'    => 'integer|exists:vendor_bill_attachments,id',
        ]);

        DB::transaction(function () use ($data, $request, $vendorBill) {
            $vendorBill->update([
                'vendor_id'            => $data['vendor_id'],
                'bill_number'          => trim($data['bill_number']),
                'bill_date'            => $data['bill_date'] ?? null,
                'amount'               => $data['amount'],
                'currency'             => 'PKR',
                'description'          => $data['description'] ?? null,
                'status'               => VendorBill::STATUS_PENDING_DIRECTOR,
                'rejected_by_role'     => null,
                // Clear prior approvals on re-submission so the new chain
                // starts fresh — but the audit log preserves them.
                'director_approved_by' => null,
                'director_approved_at' => null,
                'cmd_approved_by'      => null,
                'cmd_approved_at'      => null,
                'cmd_deadline_at'      => null,
            ]);

            $removeIds = $data['remove_attachment_ids'] ?? [];
            if (!empty($removeIds)) {
                $toRemove = $vendorBill->attachments()->whereIn('id', $removeIds)->get();
                foreach ($toRemove as $att) {
                    if ($att->file_path && Storage::disk('local')->exists($att->file_path)) {
                        try { Storage::disk('local')->delete($att->file_path); }
                        catch (\Throwable $e) {
                            Log::warning('Could not delete vendor bill attachment', [
                                'id' => $att->id, 'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    $att->delete();
                }
            }

            $this->storeAttachments($vendorBill, $request->file('attachments') ?? []);

            $this->logAction($vendorBill, 'resubmitted', 'resubmitted',
                $request->input('remarks'));
        });

        notify('Bill updated and resubmitted — back in Director queue.', 'success');
        return redirect()->route('vendor-bills.show', $vendorBill);
    }

    /**
     * Approve action. Director's approve moves the bill to the CMD queue and
     * starts the 24h SLA clock; CMD's approve marks it approved and hands it
     * back to Admin for manual close-out.
     */
    public function approve(Request $request, VendorBill $vendorBill)
    {
        $data = $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);
        $user = auth()->user();

        if ($vendorBill->status === VendorBill::STATUS_PENDING_DIRECTOR) {
            abort_unless($user->isDirector() || $user->isAdmin(), 403);
            $vendorBill->update([
                'director_approved_by' => $user->id,
                'director_approved_at' => now(),
                'status'               => VendorBill::STATUS_PENDING_CMD,
                'cmd_deadline_at'      => now()->addHours(24),
                'rejected_by_role'     => null,
            ]);
            $this->logAction($vendorBill, 'director', 'approved', $data['remarks'] ?? null);
            notify('Approved — forwarded to CMD (24h SLA started).', 'success');
        } elseif ($vendorBill->status === VendorBill::STATUS_PENDING_CMD) {
            abort_unless($user->isCmd() || $user->isAdmin(), 403);
            $vendorBill->update([
                'cmd_approved_by'  => $user->id,
                'cmd_approved_at'  => now(),
                'status'           => VendorBill::STATUS_APPROVED,
                'rejected_by_role' => null,
            ]);
            $this->logAction($vendorBill, 'cmd', 'approved', $data['remarks'] ?? null);
            notify('Approved — back with Admin for close-out.', 'success');
        } else {
            notify('Bill is not awaiting your approval.', 'danger');
        }

        return back();
    }

    /**
     * Reject action — requires remarks. Sends the bill back to Admin's queue
     * (Admin is always the uploader) for edit + resubmit.
     */
    public function reject(Request $request, VendorBill $vendorBill)
    {
        $data = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);
        $user = auth()->user();

        if (!in_array($vendorBill->status, [
            VendorBill::STATUS_PENDING_DIRECTOR,
            VendorBill::STATUS_PENDING_CMD,
        ], true)) {
            notify('Bill is not in a rejectable state.', 'danger');
            return back();
        }

        $isDirectorStage = $vendorBill->status === VendorBill::STATUS_PENDING_DIRECTOR;
        $isCmdStage      = $vendorBill->status === VendorBill::STATUS_PENDING_CMD;
        if ($isDirectorStage && !($user->isDirector() || $user->isAdmin())) abort(403);
        if ($isCmdStage && !($user->isCmd() || $user->isAdmin())) abort(403);

        $vendorBill->update([
            'status'           => VendorBill::STATUS_REJECTED,
            'rejected_by_role' => $isDirectorStage ? 'director' : 'cmd',
        ]);

        $this->logAction(
            $vendorBill,
            $isDirectorStage ? 'director' : 'cmd',
            'rejected',
            $data['remarks']
        );

        notify('Rejected — bill returned to Admin.', 'warning');
        return back();
    }

    /**
     * Admin-only manual close-out. Only valid once both Director and CMD
     * have approved (status=approved). Terminal — no further action.
     */
    public function close(Request $request, VendorBill $vendorBill)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($vendorBill->status !== VendorBill::STATUS_APPROVED) {
            notify('Bill must be fully approved before it can be closed.', 'danger');
            return back();
        }

        $vendorBill->update([
            'status'    => VendorBill::STATUS_CLOSED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        $this->logAction($vendorBill, 'admin', 'closed', $data['remarks'] ?? null);

        notify('Bill closed out.', 'success');
        return back();
    }

    /** Stream a single attachment file. */
    public function attachment(VendorBillAttachment $attachment)
    {
        $this->authorizeView($attachment->vendorBill);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);
        return response()->file(Storage::disk('local')->path($attachment->file_path));
    }

    /** JSON vendor search for the dropdown (autocomplete). */
    public function searchVendors(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $rows = Vendor::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('vendor_name', 'like', "%{$q}%")
                  ->orWhere('vendor_code', 'like', "%{$q}%");
            }))
            ->orderBy('vendor_name')
            ->limit(25)
            ->get(['id', 'vendor_code', 'vendor_name', 'city']);

        return response()->json(['data' => $rows]);
    }

    // ───────────────────────── helpers ─────────────────────────

    protected function applyQueueScope($q, ?string $queue, $user): void
    {
        switch ($queue) {
            case 'mine':
                $q->where('uploaded_by', $user->id);
                break;
            case 'director':
                $q->where('status', VendorBill::STATUS_PENDING_DIRECTOR);
                break;
            case 'cmd':
                $q->where('status', VendorBill::STATUS_PENDING_CMD);
                break;
            case 'approved':
                $q->where('status', VendorBill::STATUS_APPROVED);
                break;
            case 'closed':
                $q->where('status', VendorBill::STATUS_CLOSED);
                break;
            case 'rejected':
                $q->where('status', VendorBill::STATUS_REJECTED);
                break;
            case 'all':
            case null:
            case '':
                // no extra filter
                break;
        }
    }

    /**
     * Read access: Admin, CMD, or Director. (Uploader is always Admin, so
     * that's already covered by isAdmin().)
     */
    protected function authorizeView(VendorBill $bill): void
    {
        $u = auth()->user();
        if ($u->isAdmin() || $u->isCmd() || $u->isDirector()) return;
        abort(403);
    }

    /**
     * Edit access: Admin only, and only while the bill is editable — i.e.
     * rejected or still a draft.
     */
    protected function authorizeEdit(VendorBill $bill): void
    {
        $u = auth()->user();
        abort_unless($u->isAdmin(), 403);

        if (!in_array($bill->status, [
            VendorBill::STATUS_REJECTED,
            VendorBill::STATUS_DRAFT,
        ], true)) {
            abort(403, 'Bill is not editable in its current state.');
        }
    }

    protected function storeAttachments(VendorBill $bill, array $files): void
    {
        foreach ($files as $file) {
            if (!$file) continue;
            $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $base = sprintf(
                'bill_%d_%s_%s.%s',
                $bill->id,
                now()->format('Y-m-d_His'),
                Str::random(6),
                $ext
            );
            $path = 'vendor-bills/' . $bill->id . '/' . $base;
            Storage::disk('local')->put($path, file_get_contents($file->getPathname()));

            VendorBillAttachment::create([
                'vendor_bill_id'    => $bill->id,
                'file_path'         => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type'         => $file->getClientMimeType(),
                'size_bytes'        => $file->getSize(),
                'uploaded_by'       => auth()->id(),
            ]);
        }
    }

    protected function logAction(VendorBill $bill, string $step, string $action, ?string $remarks): void
    {
        VendorBillApproval::create([
            'vendor_bill_id' => $bill->id,
            'step'           => $step,
            'action'         => $action,
            'remarks'        => $remarks,
            'user_id'        => auth()->id(),
            'acted_at'       => now(),
        ]);
    }
}
