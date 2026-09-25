<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BusyNotifyService;
use App\Services\StockLedgerService;

/**
 * busy:import — BusyNotify se full (customers + products) cloud DB me import.
 *
 * Products ke saath stock bhi aata hai. Import ke baad:
 *   • items.stock_quantity  = Busy ka stock
 *   • set_opening_stocks    = Busy opening row (existing behavior)
 *   • SYNC_ADJ reconcile    = ledger (view_stock_detail) ko EXACTLY stock ke
 *     barabar laata hai — warna reports 0/stale dikhati thin (zero-stock bug)
 *   • view_stock_detail     = atomic rebuild (TRUNCATE+INSERT nahi — wo fail
 *     hone par poora view khaali chhod jata tha)
 *
 * Guards: stock field missing = skip (0 nahi), mass-zero protection,
 * metadata.rowCount vs received rows check.
 */
class BusyNotifyImport extends Command
{
    protected $signature = 'busy:import
                            {--type=all : all|customers|products}
                            {--company=1 : Company id}
                            {--outlet= : Outlet id for opening/adjustment rows (default 1)}
                            {--user=1 : User id for created rows}';
    protected $description = 'Import data from BusyNotify API into cloud DB';

    public function handle(): int
    {
        if (!BusyNotifyService::isEnabled()) {
            $this->error('BUSYNOTIFY_API_KEY not set in .env');
            return 1;
        }

        $type = $this->option('type');
        $companyId = (int) $this->option('company');
        $userId    = (int) $this->option('user');
        $outletId  = (int) ($this->option('outlet') ?: 1);
        $now = now()->toDateTimeString();
        $service = new BusyNotifyService();

        if ($type === 'all' || $type === 'customers') {
            $this->importCustomers($service, $companyId, $userId, $now);
        }

        if ($type === 'all' || $type === 'products') {
            $this->importProducts($service, $companyId, $userId, $outletId, $now);
        }

        return 0;
    }

    /**
     * FIX #3: Busy customer state text → states.state_id
     * (state_name lookup — GST/interstate logic ke liye zaroori).
     */
    private function resolveStateId(?string $stateText): ?int
    {
        $stateText = trim((string) $stateText);
        if ($stateText === '') return null;

        static $cache = [];
        $key = mb_strtolower($stateText);
        if (isset($cache[$key])) return $cache[$key];

        $id = DB::table('states')
            ->where('state_name', 'LIKE', $stateText)
            ->value('id');

        return $cache[$key] = $id ? (int) $id : null;
    }

    private function importCustomers(BusyNotifyService $service, int $companyId, int $userId, string $now): void
    {
        $this->info('Fetching customers from BusyNotify...');
        $data = $service->getCustomers();
        $this->info('Fetched: ' . count($data));

        $skipGroups = ['Bank Accounts','Duties & Taxes','Expenses (Indirect/Admn.)','Income (Indirect)','Current Assets','Fixed Assets','Unsecured Loans','PAIDUP CAPITAL'];
        $imported = $updated = $skipped = 0;

        foreach ($data as $row) {
            if (in_array($row['group_name'] ?? '', $skipGroups)) { $skipped++; continue; }
            $mapped = BusyNotifyService::mapCustomer($row);
            if (empty($mapped['name'])) { $skipped++; continue; }

            // FIX #3: state text → states.state_id (GST interstate logic)
            $stateId = $this->resolveStateId($row['state'] ?? $row['state_name'] ?? null);
            if ($stateId) {
                $mapped['state_id'] = $stateId;
            }

            $mapped += ['company_id'=>$companyId,'user_id'=>$userId,'updated_at'=>$now];

            $existing = null;
            if (!empty($mapped['busy_id'])) $existing = DB::table('customers')->where('busy_id',$mapped['busy_id'])->where('company_id',$companyId)->first();
            if (!$existing && !empty($mapped['phone'])) $existing = DB::table('customers')->where('phone',$mapped['phone'])->where('company_id',$companyId)->where('del_status','Live')->first();
            if (!$existing && !empty($mapped['name'])) $existing = DB::table('customers')->where('name',$mapped['name'])->where('company_id',$companyId)->where('del_status','Live')->first();

            if ($existing) { DB::table('customers')->where('id',$existing->id)->update(array_filter($mapped,fn($v)=>$v!==null)); $updated++; }
            else { $mapped += ['del_status'=>'Live','created_at'=>$now]; DB::table('customers')->insert($mapped); $imported++; }
        }

        $this->info("Customers: $imported new, $updated updated, $skipped skipped");
    }

