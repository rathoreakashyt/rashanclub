<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stock ledger ka SINGLE SOURCE OF TRUTH.
 *
 * Stock ke do sources hain jo HAMESHA barabar rahne chahiye:
 *
 *   1. items.stock_quantity — POS / item master / desktop software
 *      (/api/sync/pull isko max(ledger, stock_quantity) bhejta hai)
 *   2. view_stock_detail    — ledger (purchase + opening + sale return
 *      - sales - purchase return - damage). Web reports, stock pages aur
 *      desktop ReportPage/dashboard isi se stock padhte hain.
 *
 * Agar dono me se koi bhi update ho (Busy sync, desktop push, manual edit)
 * to reconcile*() ek SYNC_ADJ delta row likh kar ledger ko barabar karta hai
 * aur rebuild() poore view ko ATOMIC dobara banata hai.
 *
 * ── Pehle kya galat tha (zero-stock bug) ──────────────────────────────
 * • busy:sync-stock sirf items.stock_quantity update karta tha — ledger
 *   kabhi update hi nahi hota tha, isliye Busy me stock aane par bhi
 *   report wali screens zero/stale dikhati thin.
 * • view rebuild ki 5 copy thi aur sab TRUNCATE-then-INSERT thi bina
 *   transaction ke (TRUNCATE = implicit commit). INSERT fail hone par
 *   poora view KHAALI reh jata tha → har jagah 0 stock.
 * • 2 copy me negative SYNC_ADJ rows drop ho jati thin (manual stock
 *   reduction gayab ho jati thi).
 * Yahan ka code in teeno problems ka ek hi fix hai.
 */
class StockLedgerService
{
    /** SYNC_ADJ rows ka prefix — reconcile isi ko update/delete karta hai. */
    public const ADJ = 'SYNC_ADJ';

    /**
     * Atomic canonical rebuild.
     *
     * DELETE + INSERT ek transaction me (TRUNCATE nahi — MySQL TRUNCATE pe
     * implicit commit kar deta hai, rollback ka koi fayda nahi). Isliye
     * rebuild fail = purana view safe, kabhi khaali view nahi milega.
     *
     * Negative SYNC_ADJ rows bhi IN count hoti hain (type=1, negative qty)
     * taaki manual/POS stock reduction propagate ho.
     */
    public static function rebuild(): void
    {
        DB::transaction(function () {
            DB::statement('DELETE FROM view_stock_detail');
            DB::statement(self::rebuildSql());
        });
    }

