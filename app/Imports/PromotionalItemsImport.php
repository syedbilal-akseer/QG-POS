<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\PromotionalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk-upload promotional items by item_code only. Everything else (item
 * description, original_price, etc.) is pulled from the existing items /
 * item_prices tables so SC doesn't have to re-enter it.
 *
 * Expected Excel headers (case-insensitive, normalized via WithHeadingRow):
 *   item_code           — required (must exist in items table)
 *   buy_qty             — optional integer (scheme: buy N)
 *   free_qty            — optional integer (scheme: get M free)
 *   free_item_code      — optional; if blank the free item is the SAME as purchased
 *   discounted_price    — optional decimal (special price)
 *   scheme_start_date   — optional YYYY-MM-DD
 *   scheme_end_date     — optional YYYY-MM-DD
 *   is_active           — optional (defaults to 1/true)
 */
class PromotionalItemsImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    use Importable;

    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $unknownItemCodes = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $row = $row->all();

            $itemCode = $this->str($row, ['item_code', 'itemcode']);
            if (!$itemCode) {
                $this->skipped++;
                continue;
            }

            $item = Item::where('item_code', $itemCode)->first();
            if (!$item) {
                $this->unknownItemCodes[$itemCode] = true;
                $this->skipped++;
                continue;
            }

            // Pull original price from item_prices (first available row).
            $originalPrice = \App\Models\ItemPrice::where('item_code', $itemCode)
                ->orderByDesc('price_updated_at')
                ->value('list_price');

            $freeItemCode = $this->str($row, ['free_item_code']);
            if ($freeItemCode && !Item::where('item_code', $freeItemCode)->exists()) {
                Log::warning('Promotional bulk: free_item_code not in items — keeping but flagged', [
                    'free_item_code' => $freeItemCode,
                    'row_item_code'  => $itemCode,
                ]);
            }

            $payload = [
                'item_code'         => $itemCode,
                'original_price'    => $originalPrice,
                'discounted_price'  => $this->num($row, ['discounted_price']),
                'buy_qty'           => $this->int($row, ['buy_qty', 'scheme_qty']),
                'free_qty'          => $this->int($row, ['free_qty', 'free']),
                'free_item_code'    => $freeItemCode ?: null,
                'scheme_start_date' => $this->str($row, ['scheme_start_date', 'start_date']),
                'scheme_end_date'   => $this->str($row, ['scheme_end_date', 'end_date']),
                'is_active'         => $this->bool($row, ['is_active']) ?? true,
            ];

            $existing = PromotionalItem::where('item_code', $itemCode)->first();

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                PromotionalItem::create($payload);
                $this->created++;
            }
        }
    }

    public function stats(): array
    {
        return [
            'created'              => $this->created,
            'updated'              => $this->updated,
            'skipped'              => $this->skipped,
            'unknown_item_codes'   => array_keys($this->unknownItemCodes),
        ];
    }

    private function str(array $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                return trim((string) $row[$k]);
            }
        }
        return null;
    }

    private function num(array $row, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && is_numeric($row[$k])) return (float) $row[$k];
        }
        return null;
    }

    private function int(array $row, array $keys): ?int
    {
        $v = $this->num($row, $keys);
        return $v === null ? null : (int) $v;
    }

    private function bool(array $row, array $keys): ?bool
    {
        foreach ($keys as $k) {
            if (isset($row[$k])) {
                $v = strtolower(trim((string) $row[$k]));
                if ($v === '') return null;
                return in_array($v, ['1', 'true', 'yes', 'y', 'active'], true);
            }
        }
        return null;
    }
}
