<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLedgerImport;
use App\Models\LedgerImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LedgerImportController extends Controller
{
    /**
     * Display import page.
     */
    public function index()
    {
        $imports = LedgerImport::latest()
            ->paginate(20);

        return view('admin.ledger-import.index', compact('imports'));
    }

    /**
     * Upload PDF(s) and dispatch import job.
     */
    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:51200', // 50MB
        ]);
        DB::beginTransaction();

        try {

            $file = $request->file('pdf');

            $path = $file->store('ledger-imports');

            $import = LedgerImport::create([
                'file_name'              => $file->getClientOriginalName(),
                'file_path'              => $path,
                'status'                 => 'pending',
                'total_customers'        => 0,
                'total_transactions'     => 0,
                'processed_transactions' => 0,
                'created_by'             => Auth::id(),
            ]);

            ProcessLedgerImport::dispatch($import);

            DB::commit();

            return redirect()
                ->route('ledger.import.index')
                ->with('success', 'PDF uploaded successfully. Import has started in the background.');

        } catch (\Throwable $e) {

            DB::rollBack();

            report($e);

            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show import details.
     */
    public function show(LedgerImport $ledgerImport)
    {
        return response()->json([
            'id'                       => $ledgerImport->id,
            'status'                   => $ledgerImport->status,
            'customers'                => $ledgerImport->total_customers,
            'transactions'             => $ledgerImport->total_transactions,
            'processed_transactions'   => $ledgerImport->processed_transactions,
            'error_log'                => $ledgerImport->error_log,
            'created_at'               => $ledgerImport->created_at,
        ]);
    }

    /**
     * Delete an import record.
     * (Does not delete imported ledger records.)
     */
    public function destroy(LedgerImport $ledgerImport)
    {
        if ($ledgerImport->status === 'processing') {

            return back()->with(
                'error',
                'Cannot delete an import while it is processing.'
            );
        }

        if (
            $ledgerImport->file_path &&
            Storage::exists($ledgerImport->file_path)
        ) {
            Storage::delete($ledgerImport->file_path);
        }

        $ledgerImport->delete();

        return back()->with(
            'success',
            'Import deleted successfully.'
        );
    }

    /**
     * Retry failed import.
     */
    public function retry(LedgerImport $ledgerImport)
    {
        if ($ledgerImport->status !== 'failed') {
            return back()->with(
                'error',
                'Only failed imports can be retried.'
            );
        }

        $ledgerImport->update([
            'status'                 => 'pending',
            'processed_transactions' => 0,
            'error_log'              => null,
        ]);

        ProcessLedgerImport::dispatch($ledgerImport);

        return back()->with(
            'success',
            'Import has been queued again.'
        );
    }
}
