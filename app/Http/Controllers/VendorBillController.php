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
 * Vendors AP module — bill upload + two-stage approval (CMD → Director).
 *
 * Flow:
 *   1. Uploader picks a vendor, fills in bill #, amount, optional description,
 *      attaches one or more documents (bill PDF, supporting images) and
 *      submits. Bill lands at status=pending_cmd_approval.
 *   2. CMD opens it from their queue → Approve (status → pending_director_approval)
 *      or Reject with remarks (status → rejected, rejected_by_role='cmd').
 *   3. Director opens it from their queue → Approve (status → approved, terminal)
 *      or Reject with remarks (status → rejected, rejected_by_role='director').
 *   4. On rejection the bill returns to the uploader's queue; they can edit
 *      and re-submit, which flips status back to pending_cmd_approval and
 *      restarts the chain. Every action is recorded in vendor_bill_approvals
 *      so the show page can render a full timeline.
 */
class VendorBillController extends Controller
{
    /**
     * Listing. Default scoping per role:
     *   • Director       → bills awaiting director approval.
     *   • CMD            → bills awaiting CMD approval.
     *   • Anyone else    → bills they uploaded themselves.
     *   • Admin          → everything; can override scope via ?queue=all|mine|cmd|director|approved|rejected.
     */
    public function index(Request $request)
    {
        $user  = auth()->user();
        $queue = $request->input('queue');

        $q = VendorBill::query()
            ->with(['vendor:id,vendor_code,vendor_name', 'uploader:id,name'])
            ->orderByDesc('id');

        // Free-text search across bill number, vendor name, vendor code.
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
            // Admin: honor explicit queue filter if any, otherwise show all.
            $this->applyQueueScope($q, $queue, $user);
        } elseif ($user->isDirector()) {
            $this->applyQueueScope($q, $queue ?: 'director', $user);
        } elseif ($user->isCmd()) {
            $this->applyQueueScope($q, $queue ?: 'cmd', $user);
        } else {
            // Everyone else only sees their own bills.
            $q->where('uploaded_by', $user->id);
        }

        $bills = $q->paginate(20)->withQueryString();

        // Counts driving the small tab badges in the header.
        $counts = [
            'mine'     => VendorBill::where('uploaded_by', $user->id)->count(),
            'cmd'      => VendorBill::where('status', VendorBill::STATUS_PENDING_CMD)->count(),
            'director' => VendorBill::where('status', VendorBill::STATUS_PENDING_DIRECTOR)->count(),
            'approved' => VendorBill::where('status', VendorBill::STATUS_APPROVED)->count(),
            'rejected' => VendorBill::where('status', VendorBill::STATUS_REJECTED)->count(),
        ];

