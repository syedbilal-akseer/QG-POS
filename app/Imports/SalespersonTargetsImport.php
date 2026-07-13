<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Orchestrator for the Sales/Receipt Targets workbook upload.
 *
 * Reads EVERY sheet in the workbook (any count, any tab names) via
 * PhpSpreadsheet directly and feeds each one through a fresh
 * SalespersonTargetsSheetImport — so a workbook with a separate
 * "Receipt Targets" tab and a "Sales Targets" tab both land without
 * one blanking the other (the per-sheet handler only writes the
 * columns its tab carries).
 *
 * The earlier WithMultipleSheets approach broke with
 *   "Call to a member function has() on null"
 * when the registered sheet-index map didn't match the workbook's
 * actual sheet layout. PhpSpreadsheet's native iterator avoids that
 * entirely — we discover the real sheets first, then process each.
 */
class SalespersonTargetsImport
{
    public string $unit;

    /** Per-sheet handler instances populated during import(). */
    public array $sheetImports = [];

    public function __construct(string $unit = 'MILLION_PKR')
    {
        $this->unit = $unit;
    }

    /**
     * Mirror the maatwebsite Importable::import() signature so the
     * controller keeps calling `$import->import($file)` unchanged.
     * Accepts an UploadedFile or any path-like value.
     */
    public function import($file): void
    {
        $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : (string) $file;
        if (!$path || !is_readable($path)) {
            throw new \RuntimeException("Salesperson targets file is not readable: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        // Speeds up reading large XLSX files — we only care about cell values.
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
            $rows = $this->extractRows($sheet);
            if ($rows->isEmpty()) {
                Log::info('Salesperson targets — sheet skipped (no data rows)', [
                    'sheet_index' => $sheetIndex,
                    'sheet_name'  => $sheet->getTitle(),
                ]);
                continue;
            }

            $handler = new SalespersonTargetsSheetImport($this->unit);
            $handler->collection($rows);
            $this->sheetImports[$sheetIndex] = $handler;

            Log::info('Salesperson targets — sheet processed', [
                'sheet_index' => $sheetIndex,
                'sheet_name'  => $sheet->getTitle(),
                'row_count'   => $rows->count(),
                'created'     => $handler->created,
                'updated'     => $handler->updated,
                'skipped'     => $handler->skipped,
            ]);
        }
    }

    /**
     * Keywords that flag a row as the real column-header row in the
     * "long" workbook layout (one row per salesperson × month).
     */
    protected const HEADER_KEYWORDS = [
        'primary_name', 'primary', 'name', 'salesman_name', 'salesman', 'salesperson',
        'employee_name', 'employee',
        'rcp_tgt', 'rcp', 'receipt_target', 'receipt_tgt', 'receipt',
        'collection_target', 'collection_tgt', 'collection',
        'recovery_target', 'recovery_tgt', 'recovery',
        'sales_tgt', 'sales_target', 'sales', 'sale_tgt', 'net_sales_tgt', 'net_sales',
        'monthly_sales_target', 'monthly_sales',
        'datekey', 'date_key', 'dateformatted', 'date_formatted',
        'date', 'period', 'month_date', 'month',
    ];

    /**
     * Recognised month names for the "wide" workbook layout where each
     * MONTH is its own column (Jan, Feb, Mar, …). Values are 1–12.
     */
    protected const MONTH_NAMES = [
        'jan' => 1, 'january'   => 1,
        'feb' => 2, 'february'  => 2,
        'mar' => 3, 'march'     => 3,
        'apr' => 4, 'april'     => 4,
        'may' => 5,
        'jun' => 6, 'june'      => 6,
        'jul' => 7, 'july'      => 7,
        'aug' => 8, 'august'    => 8,
        'sep' => 9, 'sept'      => 9, 'september' => 9,
        'oct' => 10, 'october'  => 10,
        'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12,
    ];

    /**
     * Read a single sheet into a Collection<int, Collection<string,mixed>>
     * keyed by normalised header — the SAME shape maatwebsite's
     * WithHeadingRow + ToCollection would deliver, so the existing
     * SalespersonTargetsSheetImport::collection() works unchanged.
     *
     * The sheet's first non-empty row is NOT always the real column header —
     * many workbooks lead with a merged title cell. We scan the first 10
     * rows, score each one by how many recognised header keywords it
     * contains, and pick the highest-scoring row as the header. Everything
     * above it is treated as decoration and skipped.
     */
    protected function extractRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): Collection
    {
        // Convert the entire sheet to a numerically-indexed 2-D array.
        $matrix = $sheet->toArray(null, true, true, false);

        // Strip fully-empty rows so the scan window only sees meaningful rows.
        $matrix = array_values(array_filter($matrix, function ($row) {
            foreach ($row as $cell) {
                if ($cell !== null && $cell !== '') return true;
            }
            return false;
        }));

        if (empty($matrix)) {
            return collect();
        }

        // Dump the first few rows verbatim so a future "didn't import"
        // diagnosis is one log line away.
        Log::info('Salesperson targets — sheet preview', [
            'sheet'   => $sheet->getTitle(),
            'preview' => array_map(
                fn ($row) => array_slice(array_map(fn ($c) => (string) ($c ?? ''), $row), 0, 20),
                array_slice($matrix, 0, 5)
            ),
        ]);

        // ── Wide-layout (months as columns) first ──
        // If a row near the top has ≥3 month names (Jan, Feb, …) we treat
        // the sheet as a pivot: each data row × each month column becomes
        // one long-format target row. This matches the QG workbook layout
        // ("Sales Person | JAN | FEB | … | DEC | Total").
        $wide = $this->tryExtractWidePivot($sheet->getTitle(), $matrix);
        if ($wide !== null) {
            return $wide;
        }

        // ── Detect the real header row (long layout) ──
        // Score each of the first 10 non-empty rows; the one with the most
        // recognised keywords wins. Ties go to the EARLIER row. If no row
        // scores at all (truly unfamiliar workbook layout), fall back to
        // row 0 — the previous behaviour — so we don't silently swallow
        // a sheet that just has unusual column names.
        $scanLimit  = min(10, count($matrix));
        $bestScore  = 0;
        $headerIdx  = 0;
        for ($i = 0; $i < $scanLimit; $i++) {
            $cells     = array_map(fn ($h) => $this->normaliseHeader((string) ($h ?? '')), $matrix[$i]);
            $score     = count(array_intersect($cells, self::HEADER_KEYWORDS));
            if ($score > $bestScore) {
                $bestScore = $score;
                $headerIdx = $i;
            }
        }

        // Drop rows above (and including) the header row from the working set.
        $matrix  = array_slice($matrix, $headerIdx);
        $headers = array_map(
            fn ($h) => $this->normaliseHeader((string) ($h ?? '')),
            array_shift($matrix)
        );

        // Rest = data rows; key each one by the normalised header.
        $out = collect();
        foreach ($matrix as $row) {
            $assoc = [];
            foreach ($headers as $i => $key) {
                if ($key === '') continue;
                $assoc[$key] = $row[$i] ?? null;
            }
            // Skip rows where every value is blank under the headers.
            $hasValue = false;
            foreach ($assoc as $v) {
                if ($v !== null && $v !== '') { $hasValue = true; break; }
            }
            if (!$hasValue) continue;

            $out->push(collect($assoc));
        }

        return $out;
    }

