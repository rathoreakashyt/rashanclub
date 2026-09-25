<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\BusyNotifyService;
use App\Services\StockLedgerService;

/**
 * busy:sync-stock
 *
 * Har 2 minute mein sirf stock update hota hai Busy se.
 * Full import (busy:import) ke muqable mein ye sirf 5-10 seconds lagata hai.
 *
 * Flow:
 *   1. BusyNotify /v1/products → product_id, product_alias, product_stock
 *   2. Compare with DB — sirf changed items update (delta)
 *   3. LEDGER SYNC: items.stock_quantity ke saath view_stock_detail (ledger)
 *      ko bhi barabar karo — warna reports/desktop view wala stock zero ya
 *      stale reh jata tha (ye zero-stock bug ka main reason tha).
 *   4. Cache mein "last_stock_sync_at" + "stock_delta"
 *   5. Desktop /api/desktop/busy-import/stock-delta se delta pull karega
 *
 * BULLETPROOF GUARDS:
 *   • API ne `product_stock` field hi bhi nahi bheja (ya null) → us product ko
 *     chhodo, 0 MAT likho (field rename / auth / company mismatch par mass-zero
 *     nahi hoga).
 *   • Purane positive stock wale products ki ~90%+ ek saath 0 ho rahi ho to
 *     zero-writes rok do + error log (API ya company galat hone ka signal).
 *   • metadata.rowCount > received rows → truncated/paginated response ka warning.
 */
class BusySyncStock extends Command
{
    protected $signature   = 'busy:sync-stock {--company=1 : Company id jiska stock sync hoga}
                            {--outlet= : Outlet id for adjustment rows (default 1)}';
    protected $description = 'Fast stock-only sync from BusyNotify (runs every 2 minutes)';