    /** Busy text unit ("Pcs.") → units table ID (auto-create if missing).
     * items.unit_type ko numeric string set karta hai (desktop conversion
     * logic "2" jaisi ID string expect karta hai) + sale_unit_id bhi.
     */
    private function resolveUnit(string $companyId, int $userId, string $now, ?string $unitText): ?int
    {
        $unitText = trim((string) $unitText);
        if ($unitText === '') return null;

        static $cache = [];
        $key = $companyId . '|' . mb_strtolower($unitText);
        if (isset($cache[$key])) return $cache[$key];

        $id = DB::table('units')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where(function ($q) use ($unitText) {
                $q->where('name', $unitText)->orWhere('short_name', $unitText)->orWhere('unit_name', $unitText);
            })
            ->value('id');

        if (!$id) {
            $id = DB::table('units')->insertGetId([
                'name' => $unitText,
                'short_name' => mb_substr($unitText, 0, 10),
                'unit_name' => $unitText,
                'company_id' => $companyId,
                'user_id' => $userId,
                'del_status' => 'Live',
                'base_unit_id' => null,
                'conversion_rate' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $cache[$key] = (int) $id;
    }

    /** FIX #2: Busy product_group_name → item_categories.category_id
     * (lookup by name, auto-create if missing — items category_id me set hota hai).
     */
    private function resolveCategory(string $companyId, int $userId, string $now, ?string $groupName): ?int
    {
        $groupName = trim((string) $groupName);
        if ($groupName === '') return null;

        static $cache = [];
        $key = $companyId . '|' . mb_strtolower($groupName);
        if (isset($cache[$key])) return $cache[$key];

        $id = DB::table('item_categories')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('name', $groupName)
            ->value('id');

        if (!$id) {
            $id = DB::table('item_categories')->insertGetId([
                'name' => $groupName,
                'description' => 'BusyNotify import (product group)',
                'sort_id' => 0,
                'company_id' => $companyId,
                'user_id' => $userId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $cache[$key] = (int) $id;
    }

    private function importProducts(BusyNotifyService $service, int $companyId, int $userId, int $outletId, string $now): void
    {
        $this->info('Fetching products from BusyNotify...');
        $data = $service->getProducts();
        $this->info('Fetched: ' . count($data));

        // ── Response sanity: truncated/paginated response to nahi ──
        $meta = $service->getLastMeta();
        $expected = isset($meta['rowCount']) ? (int) $meta['rowCount'] : null;
        if ($expected !== null && $expected > count($data)) {
            $this->warn("TRUNCATED response: received " . count($data) . " of {$expected} products (metadata.rowCount) — partial import!");
            Log::warning('busy:import: truncated product response', ['received' => count($data), 'expected' => $expected]);
        }

        // Pre-load maps (stock ke saath — mass-zero guard ke liye purana stock chahiye)
        $existingItems = DB::table('items')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->get(['id', 'busy_id', 'code', 'name', 'stock_quantity']);
        $byBusyId = [];
        $byCode   = [];
        $byName   = [];
        foreach ($existingItems as $it) {
            if ($it->busy_id !== null && $it->busy_id !== '' && !isset($byBusyId[$it->busy_id])) {
                $byBusyId[$it->busy_id] = $it;
            }
            if (!empty($it->code) && !isset($byCode[$it->code])) $byCode[$it->code] = $it;
            if (!empty($it->name) && !isset($byName[$it->name])) $byName[$it->name] = $it;
        }
        $exStocks = DB::table('set_opening_stocks')->where('company_id',$companyId)->where('item_description','BusyNotify Import')->pluck('id','item_id');

        $updateMap=[]; $insertRows=[]; $stockCase=[]; $newStocks=[]; $desired=[];
        $imported=0; $updated=0; $skipped=0; $unknown=0;
        $wasPositive=0; $posToZero=0; $zeroBlocked=0;

        foreach ($data as $row) {
            $mapped = BusyNotifyService::mapProduct($row);
            if (empty($mapped['name'])) { $skipped++; continue; }

            // FIX: unit text ("Pcs.") ko units-table ID se resolve karo —
            // items.unit_type numeric ID string expect karta hai (desktop compat)
            $unitId = $this->resolveUnit($companyId, $userId, $now, $row['product_unit'] ?? null);
            if ($unitId) {
                $mapped['unit_type'] = (string) $unitId;
                $mapped['sale_unit_id'] = $unitId;
            }

            // FIX #2: product group → category (auto-create)
            $catId = $this->resolveCategory($companyId, $userId, $now, $row['product_group_name'] ?? null);
            if ($catId) {
                $mapped['category_id'] = $catId;
            }

            $mapped += ['company_id'=>$companyId,'user_id'=>$userId,'updated_at'=>$now];

            // GUARD: stock field missing/null = "pata nahi" — 0 kabhi mat likho.
            $rawStock = $row['product_stock'] ?? null;
            $hasStock = $rawStock !== null && $rawStock !== '';
            $stock = $hasStock ? (float) $rawStock : null;
            if (!$hasStock) $unknown++;

            $existingItem = null;
            if (!empty($mapped['busy_id'])) $existingItem = $byBusyId[$mapped['busy_id']] ?? null;
            if (!$existingItem && !empty($mapped['code']) && $mapped['code']!==$mapped['name']) $existingItem = $byCode[$mapped['code']] ?? null;
            if (!$existingItem && !empty($mapped['name'])) $existingItem = $byName[$mapped['name']] ?? null;

            if ($existingItem) {
                $existingId = (int) $existingItem->id;
                $updateMap[$existingId] = ['data'=>$mapped,'stock'=>$stock];

                if ($stock !== null) {
                    $oldStock = (float) ($existingItem->stock_quantity ?? 0);
                    if ($oldStock > 0.001) {
                        $wasPositive++;
                        if ($stock <= 0.001) $posToZero++;
                    }
                    $stockCase[$existingId] = $stock;
                    $desired[$existingId]    = $stock;
                    if (!isset($exStocks[$existingId]) && $stock>0)
                        $newStocks[] = ['item_id'=>$existingId,'item_type'=>'opening','item_description'=>'BusyNotify Import','stock_quantity'=>$stock,'outlet_id'=>$outletId,'user_id'=>$userId,'company_id'=>$companyId,'created_at'=>$now,'updated_at'=>$now];
                }
                $updated++;
            } else {
                $mapped += ['del_status'=>'Live','created_at'=>$now,'enable_disable_status'=>1,'stock_quantity'=>$stock ?? 0];
                $mapped['_stock']=$stock; $insertRows[]=$mapped; $imported++;
            }
        }

        // ── GUARD: mass-zero protection ──
        // Pehle positive stock wali ~90%+ items ek saath 0 aa rahi hain to Busy
        // side/galat company/field change ka signal hai — zero-writes block karo.
        if ($wasPositive >= 10 && $posToZero >= (int) ceil(0.9 * $wasPositive)) {
            foreach ($stockCase as $id => $v) {
                if ($v <= 0.001) {
                    unset($stockCase[$id], $desired[$id]);
                    $updateMap[$id]['stock'] = null; // stock CASE me shamil mat karo
                    $zeroBlocked++;
                }
            }
            $msg = "MASS-ZERO GUARD: {$posToZero}/{$wasPositive} positive-stock items returned 0 from Busy — blocked {$zeroBlocked} zero-writes. Check token/companyId/financialYear.";
            $this->error($msg);
            Log::error('busy:import: ' . $msg);
        }
        if ($unknown > 0) {
            $this->warn("{$unknown} products had no product_stock field — their stock left untouched");
        }

        $this->info("Building update queries...");

        // Batch UPDATE via CASE (stock column sirf tab jab stock known ho)
        foreach (array_chunk($updateMap,2000,true) as $chunk) {
            $ids=implode(',', array_map('intval', array_keys($chunk)));
            $n=$p=$pp=$m_=$s=$b=$cdCases='';
            $hasStockCase = false;
            foreach ($chunk as $id=>$info) {
                $m=$info['data']; $nm=str_replace("'","''",$m['name']??'');
                $n.=" WHEN $id THEN '$nm'";
                $p.=" WHEN $id THEN ".(float)($m['sale_price']??0);
                $pp.=" WHEN $id THEN ".(float)($m['purchase_price']??0);
                $m_.=" WHEN $id THEN ".(float)($m['mrp_price']??0);
                if ($info['stock'] !== null) {
                    $s.=" WHEN $id THEN ".(float) $info['stock'];
                    $hasStockCase = true;
                }
                $bid=(int)($m['busy_id']??0);
                $b.=" WHEN $id THEN ".($bid>0?$bid:'busy_id');
                // Alias (barcode) se code backfill — sirf placeholder codes pe
                if (!empty($m['code']) && $m['code']!==$m['name']) {
                    $cd=str_replace("'","''",$m['code']);
                    $cdCases.=" WHEN $id THEN '$cd'";
                }
            }
            $codeSql = $cdCases !== ''
                ? "`code`=CASE WHEN (`code` IS NULL OR `code`='' OR `code`=`name`) THEN CASE `id`$cdCases ELSE `code` END ELSE `code` END,"
                : "";
            // CASE me sirf known-stock rows hain — baaki items (unknown/blocked)
            // ELSE branch se apne current stock_quantity par rehte hain, 0 nahi jaate.
            $stockSql = $hasStockCase
                ? "`stock_quantity`=CASE `id`$s ELSE `stock_quantity` END,"
                : '';
            DB::statement("UPDATE `items` SET `name`=CASE `id`$n ELSE `name` END,$codeSql`sale_price`=CASE `id`$p ELSE `sale_price` END,`purchase_price`=CASE `id`$pp ELSE `purchase_price` END,`mrp_price`=CASE `id`$m_ ELSE `mrp_price` END,$stockSql`busy_id`=CASE `id`$b ELSE `busy_id` END,`updated_at`='$now' WHERE `id` IN($ids) AND `company_id`=$companyId");
        }

        // Batch UPDATE stocks (BusyNotify opening rows — sirf known stock)
        foreach (array_chunk($stockCase,2000,true) as $chunk) {
            $ids=implode(',', array_map('intval', array_keys($chunk))); $c='';
            foreach ($chunk as $id=>$qty) $c.=" WHEN $id THEN ".(float)$qty;
            DB::statement("UPDATE `set_opening_stocks` SET `stock_quantity`=CASE `item_id`$c ELSE `stock_quantity` END,`updated_at`='$now' WHERE `item_id` IN($ids) AND `company_id`=$companyId AND `item_description`='BusyNotify Import'");
        }

        // Batch INSERT new items
        foreach (array_chunk($insertRows,200) as $chunk) {
            $rows=array_map(fn($r)=>['row'=>array_diff_key($r,['_stock'=>1]),'stock'=>$r['_stock']],$chunk);
            DB::table('items')->insert(array_column($rows,'row'));
            foreach ($rows as $r) {
                $m=$r['row'];
                $newId=!empty($m['busy_id'])?DB::table('items')->where('busy_id',$m['busy_id'])->where('company_id',$companyId)->value('id'):null;
                if (!$newId && !empty($m['code'])) $newId=DB::table('items')->where('code',$m['code'])->where('company_id',$companyId)->value('id');
                if ($newId && $r['stock'] !== null) $desired[(int)$newId] = (float) $r['stock'];
                if ($r['stock']===null || $r['stock']<=0) continue;
                if ($newId) $newStocks[]=['item_id'=>$newId,'item_type'=>'opening','item_description'=>'BusyNotify Import','stock_quantity'=>$r['stock'],'outlet_id'=>$outletId,'user_id'=>$userId,'company_id'=>$companyId,'created_at'=>$now,'updated_at'=>$now];
            }
        }
        foreach (array_chunk($newStocks,500) as $c) DB::table('set_opening_stocks')->insert($c);

        // ── LEDGER SYNC + ATOMIC VIEW REBUILD (zero-stock fix) ──
        // Har item ka view_stock_detail ledger exactly items.stock_quantity ke
        // barabar ho jata hai (SYNC_ADJ delta), phir view atomic rebuild hota hai.
        if ($desired) {
            $stats = StockLedgerService::reconcileMany($desired, $companyId, $outletId, $userId);
            $this->line("Ledger reconcile: {$stats['adjusted']} adjustment rows ({$stats['unchanged']} already correct)");
        }
        StockLedgerService::rebuild();
        $this->line('view_stock_detail rebuilt (atomic)');

        $this->info("Items: $imported new, $updated updated, $skipped skipped");
    }
}
