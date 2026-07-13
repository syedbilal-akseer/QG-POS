<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemPacking;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import the multi-level packing sheet from SC.
 *
 * Expected columns (heading row, case-insensitive):
 *   item_code, item_description, primary_uom, secondary_uom,
 *   primary_packing, secondary_packing,
 *   secondary_to_primary, packing_to_secondary, carton_to_packing  (optional)
 *
 * For each row we upsert 4 ItemPacking rows (PRIMARY_UOM / SECONDARY_UOM /
 * PRIMARY_PACKING / SECONDARY_PACKING). Conversion factors are optional —
 * SC can fill them in a later import without re-uploading the structure.
 *
 * Usage:
 *   php artisan import:item-packings storage/app/imports/packings.xlsx
 *   php artisan import:item-packings storage/app/imports/packings.xlsx --dry-run
 */
class ImportItemPackings extends Command
{
    protected $signature = 'import:item-packings
                            {file : Path to the .xlsx file (relative or absolute)}
                            {--dry-run : Validate and report only — write nothing}';

    protected $description = 'Import multi-level packing definitions from the SC Excel sheet';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $alt = base_path($path);
            if (is_file($alt)) {
                $path = $alt;
            } else {
                $this->error("File not found: {$path}");
                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');

        $rows = collect();
        Excel::import(new class($rows) implements ToCollection, WithHeadingRow {
            public function __construct(public $bag) {}
            public function collection(\Illuminate\Support\Collection $c): void {
                $this->bag->push(...$c);
            }
        }, $path);

        $this->info("Read {$rows->count()} row(s) from {$path}");

        $created = $updated = $skipped = $unmapped = 0;
        $unmappedCodes = [];

        foreach ($rows as $r) {
            $itemCode = $this->stringValue($r, ['item_code', 'itemcode']);
            if (!$itemCode) {
                $skipped++;
                continue;
            }

            // Validate the item exists in items table — if not, skip and report.
            if (!Item::where('item_code', $itemCode)->exists()) {
                $unmapped++;
                $unmappedCodes[] = $itemCode;
                continue;
            }

            $primaryUom       = $this->stringValue($r, ['primary_uom']);
            $secondaryUom     = $this->stringValue($r, ['secondary_uom']);
            $primaryPacking   = $this->stringValue($r, ['primary_packing']);
            $secondaryPacking = $this->stringValue($r, ['seconday_packing', 'secondary_packing']); // sheet had typo

            $secondaryToPrimary = $this->numberValue($r, ['secondary_to_primary']);
            $packingToSecondary = $this->numberValue($r, ['packing_to_secondary']);
            $cartonToPacking    = $this->numberValue($r, ['carton_to_packing']);

            // Compute "conversion to base" cumulatively up the levels
            // (base = primary UOM).
            //   level 1 PRIMARY_UOM       → 1
            //   level 2 SECONDARY_UOM     → secondary_to_primary
            //   level 3 PRIMARY_PACKING   → secondary_to_primary × packing_to_secondary
            //   level 4 SECONDARY_PACKING → secondary_to_primary × packing_to_secondary × carton_to_packing
            $convL2 = $secondaryToPrimary;
            $convL3 = ($secondaryToPrimary !== null && $packingToSecondary !== null)
                ? $secondaryToPrimary * $packingToSecondary : null;
            $convL4 = ($convL3 !== null && $cartonToPacking !== null)
                ? $convL3 * $cartonToPacking : null;

            $defs = [
                [ItemPacking::LEVEL_PRIMARY_UOM,       'PRIMARY_UOM',       $primaryUom,       1.0],
                [ItemPacking::LEVEL_SECONDARY_UOM,     'SECONDARY_UOM',     $secondaryUom,     $convL2],
                [ItemPacking::LEVEL_PRIMARY_PACKING,   'PRIMARY_PACKING',   $primaryPacking,   $convL3],
                [ItemPacking::LEVEL_SECONDARY_PACKING, 'SECONDARY_PACKING', $secondaryPacking, $convL4],
            ];

            foreach ($defs as [$level, $levelCode, $uomLabel, $conversion]) {
                if (!$uomLabel) {
                    continue; // skip levels not provided in the sheet
                }
                $uomCode = strtoupper(trim($uomLabel));

                $payload = [
                    'level_code'         => $levelCode,
                    'uom_code'           => $uomCode,
                    'uom_label'          => $uomLabel,
                    'conversion_to_base' => $conversion,
                    'barcode_payload'    => ItemPacking::makeBarcodePayload($itemCode, $uomCode),
                    'is_active'          => true,
                ];

                if ($dryRun) {
                    $created++; // count it as "would create" for the report
                    continue;
                }

                $existing = ItemPacking::where('item_code', $itemCode)
                    ->where('level', $level)
                    ->first();

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    ItemPacking::create(array_merge(['item_code' => $itemCode, 'level' => $level], $payload));
                    $created++;
                }
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Created: {$created}   Updated: {$updated}   Skipped (no item code): {$skipped}   Unmapped (item not in items table): {$unmapped}");

        if ($unmapped > 0) {
            $this->warn('Unmapped item codes (not present in items table — fix in SC sheet or sync items first):');
            foreach (array_unique($unmappedCodes) as $c) {
                $this->line('  - ' . $c);
            }
        }

        return self::SUCCESS;
    }

    private function stringValue($row, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                return trim((string) $row[$k]);
            }
        }
        return null;
    }

    private function numberValue($row, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && is_numeric($row[$k])) {
                return (float) $row[$k];
            }
        }
        return null;
    }
}
