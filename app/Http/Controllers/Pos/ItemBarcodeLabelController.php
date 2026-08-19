<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use TCPDF;

/**
 * Prints Code128 labels encoding item_code only — nothing else (no lot,
 * no packing level, no UOM). Scanned back via BarcodeResolverService's
 * plain-item_code fallback, which already resolves this without any
 * change to that service.
 */
class ItemBarcodeLabelController extends Controller
{
    /**
     * Route: GET /pos/labels/item/{item}
     */
    public function single(Item $item)
    {
        $pdf = $this->makePdf($item->item_code);
        $pdf->AddPage('L', [50, 30]);
        $this->renderLabel($pdf, $item);

        return response($pdf->Output($item->item_code . '.pdf', 'S'), 200)
            ->header('Content-Type', 'application/pdf');
    }

    /**
     * Route: GET /pos/labels/batch?item_codes[]=...&item_codes[]=...
     */
    public function batch(Request $request)
    {
        $validated = $request->validate([
            'item_codes' => 'required|array|min:1|max:200',
            'item_codes.*' => 'string',
        ]);

        $items = Item::whereIn('item_code', $validated['item_codes'])->get();
        if ($items->isEmpty()) {
            abort(404, 'No matching items.');
        }

        $pdf = $this->makePdf('Item Barcodes Batch');
        foreach ($items as $item) {
            $pdf->AddPage('L', [50, 30]);
            $this->renderLabel($pdf, $item);
        }

        return response($pdf->Output('item-barcodes.pdf', 'S'), 200)
            ->header('Content-Type', 'application/pdf');
    }

    private function makePdf(string $title): TCPDF
    {
        $pdf = new TCPDF('L', 'mm', [50, 30], true, 'UTF-8', false);
        $pdf->SetCreator('POS');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(2, 2, 2);
        $pdf->SetAutoPageBreak(false, 0);
        return $pdf;
    }

    private function renderLabel(TCPDF $pdf, Item $item): void
    {
        $style = [
            'position' => '',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => true,
            'font' => 'helvetica',
            'fontsize' => 6,
            'stretchtext' => 4,
        ];

        $pdf->write1DBarcode($item->item_code, 'C128', 4, 3, 42, 16, 0.4, $style, 'T');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY(2, 20);
        $pdf->Cell(46, 4, $item->item_code, 0, 1, 'C');

        if ($item->item_description) {
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->SetXY(2, 24);
            $pdf->MultiCell(46, 4, $item->item_description, 0, 'C');
        }
    }
}
