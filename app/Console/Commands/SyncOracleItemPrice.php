<?php

namespace App\Console\Commands;

use App\Models\ItemPrice;
use App\Models\OracleItemPrice;
use App\Traits\OracleNlsSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOracleItemPrice extends Command
{
    use OracleNlsSession;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:oracle-items-price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync items price from Oracle database to MySQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Oracle price sync...');

        // Set Oracle session parameters to avoid NLS mismatch errors (ORA-01858)
        $this->setOracleNlsSession();

        // Capture sync-start timestamp BEFORE the loop. Any MySQL row whose
        // updated_at is older than this after the sync finishes is one that
        // Oracle did not supply this run — i.e. inactive at the source.
        // Using ->subSecond() gives a tiny safety margin so a row touched in
        // the very first millisecond of the loop isn't accidentally marked
        // stale by clock-skew.
        $syncStartedAt = now()->subSecond();

        // Get total count first
        $totalRecords = OracleItemPrice::count();
        $this->info("Total records to sync: {$totalRecords}");

        if ($totalRecords == 0) {
            $this->warn('No Oracle price records found to sync. Refusing to deactivate any local rows — abort.');
            return;
        }

        $batchSize = 1000; // Process in batches of 1000
        $totalBatches = ceil($totalRecords / $batchSize);
        $currentBatch = 0;
        $syncedCount = 0;

        $this->info("Processing in {$totalBatches} batches of {$batchSize} records each");

        DB::transaction(function () use ($batchSize, $totalBatches, &$currentBatch, &$syncedCount) {
            // We must add an explicit orderBy because the model has no primary key.
            // Failing to order results in a zero-length identifier error in Oracle during chunking.
            OracleItemPrice::orderBy('item_id')->chunk($batchSize, function ($oracleItemsPrices) use (&$currentBatch, &$syncedCount, $totalBatches) {
                $currentBatch++;
                $this->info("Syncing Oracle prices - batch {$currentBatch} of {$totalBatches}");

                foreach ($oracleItemsPrices as $oracleItemPrice) {
                    // Log Oracle data for first 10 items per batch
                    if ($syncedCount < 10) {
                        $this->info("Oracle data: {$oracleItemPrice->item_code} | {$oracleItemPrice->price_list_name} | {$oracleItemPrice->uom} | {$oracleItemPrice->list_price}");
                    }

                    // Find existing record using same criteria as web sync: item_code + price_list_name + uom
                    $existingPrice = ItemPrice::where('item_code', $oracleItemPrice->item_code)
                        ->where('price_list_name', $oracleItemPrice->price_list_name)
                        ->where('uom', $oracleItemPrice->uom)
                        ->first();

                    if ($existingPrice) {
                        // Update existing record if price differs
                        $oldPrice = (float) $existingPrice->list_price;
                        $newPrice = (float) $oracleItemPrice->list_price;

                        if ($oldPrice !== $newPrice) {
                            $existingPrice->update([
                                'price_list_id' => $oracleItemPrice->price_list_id,
                                'price_list_name' => $oracleItemPrice->price_list_name,
                                'item_id' => $oracleItemPrice->item_id,
                                'previous_price' => $existingPrice->list_price,
                                'list_price' => $newPrice,
                                'item_description' => $oracleItemPrice->item_description,
                                'start_date_active' => $oracleItemPrice->start_date_active ? Carbon::parse($oracleItemPrice->start_date_active)->format('Y-m-d H:i:s') : null,
                                'end_date_active' => $oracleItemPrice->end_date_active ? Carbon::parse($oracleItemPrice->end_date_active)->format('Y-m-d H:i:s') : null,
                                'updated_at' => now(),
                            ]);

                            // Log updates for first 10 items per batch
                            if ($syncedCount < 10) {
                                $this->info("UPDATED: {$oracleItemPrice->item_code} | {$oldPrice} → {$newPrice} | Diff: " . ($newPrice - $oldPrice));
                            }
                        } else {
                            // Update other fields even if price is the same (to ensure price_list_id and item_id are set)
                            $existingPrice->update([
                                'price_list_id' => $oracleItemPrice->price_list_id,
                                'price_list_name' => $oracleItemPrice->price_list_name,
                                'item_id' => $oracleItemPrice->item_id,
                                'item_description' => $oracleItemPrice->item_description,
                                'start_date_active' => $oracleItemPrice->start_date_active ? Carbon::parse($oracleItemPrice->start_date_active)->format('Y-m-d H:i:s') : null,
                                'end_date_active' => $oracleItemPrice->end_date_active ? Carbon::parse($oracleItemPrice->end_date_active)->format('Y-m-d H:i:s') : null,
                                'updated_at' => now(),
                            ]);
                        }
                    } else {
                        // Create new record
                        ItemPrice::create([
                            'price_list_id' => $oracleItemPrice->price_list_id,
                            'price_list_name' => $oracleItemPrice->price_list_name,
                            'item_id' => $oracleItemPrice->item_id,
                            'item_code' => $oracleItemPrice->item_code,
                            'item_description' => $oracleItemPrice->item_description,
                            'uom' => $oracleItemPrice->uom,
                            'list_price' => $oracleItemPrice->list_price,
                            'start_date_active' => $oracleItemPrice->start_date_active,
                            'end_date_active' => $oracleItemPrice->end_date_active,
                            'price_changed' => false,
                        ]);

                        // Log new records for first 10 items per batch
                        if ($syncedCount < 10) {
                            $this->info("CREATED: {$oracleItemPrice->item_code} | {$oracleItemPrice->price_list_name} | {$oracleItemPrice->uom} | {$oracleItemPrice->list_price}");
                        }
                    }

                    $syncedCount++;
                }

                $this->info("Batch {$currentBatch} completed. Synced {$syncedCount} records so far.");
            });
        });

        $this->info("Oracle price sync completed successfully. Total synced: {$syncedCount} records.");

        // Deactivate rows Oracle no longer supplies. We only consider rows
        // that ARE currently active (end_date_active null or in the future)
        // so re-running the sync doesn't keep re-touching already-closed
        // rows and so the reported "deactivated" count is the real delta.
        $this->deactivateStaleRows($syncStartedAt);
    }

    /**
     * Set end_date_active on every item_prices row that Oracle did not refresh
     * in this run. Includes a safety guard: if the deactivation candidate set
     * is more than 50% of the currently-active rows, abort the deactivation
     * step and log a warning. That covers the failure mode where Oracle
     * returned a degraded result set (e.g. only one price list synced because
     * of a transient view error) — without this guard, the next sync would
     * silently retire most of the catalogue.
     */
    private function deactivateStaleRows(\Illuminate\Support\Carbon $syncStartedAt): void
    {
        $activeQuery = ItemPrice::query()
            ->where(function ($q) use ($syncStartedAt) {
            $q->whereNull('start_date_active')
                ->orWhere('start_date_active', '<=', $syncStartedAt);
            })
            ->where(function ($q) use ($syncStartedAt) {
                $q->whereNull('end_date_active')
                ->orWhere('end_date_active', '>', $syncStartedAt);
            });

        $totalActive = (clone $activeQuery)->count();

        $staleQuery = (clone $activeQuery)
            ->where('updated_at', '<', $syncStartedAt);

        $staleCount = (clone $staleQuery)->count();

        if ($staleCount === 0) {
            $this->info('No stale rows to deactivate — Oracle covered every active row.');
            return;
        }

        // Safety guard — refuse to retire more than half the catalogue in
        // one sync. A genuine clean-up will only ever be a small fraction;
        // anything bigger is almost certainly a partial Oracle pull.
        if ($totalActive > 0 && ($staleCount / $totalActive) > 0.5) {
            $this->error(sprintf(
                "Refusing to deactivate %d/%d active rows (%.1f%%) — looks like a partial Oracle pull. Investigate before re-running.",
                $staleCount,
                $totalActive,
                ($staleCount / $totalActive) * 100,
            ));
            \Log::warning('SyncOracleItemPrice deactivation aborted', [
                'stale_count'        => $staleCount,
                'total_active_before'=> $totalActive,
                'sync_started_at'    => $syncStartedAt->toDateTimeString(),
            ]);
            return;
        }

        // Snapshot which item_code + price_list_id pairs are about to lose
        // their only active row, so we can tell after the update whether
        // Oracle actually supplied a replacement for them this run. Without
        // this, an item that silently drops out of one price list (Oracle
        // omitted it from this run's feed, no new row created) goes
        // completely unnoticed — it just vanishes from that price list's
        // search/browse results until some later sync happens to restore
        // it, and the first anyone hears about it is a customer complaint.
        $staleRows = (clone $staleQuery)->get(['item_code', 'price_list_id', 'price_list_name'])
            ->unique(fn ($row) => $row->item_code.'|'.$row->price_list_id);

        $updated = (clone $staleQuery)->update([
            'end_date_active' => $syncStartedAt,
            'updated_at'      => now(),
        ]);

        $this->info("Deactivated {$updated} stale row(s) (set end_date_active = {$syncStartedAt->toDateTimeString()}).");
        \Log::info('SyncOracleItemPrice deactivation', [
            'deactivated_rows'   => $updated,
            'total_active_before'=> $totalActive,
            'sync_started_at'    => $syncStartedAt->toDateTimeString(),
        ]);

        $this->reportOrphanedPairs($staleRows, $syncStartedAt);
    }

    /**
     * For each item_code + price_list_id pair that just had its active row
     * end-dated, check whether it still has any active row left. If not,
     * that item has effectively disappeared from that price list — flag it
     * so it can be chased with the Oracle side instead of surfacing only
     * when a salesperson reports "products not showing" for a customer.
     * Capped so a large deactivation batch (still under the 50% guard
     * above) can't turn this into thousands of extra queries in one run.
     */
    private function reportOrphanedPairs(\Illuminate\Support\Collection $staleRows, \Illuminate\Support\Carbon $syncStartedAt): void
    {
        if ($staleRows->isEmpty()) {
            return;
        }

        if ($staleRows->count() > 500) {
            \Log::warning('SyncOracleItemPrice: skipping orphan check, too many affected pairs to check individually', [
                'affected_pairs' => $staleRows->count(),
            ]);
            return;
        }

        $now = now();
        $orphaned = [];

        foreach ($staleRows as $row) {
            $stillActive = ItemPrice::where('item_code', $row->item_code)
                ->where('price_list_id', $row->price_list_id)
                ->whereNotNull('list_price')
                ->where(function ($q) use ($now) {
                    $q->whereNull('start_date_active')->orWhere('start_date_active', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('end_date_active')->orWhere('end_date_active', '>=', $now);
                })
                ->exists();

            if (! $stillActive) {
                $orphaned[] = [
                    'item_code'       => $row->item_code,
                    'price_list_id'   => $row->price_list_id,
                    'price_list_name' => $row->price_list_name,
                ];
            }
        }

        if (empty($orphaned)) {
            return;
        }

        $this->warn(count($orphaned).' item/price-list pair(s) now have NO active price row — Oracle end-dated the old price but did not supply a replacement. These items will show as missing from search/browse for customers on the affected price list(s) until Oracle supplies a fresh row.');
        \Log::warning('SyncOracleItemPrice: item(s) orphaned from price list (no active row after deactivation)', [
            'orphaned_count'  => count($orphaned),
            'orphaned'        => $orphaned,
            'sync_started_at' => $syncStartedAt->toDateTimeString(),
        ]);
    }
}

