<?php

namespace App\Http\Controllers\WMS;

use App\Http\Controllers\Controller;
use App\Models\WMS\GrnLine;
use App\Models\WMS\Lpn;
use Illuminate\Http\Request;
use TCPDF;

class WmsLabelController extends Controller
{
    /**
     * Print a batch of LPN labels for all LPNs generated from a GRN line.
     * Route: GET /wms/labels/batch/{grnLine}
     */
    public function batchByGrnLine(GrnLine $grnLine)
    {
        $lpns = Lpn::where('grn_line_id', $grnLine->id)->get();
        if ($lpns->isEmpty()) {
            abort(404, 'No LPNs found for this GRN line.');
        }
        return $this->generateLabelPdf($lpns, $grnLine->grn);
    }

    /**
     * Print a single LPN label.
     * Route: GET /wms/labels/{lpn}
     */
    public function single(Lpn $lpn)
    {
        return $this->generateLabelPdf(collect([$lpn]));
    }

    /**
     * Print a GRN QR summary page (lot-level, not LPN-level).
     * Route: GET /wms/labels/grn-qr/{grnLine}
     */
    public function grnQr(GrnLine $grnLine)
    {
        $grn = $grnLine->grn;

        $pdf = $this->makePdf('GRN QR — ' . $grnLine->system_sub_lot);
        // Large single-page QR label
        $pdf->AddPage('P', [100, 100]);
        $pdf->SetFont('helvetica', 'B', 7);

        $data = json_encode([
            'item' => $grnLine->item_code,
            'lot'  => $grnLine->system_sub_lot,
            'exp'  => $grnLine->expiry_date,
            'grn'  => $grn->grn_number,
            'po'   => $grn->po_number,
        ]);

        // QR code via TCPDF built-in
        $pdf->write2DBarcode($data, 'QRCODE,H', 25, 8, 50, 50, ['border' => false]);

        $pdf->SetXY(5, 60);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(90, 5, $grnLine->item_code, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(90, 4, 'LOT: ' . $grnLine->system_sub_lot, 0, 1, 'C');
        if ($grnLine->expiry_date) {
            $pdf->SetTextColor(180, 0, 0);
            $pdf->Cell(90, 4, 'EXP: ' . $grnLine->expiry_date, 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Cell(90, 4, 'GRN: ' . $grn->grn_number, 0, 1, 'C');

        return response($pdf->Output('grn_qr.pdf', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="grn_qr_' . $grnLine->system_sub_lot . '.pdf"');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────

    private function generateLabelPdf(\Illuminate\Support\Collection $lpns, ?object $grn = null): \Illuminate\Http\Response
    {
        // Label size: 100mm × 60mm landscape
        $pdf = $this->makePdf('WMS LPN Labels');

        foreach ($lpns as $lpn) {
            $pdf->AddPage('L', [60, 100]);
            $this->renderLpnLabel($pdf, $lpn, $grn);
        }

        $filename = 'lpn_labels_' . now()->format('Ymd_His') . '.pdf';

        return response($pdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    private function makePdf(string $title): TCPDF
    {
        $pdf = new TCPDF('L', 'mm', [60, 100], true, 'UTF-8', false);
        $pdf->SetCreator('WMS');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(3, 3, 3);
        $pdf->SetAutoPageBreak(false, 0);
        return $pdf;
    }

    private function renderLpnLabel(TCPDF $pdf, Lpn $lpn, ?object $grn = null): void
    {
        $grnLine = $lpn->grnLine;
        $grnObj  = $grn ?? $grnLine?->grn;

        // ── Header bar ──
        $pdf->SetFillColor(30, 64, 175);   // Indigo-800
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Rect(3, 3, 94, 6, 'F');
        $pdf->SetXY(3, 3.5);
        $pdf->Cell(50, 5, 'WMS LPN LABEL', 0, 0, 'L');
        $pdf->Cell(44, 5, $lpn->uom ? strtoupper($lpn->uom) : '', 0, 0, 'R');

        // ── Item code ──
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(3, 11);
        $pdf->Cell(94, 6, $lpn->item_code, 0, 1, 'L');

        // ── Meta fields (left column) ──
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetXY(3, 18);
        $fields = [
            ['LOT',    $lpn->system_sub_lot ?? $lpn->lot_number ?? '—'],
            ['SUP LOT', $lpn->lot_number ?? '—'],
            ['MFG',    $lpn->mfg_date ?? '—'],
            ['GRN',    $grnObj?->grn_number ?? '—'],
            ['PO',     $grnObj?->po_number ?? '—'],
            ['WH',     $grnObj?->warehouse_name ?? ($grnObj?->ou_id ?? '—')],
            ['QTY',    number_format($lpn->quantity) . ' ' . strtoupper($lpn->uom ?? '')],
        ];

        foreach ($fields as [$label, $value]) {
            $pdf->SetFont('helvetica', 'B', 5.5);
            $pdf->Cell(12, 4, $label . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->Cell(40, 4, $value, 0, 1, 'L');
        }

        // ── Expiry (highlighted if present) ──
        if ($lpn->expiry_date) {
            $pdf->SetFillColor(254, 226, 226);
            $pdf->SetTextColor(180, 0, 0);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetXY(3, 52);
            $pdf->Cell(50, 5, 'EXP: ' . $lpn->expiry_date, 0, 0, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
        }

        // ── Barcode (Code128) ──
        $pdf->SetXY(55, 15);
        $style = [
            'position'    => '',
            'align'       => 'C',
            'stretch'     => false,
            'fitwidth'    => true,
            'cellfitalign'=> '',
            'border'      => false,
            'hpadding'    => 'auto',
            'vpadding'    => 'auto',
            'fgcolor'     => [0, 0, 0],
            'bgcolor'     => false,
            'text'        => true,
            'font'        => 'helvetica',
            'fontsize'    => 6,
            'stretchtext' => 4,
        ];
        $pdf->write1DBarcode($lpn->lpn_number, 'C128', 55, 15, 42, 22, 0.4, $style, 'T');

        // ── LPN number text ──
        $pdf->SetFont('helvetica', 'B', 5.5);
        $pdf->SetXY(55, 38);
        $pdf->Cell(42, 4, $lpn->lpn_number, 0, 1, 'C');

        // ── GRN date ──
        if ($grnObj?->received_date) {
            $pdf->SetFont('helvetica', '', 5);
            $pdf->SetXY(55, 43);
            $pdf->Cell(42, 4, 'GRN DATE: ' . \Carbon\Carbon::parse($grnObj->received_date)->format('d-M-Y'), 0, 1, 'C');
        }

        // ── Bottom border ──
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(3, 57, 97, 57);
    }
}
