<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\WMS\GatePass;
use TCPDF;

class GatePassLabelController extends Controller
{
    /**
     * Printable gate pass — QR encodes the qr_token, which the security
     * scan screen (GatePassManager::validate) looks up directly.
     * Route: GET /pos/gate-pass/{gatePass}/print
     */
    public function print(GatePass $gatePass)
    {
        $gatePass->load(['order.customer', 'createdBy']);

        $pdf = new TCPDF('P', 'mm', [80, 120], true, 'UTF-8', false);
        $pdf->SetCreator('POS');
        $pdf->SetTitle($gatePass->gate_pass_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(4, 4, 4);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $pdf->SetFillColor(30, 64, 175);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Rect(4, 4, 72, 7, 'F');
        $pdf->SetXY(4, 5);
        $pdf->Cell(72, 5, 'GATE PASS', 0, 1, 'C');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->write2DBarcode($gatePass->qr_token, 'QRCODE,H', 15, 15, 50, 50, ['border' => false]);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(4, 68);
        $pdf->Cell(72, 5, $gatePass->gate_pass_number, 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 7);
        $rows = [
            ['Type', strtoupper($gatePass->type)],
            ['Order', $gatePass->order?->order_number ?? '—'],
            ['Customer', $gatePass->order?->customer?->customer_name ?? '—'],
            ['Created', $gatePass->created_at->format('d M Y, h:i A')],
            ['By', $gatePass->createdBy?->name ?? '—'],
        ];
        $y = 76;
        foreach ($rows as [$label, $value]) {
            $pdf->SetXY(4, $y);
            $pdf->SetFont('helvetica', 'B', 6.5);
            $pdf->Cell(20, 4, $label . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->Cell(52, 4, $value, 0, 1, 'L');
            $y += 5;
        }

        return response($pdf->Output($gatePass->gate_pass_number . '.pdf', 'S'), 200)
            ->header('Content-Type', 'application/pdf');
    }
}
