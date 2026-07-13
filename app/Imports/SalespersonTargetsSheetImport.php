<?php

namespace App\Imports;

use App\Models\SalespersonTarget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Per-sheet handler for SalespersonTargetsImport (the orchestrator that
 * implements WithMultipleSheets).
 *
 * Each sheet may carry receipt targets, sales targets, or both. Columns the
 * sheet does NOT contain are left untouched on existing rows — so the
 * "Receipt" sheet won't blank out the sales_target that was just written by
 * the "Sales" sheet, and vice-versa.
 */
class SalespersonTargetsSheetImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    use Importable;

    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $unresolvedNames = [];

    public string $unit;

    public function __construct(string $unit = 'MILLION_PKR')
    {
        $this->unit = $unit;
    }

    public function collection(Collection $rows): void
    {
        // Log the header row of THIS sheet on first iteration so future
        // "why didn't it import" diagnoses are obvious from the logs.
        $headerLogged = false;

        foreach ($rows as $row) {
            $row = $row->all();

            if (!$headerLogged) {
                Log::info('Salesperson targets sheet headers detected', [
                    'columns' => array_keys($row),
                ]);
                $headerLogged = true;
            }

            // Name columns — accept a wide set of common variants so a
            // workbook authored by a different team member still matches.
            $primary  = $this->str($row, [
                'primary_name', 'primary name', 'name',
                'salesperson', 'sales_person', 'sales person',
                'salesman_primary',
            ]);
            $salesman = $this->str($row, [
                'salesman_name', 'salesman name', 'salesman',
                'sales_man', 'sales man', 'employee_name', 'employee name',
            ]);

            $datekey  = $this->str($row, ['datekey', 'date_key', 'period_key']);
            $dateFmt  = $this->str($row, [
                'dateformatted', 'date formatted', 'date_formatted',
                'date', 'period', 'month_date',
            ]);

            // Detect which target columns the sheet actually has. Missing
            // columns stay NULL → not written to the upsert payload (so
            // the OTHER sheet's column for the same row isn't blanked).
            $rcpTgt   = $this->num($row, [
                'rcp_tgt', 'rcp tgt', 'rcp', 'receipt_target', 'receipt_tgt',
                'receipt', 'collection_target', 'collection_tgt', 'collection',
                'recovery_target', 'recovery_tgt', 'recovery',
            ]);
            $salesTgt = $this->num($row, [
                'sales_tgt', 'sales tgt', 'sales_target', 'sales target',
                'sale_tgt', 'net_sales_tgt', 'net_sales',
                'sales', 'monthly_sales_target', 'monthly_sales',
            ]);

            if (!$primary && !$salesman) {
                $this->skipped++;
                continue;
            }

            $period = $this->parsePeriod($datekey, $dateFmt);
            if (!$period) {
                Log::warning('Salesperson target row skipped — could not parse date', [
                    'datekey'        => $datekey,
                    'dateformatted'  => $dateFmt,
                    'primary_name'   => $primary,
                    'sheet'          => static::class,
                ]);
                $this->skipped++;
                continue;
            }

            $userId = SalespersonTarget::resolveUserId($primary, $salesman);
            if ($userId === null && $primary) {
                $this->unresolvedNames[$primary] = true;
            }

            $existing = SalespersonTarget::where('primary_name', $primary)
                ->where('year',  $period['year'])
                ->where('month', $period['month'])
                ->first();

            // Per-row unit override — set by the wide-pivot transformer in
            // SalespersonTargetsImport when emitting unpivoted rows. Those
            // values already carry full PKR, so they shouldn't be multiplied
            // again by the MILLION_PKR unit factor.
            $rowUnit = $this->str($row, ['_unit_override']) ?: $this->unit;

            // Base payload always written; target columns conditionally added
            // so a sheet that doesn't carry a value doesn't blow away the
            // existing one written by the other sheet.
            $payload = [
                'user_id'           => $userId,
                'primary_name'      => $primary,
                'salesman_name'     => $salesman,
                'year'              => $period['year'],
                'month'             => $period['month'],
                'unit'              => $rowUnit,
                'period_start_date' => $period['start'],
            ];
            if ($rcpTgt   !== null) $payload['receipt_target'] = $rcpTgt;
            if ($salesTgt !== null) $payload['sales_target']   = $salesTgt;

            if ($existing) {
                if ($existing->unit !== $rowUnit) {
                    $oldFactor = SalespersonTarget::UNIT_FACTORS[$existing->unit] ?? 1;
                    $newFactor = SalespersonTarget::UNIT_FACTORS[$rowUnit] ?? 1;
                    if ($newFactor > 0 && $oldFactor !== $newFactor) {
                        $scale = $oldFactor / $newFactor;
                        if (!array_key_exists('receipt_target', $payload)) {
                            $payload['receipt_target'] = (float) $existing->receipt_target * $scale;
                        }
                        if (!array_key_exists('sales_target', $payload)) {
                            $payload['sales_target'] = (float) $existing->sales_target * $scale;
                        }
                    }
                }
                $existing->update($payload);
                $this->updated++;
            } else {
                // Brand-new row → make sure the target columns aren't NULL.
                $payload += [
                    'receipt_target' => 0,
                    'sales_target'   => 0,
                ];
                SalespersonTarget::create($payload);
                $this->created++;
            }
        }
    }

    public function stats(): array
    {
        return [
            'created'           => $this->created,
            'updated'           => $this->updated,
            'skipped'           => $this->skipped,
            'unresolved_names'  => array_keys($this->unresolvedNames),
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
            if (array_key_exists($k, $row) && is_numeric($row[$k])) {
                return (float) $row[$k];
            }
        }
        return null;
    }

    private function parsePeriod(?string $datekey, ?string $dateFmt): ?array
    {
        if ($datekey && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $datekey, $m)) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            if ($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12) {
                return [
                    'year'  => $year,
                    'month' => $month,
                    'start' => Carbon::create($year, $month, 1)->startOfDay(),
                ];
            }
        }
        if ($dateFmt) {
            try {
                $d = Carbon::parse($dateFmt);
                return [
                    'year'  => (int) $d->year,
                    'month' => (int) $d->month,
                    'start' => $d->copy()->startOfMonth(),
                ];
            } catch (\Throwable) { /* fall through */ }
        }
        return null;
    }
}