    /**
     * Rebuild jo exception na uchhale (legacy call sites jahan error sirf
     * log hota tha). Returns: true = rebuild hua.
     */
    public static function rebuildQuietly(): bool
    {
        try {
            self::rebuild();
            return true;
        } catch (\Throwable $e) {
            // Transaction rollback ho chuka hai — view purane data par safe hai.
            Log::error('StockLedgerService::rebuild failed (view left untouched)', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * CANONICAL rebuild SQL — ab har jagah (sync controller, busy import,
     * desktop import, POS sale, refresh-stock-view route) isi ek copy ko
     * use karta hai.
     */
    public static function rebuildSql(): string
    {
        return "INSERT INTO view_stock_detail (item_id, type, stock_quantity, outlet_id, company_id, del_status)
            -- Purchases IN
            SELECT item_id, 1, quantity_amount, outlet_id, company_id, del_status
            FROM purchase_details WHERE del_status='Live' AND quantity_amount > 0
            UNION ALL
            -- Opening stock IN (negative SYNC_ADJ adjustment rows bhi — warna
            -- stock kam karne wale adjustment view me kabhi nahi aate)
            SELECT item_id, 1, stock_quantity, outlet_id, company_id, 'Live'
            FROM set_opening_stocks
            WHERE stock_quantity > 0 OR item_description LIKE '" . self::ADJ . "%'
            UNION ALL
            -- Sale returns IN
            SELECT item_id, 1, return_quantity_amount, outlet_id, company_id, del_status
            FROM sale_return_details WHERE del_status='Live' AND return_quantity_amount > 0
            UNION ALL
            -- Sales OUT
            SELECT item_id, 2, qty, outlet_id, company_id, del_status
            FROM sale_details WHERE del_status='Live' AND qty > 0
            UNION ALL
            -- Purchase returns OUT
            SELECT item_id, 2, return_quantity_amount, outlet_id, company_id, del_status
            FROM purchase_return_details WHERE del_status='Live' AND return_quantity_amount > 0
            UNION ALL
            -- Damages OUT
            SELECT item_id, 2, damage_quantity, outlet_id, company_id, del_status
            FROM damage_details WHERE del_status='Live' AND damage_quantity > 0";
    }

    /**
     * Ledger (view ke sources se) = purchase + sale return + user opening
     * - sales - purchase return - damage, per item.
     *
     * @param  int[] $itemIds
     * @return array<int,float> item_id => ledger qty
     */
    public static function ledgerTotals(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if (!$itemIds) {
            return [];
        }

        $sum = function (string $table, string $column, bool $liveOnly = true) use ($itemIds) {
            $q = DB::table($table)->whereIn('item_id', $itemIds);
            if ($liveOnly) {
                $q->where('del_status', 'Live');
            }
            return $q->groupBy('item_id')
                ->selectRaw("item_id, SUM(IFNULL({$column},0)) AS qty")
                ->pluck('qty', 'item_id');
        };

        $purchaseIn   = $sum('purchase_details', 'quantity_amount');
        $saleReturnIn = $sum('sale_return_details', 'return_quantity_amount');
        $saleOut      = $sum('sale_details', 'qty');
        $purchaseRet  = $sum('purchase_return_details', 'return_quantity_amount');
        $damageOut    = $sum('damage_details', 'damage_quantity');

        // Opening rows — SYNC_ADJ chhod kar (warna adjustment dobara count hoga).
        // NOTE: item_description NULL rows bhi count hoti hain (web opening stock
        // NULL description se save karta hai) — NOT LIKE NULL rows ko drop kar
        // deta tha, jisse ledger galat aata tha.
        $opening = DB::table('set_opening_stocks')
            ->whereIn('item_id', $itemIds)
            ->where(function ($q) {
                $q->whereNull('item_description')
                  ->orWhere('item_description', 'NOT LIKE', self::ADJ . '%');
            })
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(IFNULL(stock_quantity,0)) AS qty')
            ->pluck('qty', 'item_id');

        $totals = [];
        foreach ($itemIds as $id) {
            $totals[$id] = round(
                (float) ($purchaseIn[$id] ?? 0)
                + (float) ($saleReturnIn[$id] ?? 0)
                + (float) ($opening[$id] ?? 0)
                - (float) ($saleOut[$id] ?? 0)
                - (float) ($purchaseRet[$id] ?? 0)
                - (float) ($damageOut[$id] ?? 0),
                3
            );
        }

        return $totals;
    }

    /**
     * Ledger ko desired stock par le aao (SYNC_ADJ delta row upsert karke).
     *
     * @param  array<int,float> $desiredByItemId item_id => desired stock (Busy/local truth)
     * @return array{adjusted:int,unchanged:int}
     */
    public static function reconcileMany(array $desiredByItemId, int $companyId, ?int $outletId = null, int $userId = 0): array
    {
        $stats = ['adjusted' => 0, 'unchanged' => 0];
        if (!$desiredByItemId) {
            return $stats;
        }

        $desiredByItemId = array_map('floatval', $desiredByItemId);
        $outletId = (int) ($outletId ?: 1);
        $now = now()->toDateTimeString();

        foreach (array_chunk($desiredByItemId, 500, true) as $chunk) {
            $ids     = array_keys($chunk);
            $totals  = self::ledgerTotals($ids);

            // Existing SYNC_ADJ rows (ek se zyada ho to extra delete)
            $adjRows = [];
            $extraIds = [];
            $rows = DB::table('set_opening_stocks')
                ->whereIn('item_id', $ids)
                ->where('item_description', 'LIKE', self::ADJ . '%')
                ->orderBy('id')
                ->get(['id', 'item_id', 'stock_quantity']);
            foreach ($rows as $r) {
                $itemId = (int) $r->item_id;
                if (isset($adjRows[$itemId])) {
                    $extraIds[] = (int) $r->id; // duplicate adjustment row
                    continue;
                }
                $adjRows[$itemId] = $r;
            }

            try {
                DB::beginTransaction();

                if ($extraIds) {
                    DB::table('set_opening_stocks')
                        ->whereIn('id', $extraIds)
                        ->delete();
                }

                foreach ($chunk as $itemId => $desired) {
                    $itemId  = (int) $itemId;
                    $current = (float) ($adjRows[$itemId]->stock_quantity ?? 0);
                    // Ledger + existing ADJ = current value; desired ke liye naya ADJ
                    $newAdj = round($desired - ($totals[$itemId] ?? 0), 3);

                    if (abs($newAdj - $current) <= 0.001) {
                        $stats['unchanged']++;
                        continue;
                    }

                    if (abs($newAdj) <= 0.001) {
                        // Ledger pehle se barabar hai — extra adjustment row hata do
                        if (isset($adjRows[$itemId])) {
                            DB::table('set_opening_stocks')->where('id', $adjRows[$itemId]->id)->delete();
                            $stats['adjusted']++;
                        } else {
                            $stats['unchanged']++;
                        }
                        continue;
                    }

                    if (isset($adjRows[$itemId])) {
                        DB::table('set_opening_stocks')->where('id', $adjRows[$itemId]->id)->update([
                            'stock_quantity' => $newAdj,
                            'updated_at'     => $now,
                        ]);
                    } else {
                        DB::table('set_opening_stocks')->insert([
                            'item_id'         => $itemId,
                            'item_type'       => 'opening',
                            'item_description' => self::ADJ,
                            'stock_quantity'  => $newAdj,
                            'outlet_id'       => $outletId,
                            'user_id'         => $userId ?: null,
                            'company_id'      => $companyId,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ]);
                    }
                    $stats['adjusted']++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('StockLedgerService::reconcileMany failed', ['error' => $e->getMessage()]);
                throw $e;
            }
        }

        return $stats;
    }

    /**
     * Ek item ko code se reconcile karo (desktop push/manual stock edit path).
     * Desired = items.stock_quantity ka current value (DB se).
     */
    public static function reconcileByCode(?string $code, int $companyId, ?int $outletId = null, int $userId = 0): void
    {
        if (empty($code)) {
            return;
        }

        try {
            $item = DB::table('items')
                ->where('code', $code)
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->first();

            if (!$item) {
                return;
            }

            self::reconcileMany(
                [(int) $item->id => (float) ($item->stock_quantity ?? 0)],
                $companyId,
                $outletId,
                $userId
            );
        } catch (\Throwable $e) {
            Log::error('StockLedgerService::reconcileByCode failed', ['code' => $code, 'error' => $e->getMessage()]);
        }
    }
}
