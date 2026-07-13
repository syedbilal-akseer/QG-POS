<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\PromotionalItemsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class PromotionalItemBulkController extends Controller
{
    public function form()
    {
        return view('admin.promotional-items.bulk-upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new PromotionalItemsImport();
            $import->import($request->file('file'));

            $stats = $import->stats();
            Log::info('Promotional items bulk upload', $stats);

            $msg = sprintf(
                'Promotional items: %d created, %d updated, %d skipped.',
                $stats['created'], $stats['updated'], $stats['skipped']
            );
            if (!empty($stats['unknown_item_codes'])) {
                $msg .= ' Unknown item codes (not in items table): '
                     . implode(', ', array_slice($stats['unknown_item_codes'], 0, 10))
                     . (count($stats['unknown_item_codes']) > 10 ? '…' : '');
            }

            return redirect()->route('promotional-items.all')->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Promotional items bulk upload failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