    /**
     * Match WithHeadingRow's default normaliser: lower-case, replace any
     * non-alphanumeric run with underscore, trim leading/trailing _.
     * e.g. "Rcp Tgt" → "rcp_tgt", "Sales Target (PKR)" → "sales_target_pkr".
     */
    protected function normaliseHeader(string $h): string
    {
        $h = strtolower(trim($h));
        $h = preg_replace('/[^a-z0-9]+/u', '_', $h);
        return trim((string) $h, '_');
    }

    /**
     * Detect and unpivot the WIDE workbook layout where each month is its
     * own column:
     *
     *   Sales Person | JAN | FEB | MAR | … | DEC | Total
     *   Tajammul     | …   | …   | …   | … | …   | …
     *
     * Returns a Collection of long-format rows the SheetImport handler
     * already understands:
     *   { primary_name, salesman_name, sales_tgt | rcp_tgt, datekey }
     *
     * The target column written (sales_tgt vs rcp_tgt) is inferred from
     * the sheet title — anything containing "recovery" / "collection" /
     * "receipt" lands in the receipt target column; anything else (incl.
     * the default "Sales Targets" workbook) lands in the sales target.
     *
     * Year defaults to the current year unless the title contains an
     * explicit four-digit year (e.g. "Recovery Targets 2026 (…)").
     *
     * Returns null when the sheet doesn't look pivoted — caller then
     * falls through to the existing long-layout extractor.
     */
    protected function tryExtractWidePivot(string $sheetTitle, array $matrix): ?Collection
    {
        // Find the header row that contains at least 3 month names.
        $headerIdx  = -1;
        $monthMap   = []; // [colIndex => monthNumber]
        $nameColIdx = null;
        $scanLimit  = min(15, count($matrix));

        for ($i = 0; $i < $scanLimit; $i++) {
            $row     = $matrix[$i];
            $months  = [];
            $maybeName = null;

            foreach ($row as $colIdx => $cell) {
                $token = $this->normaliseHeader((string) ($cell ?? ''));
                if (isset(self::MONTH_NAMES[$token])) {
                    $months[$colIdx] = self::MONTH_NAMES[$token];
                } elseif (in_array($token, [
                    'sales_person', 'salesperson', 'sales_man', 'salesman',
                    'salesman_name', 'name', 'employee_name', 'employee',
                    'primary_name', 'primary',
                ], true)) {
                    $maybeName = $colIdx;
                }
            }

            if (count($months) >= 3) {
                $headerIdx  = $i;
                $monthMap   = $months;
                // If the same row had a "Sales Person"-like cell, use it.
                // Otherwise default to column 0 (the leftmost), which is
                // the QG layout's convention.
                $nameColIdx = $maybeName ?? 0;
                break;
            }
        }

        if ($headerIdx === -1) {
            return null; // not a wide pivot
        }

        // ── Determine which target column the values belong to ──
        $title = strtolower($sheetTitle);
        $isRecoveryTab = preg_match('/recovery|collection|receipt|recoverable/', $title) === 1;
        $targetKey = $isRecoveryTab ? 'rcp_tgt' : 'sales_tgt';

        // ── Year inference ──
        // Look in the first few rows for a 4-digit year (e.g. in a title
        // like "Recovery Targets 2026 (Post Customer Transfer)") so the
        // sheet doesn't drift to a different year just because it's
        // uploaded in January.
        $year = (int) date('Y');
        foreach (array_slice($matrix, 0, $scanLimit) as $row) {
            foreach ($row as $cell) {
                if (preg_match('/(20\d{2})/', (string) ($cell ?? ''), $m)) {
                    $year = (int) $m[1];
                    break 2;
                }
            }
        }

        Log::info('Salesperson targets — wide pivot detected', [
            'sheet'        => $sheetTitle,
            'header_row'   => $headerIdx,
            'name_col'     => $nameColIdx,
            'month_cols'   => $monthMap,
            'target_key'   => $targetKey,
            'year'         => $year,
        ]);

        $out = collect();

        foreach (array_slice($matrix, $headerIdx + 1) as $row) {
            $name = trim((string) ($row[$nameColIdx] ?? ''));
            if ($name === '') continue;
            // Skip footer/summary rows like "Total" / "Grand Total".
            if (preg_match('/^total|grand total$/i', $name)) continue;

            foreach ($monthMap as $colIdx => $monthNumber) {
                $raw = $row[$colIdx] ?? null;
                if ($raw === null || $raw === '') continue; // blank cell → no target this month

                $value = (float) $raw;
                if ($value <= 0) continue; // 0 or negative → nothing meaningful to write

                // unit conversion: workbook values are in raw PKR (the
                // tab shows full numbers like 30,285,017.28). The
                // unit defaults to MILLION_PKR (see SalespersonTarget::
                // UNIT_FACTORS) — we override to PKR when emitting so
                // the receipt_target_pkr accessor doesn't multiply by 1e6.
                $out->push(collect([
                    'primary_name'  => $name,
                    'salesman_name' => $name,
                    'datekey'       => sprintf('%04d%02d01', $year, $monthNumber),
                    $targetKey      => $value,
                    '_unit_override' => 'PKR',
                ]));
            }
        }

        return $out;
    }

    /**
     * Aggregated stats across every sheet that was processed. Matches the
     * shape the controller already reads, with an extra `per_sheet` array
     * so the admin can see whether each tab landed work.
     */
    public function stats(): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $unresolved = [];
        $perSheet = [];

        foreach ($this->sheetImports as $idx => $s) {
            $created += $s->created;
            $updated += $s->updated;
            $skipped += $s->skipped;
            $unresolved = array_merge($unresolved, $s->unresolvedNames);
            $perSheet["sheet_{$idx}"] = [
                'created' => $s->created,
                'updated' => $s->updated,
                'skipped' => $s->skipped,
            ];
        }

        return [
            'created'          => $created,
            'updated'          => $updated,
            'skipped'          => $skipped,
            'unresolved_names' => array_values(array_unique(array_keys($unresolved))),
            'per_sheet'        => $perSheet,
        ];
    }
}