        return view('admin.vendor-bills.index', [
            'bills'        => $bills,
            'counts'       => $counts,
            'activeQueue'  => $queue ?: ($user->isDirector() ? 'director' : ($user->isCmd() ? 'cmd' : 'mine')),
            'search'       => $request->input('search'),
            'statusFilter' => $status,
        ]);
    }

    /**
     * Bill form: standalone create OR edit-after-rejection (same view).
     */
    public function create()
    {
        return view('admin.vendor-bills.form', [
            'bill'    => null,
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('vendor_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
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
                'status'       => VendorBill::STATUS_PENDING_CMD,
                'uploaded_by'  => auth()->id(),
            ]);

            $this->storeAttachments($bill, $request->file('attachments') ?? []);

            $this->logAction($bill, 'submitted', 'submitted', null);

            return $bill;
        });

        notify('Bill submitted — now awaiting CMD approval.', 'success');
        return redirect()->route('vendor-bills.show', $bill);
    }

    public function show(VendorBill $vendorBill)
    {
        $this->authorizeView($vendorBill);

        $vendorBill->load(['vendor', 'uploader', 'cmdApprover', 'directorApprover',
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
     * Update + automatic resubmission. Only the uploader may update, and only
     * when the bill is in 'rejected' or 'draft' state. After update, status
     * flips back to pending_cmd_approval and the chain restarts.
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
                'vendor_id'         => $data['vendor_id'],
                'bill_number'       => trim($data['bill_number']),
                'bill_date'         => $data['bill_date'] ?? null,
                'amount'            => $data['amount'],
                'currency'          => 'PKR',
                'description'       => $data['description'] ?? null,
                'status'            => VendorBill::STATUS_PENDING_CMD,
                'rejected_by_role'  => null,
                // Clear prior approvals on re-submission so the new chain
                // starts fresh — but the audit log preserves them.
                'cmd_approved_by'      => null,
                'cmd_approved_at'      => null,
                'director_approved_by' => null,
                'director_approved_at' => null,
            ]);

            // Drop attachments the user marked for removal, then add any new
            // ones uploaded with this update.
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

        notify('Bill updated and resubmitted — back in CMD queue.', 'success');
        return redirect()->route('vendor-bills.show', $vendorBill);
    }

    /**
     * Approve action. CMD's approve moves the bill to director queue;
     * director's approve marks it terminal-approved.
     */
    public function approve(Request $request, VendorBill $vendorBill)
    {
        $data = $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);
        $user = auth()->user();

        if ($vendorBill->status === VendorBill::STATUS_PENDING_CMD) {
            abort_unless($user->isCmd() || $user->isAdmin(), 403);
            $vendorBill->update([
                'cmd_approved_by' => $user->id,
                'cmd_approved_at' => now(),
                'status'          => VendorBill::STATUS_PENDING_DIRECTOR,
                'rejected_by_role' => null,
            ]);
            $this->logAction($vendorBill, 'cmd', 'approved', $data['remarks'] ?? null);
            notify('Approved — forwarded to Director.', 'success');
        } elseif ($vendorBill->status === VendorBill::STATUS_PENDING_DIRECTOR) {
            abort_unless($user->isDirector() || $user->isAdmin(), 403);
            $vendorBill->update([
                'director_approved_by' => $user->id,
                'director_approved_at' => now(),
                'status'               => VendorBill::STATUS_APPROVED,
                'rejected_by_role'     => null,
            ]);
            $this->logAction($vendorBill, 'director', 'approved', $data['remarks'] ?? null);
            notify('Approved — bill is now fully approved.', 'success');
        } else {
            notify('Bill is not awaiting your approval.', 'danger');
        }

        return back();
    }

    /**
     * Reject action — requires remarks. Sends the bill back to the uploader's
     * queue for edit + resubmit.
     */
    public function reject(Request $request, VendorBill $vendorBill)
    {
        $data = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);
        $user = auth()->user();

        if (!in_array($vendorBill->status, [
            VendorBill::STATUS_PENDING_CMD,
            VendorBill::STATUS_PENDING_DIRECTOR,
        ], true)) {
            notify('Bill is not in a rejectable state.', 'danger');
            return back();
        }

        // Permission: CMD can only reject CMD-pending, Director only director-pending.
        $isCmdStage      = $vendorBill->status === VendorBill::STATUS_PENDING_CMD;
        $isDirectorStage = $vendorBill->status === VendorBill::STATUS_PENDING_DIRECTOR;
        if ($isCmdStage && !($user->isCmd() || $user->isAdmin())) abort(403);
        if ($isDirectorStage && !($user->isDirector() || $user->isAdmin())) abort(403);

        $vendorBill->update([
            'status'           => VendorBill::STATUS_REJECTED,
            'rejected_by_role' => $isCmdStage ? 'cmd' : 'director',
        ]);

        $this->logAction(
            $vendorBill,
            $isCmdStage ? 'cmd' : 'director',
            'rejected',
            $data['remarks']
        );

        notify('Rejected — bill returned to uploader.', 'warning');
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
            case 'cmd':
                $q->where('status', VendorBill::STATUS_PENDING_CMD);
                break;
            case 'director':
                $q->where('status', VendorBill::STATUS_PENDING_DIRECTOR);
                break;
            case 'approved':
                $q->where('status', VendorBill::STATUS_APPROVED);
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
     * Read access: uploader, CMD, Director, or admin.
     */
    protected function authorizeView(VendorBill $bill): void
    {
        $u = auth()->user();
        if ($u->isAdmin() || $u->isCmd() || $u->isDirector()) return;
        if ((int) $bill->uploaded_by === (int) $u->id) return;
        abort(403);
    }

    /**
     * Edit access: only the uploader (or admin), and only while the bill is
     * editable — i.e. it was rejected or is still a draft. Once it's in a
     * pending-approval state or terminal-approved, the uploader can't modify.
     */
    protected function authorizeEdit(VendorBill $bill): void
    {
        $u = auth()->user();
        $isOwner = (int) $bill->uploaded_by === (int) $u->id;
        if (!$u->isAdmin() && !$isOwner) abort(403);

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