    public function handle(): int
    {
        if (!BusyNotifyService::isEnabled()) {
            $this->line('BusyNotify not configured — skip');
            return 0;
        }

        $companyId = (int) $this->option('company');
        $outletId  = (int) ($this->option('outlet') ?: 1);
        $now       = now()->toDateTimeString();

        try {
            $service  = new BusyNotifyService();
            $products = $service->getProductsStockOnly();

            if (empty($products)) {
                $this->warn('BusyNotify returned 0 products');
                Log::warning('busy:sync-stock: BusyNotify returned 0 products — stock unchanged');
                return 0;
            }

            // ── Response sanity: rowCount vs received rows (pagination/truncation) ──
            $meta      = $service->getLastMeta();
            $expected  = isset($meta['rowCount']) ? (int) $meta['rowCount'] : null;
            if ($expected !== null && $expected > count($products)) {
                $msg = "busy:sync-stock: truncated response — received " . count($products)
                     . " of {$expected} products (metadata.rowCount). Partial sync!";
                $this->warn($msg);
                Log::warning($msg);
            }

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
            $desired      = []; // [item_id => new_stock] — ledger reconcile ke liye
            $delta        = []; // changed items for the desktop
            $unknown      = 0;  // rows jisme product_stock field hi nahi thi
            $matched      = 0;
            $wasPositive  = 0;  // purane stock > 0 wale matched items
            $posToZero    = 0;  // unhi me se ab 0 hone wale

            foreach ($products as $p) {
                $rawStock = $p['product_stock'] ?? null;

                // GUARD 1: stock field missing/null = "pata nahi", 0 nahi.
                if ($rawStock === null || $rawStock === '') {
                    $unknown++;
                    continue;
                }

                $busyId   = $p['product_id'];
                $code     = $p['product_alias'] ?? null;
                $newStock = (float) $rawStock;

                // Match by busy_id first, then by code
                $dbItem = $busyId ? ($dbItems[$busyId] ?? null) : null;
                if (!$dbItem && $code) {
                    $dbItem = $dbByCode[$code] ?? null;
                }
                if (!$dbItem) continue;

                $matched++;
                $oldStock = (float) ($dbItem->stock_quantity ?? 0);

                if ($oldStock > 0.001) {
                    $wasPositive++;
                    if ($newStock <= 0.001) $posToZero++;
                }

                // Only update if stock actually changed (0.001 tolerance)
                if (abs($newStock - $oldStock) > 0.001) {
                    $stockUpdates[$dbItem->id] = $newStock;
                    $desired[$dbItem->id]      = $newStock;
                    $delta[] = [
                        'item_id'  => $dbItem->id,
                        'busy_id'  => $busyId,
                        'code'     => $code,
                        'old_stock'=> $oldStock,
                        'new_stock'=> $newStock,
                    ];
                }
            }

            // ── GUARD 2: mass-zero protection ──
            // Pehle positive stock wale ~90%+ products ek saath 0 aa rahi hain to
            // API/company/koi aur problem hai — zero-writes rok do (positives chalu).
            if ($wasPositive >= 10 && $posToZero >= (int) ceil(0.9 * $wasPositive)) {
                $blocked = 0;
                foreach ($stockUpdates as $id => $v) {
                    if ($v <= 0.001) {
                        unset($stockUpdates[$id], $desired[$id]);
                        $blocked++;
                    }
                }
                $delta = array_values(array_filter($delta, fn($d) => $d['new_stock'] > 0.001));
                $msg = "busy:sync-stock: MASS-ZERO GUARD — {$posToZero}/{$wasPositive} positive-stock "
                     . "products returned 0 from Busy. Blocked {$blocked} zero-writes. "
                     . 'Check BUSYNOTIFY token/companyId/financialYear.';
                $this->error($msg);
                Log::error($msg);
            }

            if ($matched === 0 && count($products) > 0) {
                $msg = 'busy:sync-stock: 0 products matched with DB items '
                     . '(busy_id/code mismatch?) — nothing updated.';
                $this->warn($msg);
                Log::warning($msg);
            }

            if ($unknown > 0) {
                $this->warn("{$unknown} products had no product_stock field — skipped (not treated as 0)");
            }

            // Batch update items — one query per changed item
            if (!empty($stockUpdates)) {
                // Chunked CASE UPDATE — single query for up to 500 items
                foreach (array_chunk($stockUpdates, 500, true) as $chunk) {
                    $ids      = implode(',', array_map('intval', array_keys($chunk)));
                    $caseSql  = 'CASE id ';
                    $bindings = [];
                    foreach ($chunk as $itemId => $stock) {
                        $caseSql   .= "WHEN ? THEN ? ";
                        $bindings[] = (int) $itemId;
                        $bindings[] = (float) $stock;
                    }
                    $caseSql .= 'END';
                    $bindings[] = $now; // updated_at binding

                    DB::statement(
                        "UPDATE items SET stock_quantity = {$caseSql}, updated_at = ? WHERE id IN ({$ids})",
                        $bindings
                    );
                }
            }

            $updated = count($stockUpdates);

            // ── LEDGER SYNC (zero-stock fix) ──
            // items.stock_quantity ke saath view_stock_detail (ledger) bhi barabar
            // karo + view dobara banao, taaki web reports aur desktop ka
            // view_stock_detail mirror wahi dikhaye jo Busy/POS bol raha hai.
            if (!empty($desired)) {
                try {
                    $stats = StockLedgerService::reconcileMany($desired, $companyId, $outletId);
                    StockLedgerService::rebuild();
                    if ($stats['adjusted'] > 0) {
                        $this->line("Ledger reconcile: {$stats['adjusted']} adjustment rows, view rebuilt");
                    }
                } catch (\Throwable $e) {
                    // items.stock_quantity update bach chuka hai — ledger agli baar
                    // heal ho jayega. Sync fail mat hone do.
                    Log::error('busy:sync-stock: ledger reconcile/rebuild failed', ['error' => $e->getMessage()]);
                    $this->warn('Ledger reconcile failed: ' . $e->getMessage());
                }
            }

            // Store delta in cache for 10 minutes — desktop polls this
            Cache::put('busy_stock_delta', [
                'synced_at' => $now,
                'updated'   => $updated,
                'delta'     => $delta,
            ], now()->addMinutes(10));

            Cache::put('busy_last_stock_sync', $now, now()->addHours(24));

            $this->info("Stock sync done: {$updated} items updated"
                . ($unknown ? " ({$unknown} skipped: no stock field)" : ''));
            return 0;

        } catch (\Throwable $e) {
            $this->error('Stock sync error: ' . $e->getMessage());
            Log::error('busy:sync-stock failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
