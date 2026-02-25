<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OracleUnreconReceipt;
use App\Models\ReceiptCheque;
use App\Models\ReturnedCheque;
use App\Models\CustomerReceipt;
use App\Notifications\ChequeReturnedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PdcController extends Controller
{
    /**
     * Get PDC summary (Total count and amount).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowedOuIds = $user->getAllowedReceiptOuIds();

        $query = OracleUnreconReceipt::query();
        if (!empty($allowedOuIds)) {
            $query->whereIn('org_id', $allowedOuIds);
        }

        $summary = [
            'count' => (clone $query)->count(),
            'amount' => (clone $query)->sum('receipt_amount'),
        ];
        
        // Breakdown customer wise
        $breakdown = (clone $query)
            ->selectRaw('customer_number, customer_name, COUNT(*) as count, SUM(receipt_amount) as amount')
            ->groupBy('customer_number', 'customer_name')
            ->orderByDesc('amount')
            ->get();
            
        $summary['breakdown'] = $breakdown;

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $summary,
        ]);
    }

    /**
     * Get PDC details (List of unreconciled receipts).
     */
    public function details(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowedOuIds = $user->getAllowedReceiptOuIds();

        $query = OracleUnreconReceipt::query();
        if (!empty($allowedOuIds)) {
            $query->whereIn('org_id', $allowedOuIds);
        }

        // Search by customer number (ID) or receipt number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_number', 'LIKE', "%{$search}%")
                  ->orWhere('receipt_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%");
            });
        }
        
        // Exact filter by customer_number if provided
        if ($request->filled('customer_number')) {
            $query->where('customer_number', $request->customer_number);
        }

        $summaryQuery = clone $query;
        $totalCount = $summaryQuery->count();
        $totalAmount = $summaryQuery->sum('receipt_amount');

        $pdcs = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'PDC details retrieved successfully',
            'total_count' => $totalCount,
            'total_amount' => $totalAmount,
            'data' => $pdcs->items(),
            'pagination' => [
                'total' => $pdcs->total(),
                'count' => $pdcs->count(),
                'per_page' => $pdcs->perPage(),
                'current_page' => $pdcs->currentPage(),
                'total_pages' => $pdcs->lastPage(),
                'next_page_url' => $pdcs->nextPageUrl(),
                'prev_page_url' => $pdcs->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Submit a returned check (Accounts side API).
     */
    public function submitReturn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receipt_cheque_id' => 'required|exists:receipt_cheques,id',
            'reason' => 'required|string|max:1000',
            'image' => 'required|image|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $cheque = ReceiptCheque::with('customerReceipt.createdBy')->findOrFail($request->receipt_cheque_id);

        try {
            return DB::transaction(function () use ($request, $cheque) {
                // Store image
                $imagePath = $request->file('image')->store('returned-cheques', 'public');

                // Create return record
                $returnedCheque = ReturnedCheque::create([
                    'customer_receipt_id' => $cheque->customer_receipt_id,
                    'receipt_cheque_id' => $cheque->id,
                    'reason' => $request->reason,
                    'image_path' => $imagePath,
                    'submitted_by_id' => auth()->id(),
                ]);

                // Update cheque status
                $cheque->update(['status' => 'bounced']);

                // Notify Salesperson (creator of the receipt)
                if ($cheque->customerReceipt && $cheque->customerReceipt->createdBy) {
                    $cheque->customerReceipt->createdBy->notify(new ChequeReturnedNotification($returnedCheque));
                }

                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Returned check submitted successfully and salesperson notified.',
                    'data' => $returnedCheque,
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Failed to submit returned check', [
                'error' => $e->getMessage(),
                'cheque_id' => $cheque->id
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to process return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search for a cheque by number to get its ID.
     */
    public function searchCheque(Request $request): JsonResponse
    {
        $request->validate([
            'cheque_no' => 'required|string|min:3',
        ]);

        $cheques = ReceiptCheque::query()
            ->with(['customerReceipt.customer'])
            ->where('cheque_no', 'LIKE', "%{$request->cheque_no}%")
            ->limit(20)
            ->get()
            ->map(function ($cheque) {
                return [
                    'id' => $cheque->id,
                    'cheque_no' => $cheque->cheque_no,
                    'amount' => $cheque->cheque_amount,
                    'bank_name' => $cheque->bank_name,
                    'status' => $cheque->status,
                    'customer_name' => $cheque->customerReceipt->customer->customer_name ?? 'Unknown Customer',
                    'receipt_number' => $cheque->customerReceipt->receipt_number ?? 'N/A',
                ];
            });

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $cheques,
        ]);
    }
}
