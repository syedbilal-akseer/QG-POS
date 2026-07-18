<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;

/**
 * PDF text extraction + page splitting, shared by controllers that need to
 * break a bulk multi-customer PDF into one file per customer. Copied from
 * InvoiceController's private methods rather than refactoring that
 * controller in place, so the existing invoice pipeline stays untouched.
 */
trait ExtractsPdfPages
{
    /**
     * Extract full text from a PDF, preserving page breaks as form-feed (\f)
     * characters so callers can split on them. Tries pdftotext -layout first
     * (keeps column spacing), falls back to Smalot\PdfParser.
     */
    protected function extractPdfTextFromFile(string $pdfPath): string
    {
        try {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $checkCmd = $isWindows ? 'where pdftotext' : 'which pdftotext 2>/dev/null';
            exec($checkCmd, $output, $returnCode);

            if ($returnCode === 0) {
                $tempTextFile = sys_get_temp_dir().'/'.uniqid('ledger_pdf_text_').'.txt';
                $command = sprintf('pdftotext -layout %s %s', escapeshellarg($pdfPath), escapeshellarg($tempTextFile));
                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($tempTextFile)) {
                    $text = file_get_contents($tempTextFile);
                    unlink($tempTextFile);

                    return $text;
                }
            }

            $parser = new Parser;
            $pdf = $parser->parseFile($pdfPath);
            $pages = $pdf->getPages();
            $text = '';

            foreach ($pages as $page) {
                $text .= $page->getText()."\n\f\n";
            }

            if (! empty(trim($text))) {
                Log::info('Ledger PDF text extracted via PdfParser fallback', ['pages_found' => count($pages)]);

                return $text;
            }

            return '';
        } catch (\Exception $e) {
            Log::error('Ledger PDF text extraction failed: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Extract the given 1-indexed $pages from $inputPath into a new PDF at
     * $outputPath using FPDI. Returns false (without throwing) on failure so
     * callers can mark that customer's row as failed instead of aborting the
     * whole batch.
     */
    protected function splitPdfPages(string $inputPath, string $outputPath, array $pages): bool
    {
        try {
            $fpdi = new Fpdi;
            $fpdi->SetMargins(0, 0, 0);
            $fpdi->SetAutoPageBreak(false);

            $pageCount = $fpdi->setSourceFile($inputPath);

            foreach ($pages as $pageNum) {
                if ($pageNum <= 0 || $pageNum > $pageCount) {
                    Log::warning('Ledger page number out of range for FPDI', ['page' => $pageNum, 'total' => $pageCount]);

                    continue;
                }

                $tplId = $fpdi->importPage($pageNum);
                $size = $fpdi->getTemplateSize($tplId);

                if ($size === false || ! isset($size['w']) || ! isset($size['h'])) {
                    $size = ['w' => 210, 'h' => 297, 'orientation' => 'P'];
                }

                $orientation = $size['orientation'] ?? ($size['w'] > $size['h'] ? 'L' : 'P');
                $fpdi->AddPage($orientation, [$size['w'], $size['h']]);
                $fpdi->useTemplate($tplId, 0, 0, $size['w'], $size['h'], true);
            }

            $pdfContent = $fpdi->Output('', 'S');
            file_put_contents($outputPath, $pdfContent);

            return file_exists($outputPath) && filesize($outputPath) > 0;
        } catch (\Exception $e) {
            Log::error('Ledger PDF page split failed: '.$e->getMessage(), [
                'input' => $inputPath,
                'output' => $outputPath,
                'pages' => $pages,
            ]);

            return false;
        }
    }
}
