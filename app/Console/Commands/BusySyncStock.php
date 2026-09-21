<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\BusyNotifyService;

/**
 * busy:sync-stock
 *
 * Har 2 minute mein sirf stock_quantity update karta hai Busy se.
 * Full import (busy:import) ke muqable mein ye sirf 5-10 seconds lagata hai.
 *
 * Flow:
 *   1. BusyNotify /v1/products → sirf product_id, product_alias, product_stock
 *   2. Compare with DB — sirf changed items update (delta)
 *   3. Cache mein "last_stock_sync_at" + "stock_delta" store karo
 *   4. Desktop /api/desktop/busy-import/stock-delta se ye delta pull karega
 */
class BusySyncStock extends Command
{
    protected $signature   = 'busy:sync-stock';
    protected $description = 'Fast stock-only sync from BusyNotify (runs every 2 minutes)';

    public function handle(): int
    {
        if (!BusyNotifyService::isEnabled()) {
            $this->line('BusyNotify not configured — skip');
            return 0;
        }

        $companyId = 1;
        $now       = now()->toDateTimeString();

        try {
            $service  = new BusyNotifyService();
            $products = $service->getProductsStockOnly();

            if (empty($products)) {
                $this->warn('BusyNotify returned 0 products');
                return 0;
            }

            $updated  = 0;
            $delta    = []; // changed items: [busy_id => new_stock]

            // Fetch current DB stocks in one query (keyed by busy_id and code)
            $dbItems = DB::table('items')
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->whereNotNull('busy_id')
                ->select('id', 'busy_id', 'code', 'stock_quantity')
                ->get()
                ->keyBy('busy_id');

            // Also index by code for fallback match
            $dbByCode = DB::table('items')
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->select('id', 'busy_id', 'code', 'stock_quantity')
                ->get()
                ->keyBy('code');

            $stockUpdates = []; // [item_id => new_stock]

            foreach ($products as $p) {
                $busyId    = $p['product_id'];
                $code      = $p['product_alias'] ?? null;
                $newStock  = (float) ($p['product_stock'] ?? 0);

                // Match by busy_id first, then by code
                $dbItem = $busyId ? ($dbItems[$busyId] ?? null) : null;
                if (!$dbItem && $code) {
                    $dbItem = $dbByCode[$code] ?? null;
                }
                if (!$dbItem) continue;

                $oldStock = (float) ($dbItem->stock_quantity ?? 0);

                // Only update if stock actually changed (0.001 tolerance)
                if (abs($newStock - $oldStock) > 0.001) {
                    $stockUpdates[$dbItem->id] = $newStock;
                    $delta[] = [
                        'item_id'  => $dbItem->id,
                        'busy_id'  => $busyId,
                        'code'     => $code,
                        'old_stock'=> $oldStock,
                        'new_stock'=> $newStock,
                    ];
                }
            }

            // Batch update items — one query per changed item
            // For large datasets use chunked CASE UPDATE
            if (!empty($stockUpdates)) {
                // Chunked CASE UPDATE — single query for up to 500 items
                foreach (array_chunk($stockUpdates, 500, true) as $chunk) {
                    $ids      = implode(',', array_keys($chunk));
                    $caseSql  = 'CASE id ';
                    $bindings = [];
                    foreach ($chunk as $itemId => $stock) {
                        $caseSql   .= "WHEN ? THEN ? ";
                        $bindings[] = $itemId;
                        $bindings[] = $stock;
                    }
                    $caseSql .= 'END';
                    $bindings[] = $now; // updated_at binding

                    DB::statement(
                        "UPDATE items SET stock_quantity = {$caseSql}, updated_at = ? WHERE id IN ({$ids})",
                        $bindings
                    );
                    $updated += count($chunk);
                }
            }

            // Store delta in cache for 10 minutes — desktop polls this
            Cache::put('busy_stock_delta', [
                'synced_at' => $now,
                'updated'   => $updated,
                'delta'     => $delta,
            ], now()->addMinutes(10));

            Cache::put('busy_last_stock_sync', $now, now()->addHours(24));

            $this->info("Stock sync done: {$updated} items updated");
            return 0;

        } catch (\Throwable $e) {
            $this->error('Stock sync error: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('busy:sync-stock failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
