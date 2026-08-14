<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\SalespersonTargetsImport;
use App\Models\SalespersonTarget;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SalespersonTargetController extends Controller
{
    /**
     * Listing page with optional filters.
     *
     * Admin sees everything; salespersons see only their own rows (matched by
     * user_id OR by their name/oracle_user_name appearing in the target row).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = SalespersonTarget::query();

        // Non-admin → scope to themselves
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('primary_name', $user->name)
                  ->orWhere('salesman_name', $user->name)
                  ->orWhere('primary_name', $user->oracle_user_name)
                  ->orWhere('salesman_name', $user->oracle_user_name);
            });
        }

        // Default scope: current month. If ?period=year is sent, show whole year.
        $period = $request->query('period', 'month');     // 'month' | 'year'
        $year   = (int) $request->query('year',  now()->year);
        $month  = (int) $request->query('month', now()->month);

        $query->where('year', $year);
        if ($period !== 'year') {
            $query->where('month', $month);
        }

        // Optional filters
        if ($request->filled('user_id') && $user->isAdmin()) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('name')) {
            $term = $request->name;
            $query->where(function ($q) use ($term) {
                $q->where('primary_name', 'like', "%{$term}%")
                  ->orWhere('salesman_name', 'like', "%{$term}%");
            });
        }

        $targets = $query->with('user:id,name,email,role,additional_roles,oracle_user_name,employee_id,status')
            ->orderBy('year')->orderBy('month')->orderBy('primary_name')
            ->paginate(50)
            ->withQueryString();

        // For the user dropdown (admin only)
        $userOptions = $user->isAdmin()
            ? User::whereNotNull('name')->orderBy('name')->pluck('name', 'id')
            : collect();

        return view('admin.salesperson-targets.index', [
            'targets'      => $targets,
            'period'       => $period,
            'year'         => $year,
            'month'        => $month,
            'user_id'      => $request->user_id,
            'name'         => $request->name,
            'userOptions'  => $userOptions,
            'isAdmin'      => $user->isAdmin(),
        ]);
    }

    /**
     * Upload form (admin only).
     */
    public function uploadForm()
    {
        return view('admin.salesperson-targets.upload');
    }

    /**
     * Handle the uploaded TARGET.xlsx file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'unit' => 'required|in:PKR,THOUSAND_PKR,MILLION_PKR',
        ]);

        try {
            $import = new SalespersonTargetsImport($request->input('unit'));
            $import->import($request->file('file'));

            $stats = $import->stats();

            Log::info('Salesperson targets import completed', $stats);

            $msg = sprintf(
                'Created: %d, Updated: %d, Skipped: %d.',
                $stats['created'], $stats['updated'], $stats['skipped']
            );

            // Per-sheet breakdown so the admin can confirm BOTH tabs of a
            // multi-tab workbook actually contributed work.
            if (!empty($stats['per_sheet'])) {
                $sheetBits = [];
                foreach ($stats['per_sheet'] as $key => $s) {
                    if ($s['created'] === 0 && $s['updated'] === 0 && $s['skipped'] === 0) continue;
                    $sheetBits[] = sprintf(
                        '%s → +%d / ~%d / -%d',
                        str_replace('sheet_', 'tab ', $key),
                        $s['created'], $s['updated'], $s['skipped']
                    );
                }
                if (!empty($sheetBits)) {
                    $msg .= ' Per-tab: ' . implode('; ', $sheetBits) . '.';
                }
            }

            if (!empty($stats['unresolved_names'])) {
                $msg .= ' Unmatched names: ' . implode(', ', array_slice($stats['unresolved_names'], 0, 5))
                     . (count($stats['unresolved_names']) > 5 ? '…' : '')
                     . ' (still saved by primary_name — link to users later).';
            }

            return redirect()->route('admin.salesperson-targets.index')->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Salesperson targets import failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
