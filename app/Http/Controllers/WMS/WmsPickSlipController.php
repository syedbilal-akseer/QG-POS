<?php

namespace App\Http\Controllers\WMS;

use App\Http\Controllers\Controller;
use App\Models\WMS\Lpn;
use App\Models\WMS\Location;
use Illuminate\Http\Request;
use TCPDF;

class WmsPickSlipController extends Controller
{
    /**
     * Print a pick slip for a single LPN.
     * Route: GET /wms/pick-slip/{lpn}
     */
    public function single(Lpn $lpn)
    {
        return $this->generatePickSlip(collect([$lpn]));
    }

    /**
     * Print a consolidated pick slip for all stored LPNs of a given item code.
     * Route: GET /wms/pick-slip/item/{itemCode}
     */
    public function byItem(Request $request, string $itemCode)
    {
        $lpns = Lpn::with(['location', 'grnLine.grn'])
            ->where('item_code', $itemCode)
            ->where('status', Lpn::STATUS_STORED)
            ->orderBy('ou_id')
            ->orderByRaw("COALESCE(expiry_date, '9999-12-31')")  // FEFO order
            ->get();

        // Within same expiry group, FIFO order by GRN date
        $lpns = $lpns->sortBy(function ($lpn) {
            return [
                $lpn->expiry_date ?? '9999-12-31',
                $lpn->grnLine?->grn?->received_date ?? now(),
            ];
        })->values();

        if ($lpns->isEmpty()) {
            abort(404, 'No stored stock found for item: ' . $itemCode);
        }

        return $this->generatePickSlip($lpns, "Pick Slip — {$itemCode}");
    }

    /**
     * Print a pick slip for all staged/pending LPNs (put-away pending).
     * Route: GET /wms/pick-slip/pending
     */
    public function pending()
    {
        $lpns = Lpn::with(['location', 'grnLine.grn'])
            ->where('status', Lpn::STATUS_RECEIVED)
            ->orderBy('created_at')
            ->get();

        if ($lpns->isEmpty()) {
            abort(404, 'No pending put-away LPNs.');
        }

        return $this->generatePickSlip($lpns, 'Put-Away Pending List');
    }

    // ── Private ──────────────────────────────────────────────────────────

    private function generatePickSlip(\Illuminate\Support\Collection $lpns, string $title = 'WMS Pick Slip'): \Illuminate\Http\Response
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('WMS');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // ── Header ──
        $pdf->SetFillColor(30, 64, 175);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Rect(10, 10, 190, 12, 'F');
        $pdf->SetXY(12, 11);
        $pdf->Cell(130, 10, $title, 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(58, 10, 'Generated: ' . now()->format('d M Y H:i'), 0, 1, 'R');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY(12, 24);
        $pdf->Cell(100, 5, 'Operator: ' . (auth()->user()?->name ?? 'System'), 0, 0, 'L');
        $pdf->Cell(88, 5, 'Total LPNs: ' . $lpns->count() . '  |  Total Qty: ' . number_format($lpns->sum('quantity')), 0, 1, 'R');

        $pdf->Ln(3);

        // ── Table Header ──
        $pdf->SetFillColor(243, 244, 246);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(75, 85, 99);
        $cols = [
            ['LPN #',        45],
            ['Item / SKU',   30],
            ['Lot',          30],
            ['Location',     22],
            ['Qty / UOM',    18],
            ['Expiry',       18],
            ['GRN Date',     22],
        ];
        $pdf->SetY($pdf->GetY());
        foreach ($cols as [$label, $w]) {
            $pdf->Cell($w, 6, $label, 'TB', 0, 'L', true);
        }
        $pdf->Ln();

        // ── Rows ──
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(0, 0, 0);
        $row = 0;
        foreach ($lpns as $lpn) {
            $grnDate = $lpn->grnLine?->grn?->received_date
                ? \Carbon\Carbon::parse($lpn->grnLine->grn->received_date)->format('d M Y')
                : '—';
            $location = $lpn->location?->location_code ?? 'STAGING';
            $fill = ($row % 2 === 0);

            // Flag expired / expiring
            $isExpiring = $lpn->expiry_date && \Carbon\Carbon::parse($lpn->expiry_date)->diffInDays(now(), false) > -30;
            $isPast     = $lpn->expiry_date && \Carbon\Carbon::parse($lpn->expiry_date)->isPast();

            if ($isPast) {
                $pdf->SetFillColor(254, 226, 226);
                $fill = true;
            } elseif ($isExpiring) {
                $pdf->SetFillColor(254, 243, 199);
                $fill = true;
            } elseif ($fill) {
                $pdf->SetFillColor(249, 250, 251);
            }

            $values = [
                [$lpn->lpn_number,                        45, 'L'],
                [$lpn->item_code,                         30, 'L'],
                [$lpn->system_sub_lot ?? $lpn->lot_number ?? '—', 30, 'L'],
                [$location,                               22, 'C'],
                [number_format($lpn->quantity) . ' ' . strtoupper($lpn->uom ?? ''), 18, 'C'],
                [$lpn->expiry_date ?? '—',                18, 'C'],
                [$grnDate,                                22, 'C'],
            ];
            foreach ($values as [$text, $w, $align]) {
                $pdf->Cell($w, 5, $text, 0, 0, $align, $fill);
            }
            $pdf->Ln();

            // Reset fill color to alternating if we used a special one
            if ($isPast || $isExpiring) {
                $pdf->SetFillColor(249, 250, 251);
            }
            $row++;
        }

        // ── Legend ──
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetFillColor(254, 226, 226);
        $pdf->Cell(4, 4, ' ', 0, 0, 'L', true);
        $pdf->Cell(50, 4, '  Expired stock', 0, 0, 'L');
        $pdf->SetFillColor(254, 243, 199);
        $pdf->Cell(4, 4, ' ', 0, 0, 'L', true);
        $pdf->Cell(80, 4, '  Expiring within 30 days — pick first', 0, 0, 'L');
        $pdf->Ln(4);
        $pdf->Cell(190, 4, 'LPNs are sorted in FEFO → FIFO order. Scan LPN barcode on Directed Picking screen to confirm each pick.', 0, 1, 'L');

        // ── Signature Lines ──
        $pdf->Ln(6);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(60, 5, 'Picker: ___________________________', 0, 0, 'L');
        $pdf->Cell(60, 5, 'Checker: ___________________________', 0, 0, 'C');
        $pdf->Cell(60, 5, 'Dispatch: ___________________________', 0, 1, 'R');

        $filename = 'pick_slip_' . now()->format('Ymd_His') . '.pdf';

        return response($pdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}
