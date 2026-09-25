<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\BusyNotifyService;
use App\Services\StockLedgerService;

/**
 * busy:diagnose
 *
 * Poore Busy → cloud → software stock pipeline ki health check.
 * Har red flag ke saath batata hai kya galat hai aur kya karna hai.
 *
 * Usage:
 *   php artisan busy:diagnose
 *   php artisan busy:diagnose --company=1 --api  (Busy API se live compare)
 *   php artisan busy:diagnose --fix              (ledger/view heal kar do)
 */
class BusyDiagnose extends Command
{
    protected $signature = 'busy:diagnose
                            {--company=1 : Company id}
                            {--api : BusyNotify API se live stock compare karo}
                            {--fix : Ledger mismatch ko reconcile + view rebuild se theek karo}
                            {--limit=15 : Kitne mismatch rows dikhane hain}';
    protected $description = 'Diagnose Busy → cloud → software stock pipeline (finds why stock shows 0)';

    /** @var array<int,array{level:string,msg:string}> */
    private array $report = [];

    public function handle(): int
    {
        $companyId = (int) $this->option('company');
        $limit     = (int) $this->option('limit');

        $this->line('');
        $this->line('══ Busy → Cloud → Software — stock pipeline diagnosis ══');
        $this->line('');

        $this->checkConfig($companyId);
        $apiStock = $this->option('api') ? $this->checkApi($companyId) : null;
        $mismatches = $this->checkDatabase($companyId, $limit);
        $this->checkView($companyId);

        if ($this->option('fix')) {
            $this->runFix($companyId, $mismatches);
        } else {
            $this->newLine();
            $this->line('Tip: ledger mismatch theek karne ke liye → php artisan busy:diagnose --company=' . $companyId . ' --fix');
        }

        if ($apiStock === null) {
            $this->newLine();
            $this->line('Busy API live compare chahiye to → php artisan busy:diagnose --api');
        }

        return 0;
    }

    private function checkConfig(int $companyId): void
    {
        $this->info('1) CONFIG');

        $enabled = BusyNotifyService::isEnabled();
        $this->line('   BusyNotify configured : ' . ($enabled ? 'YES' : 'NO (BUSYNOTIFY_API_KEY / companies.busynotify_token missing)'));

        try {
            $row = DB::table('companies')->where('id', $companyId)
                ->select('busynotify_token', 'busynotify_company_id')->first();
            $this->line('   DB token (company ' . $companyId . ') : '
                . ($row && $row->busynotify_token ? 'set (....' . substr($row->busynotify_token, -4) . ')' : 'NOT set'));
            $this->line('   DB busynotify_company_id : ' . (($row->busynotify_company_id ?? null) ?: 'auto-detect'));
        } catch (\Throwable $e) {
            $this->warn('   companies table read failed: ' . $e->getMessage());
        }

        $this->line('   financial_year        : ' . config('services.busynotify.financial_year', date('Y')));
        $this->line('   scheduler             : crontab me `php artisan schedule:run` har minute hona chahiye');
        $this->line('     (Docker/cPanel par cron na ho to busy:import + busy:sync-stock KABHI nahi chalte)');
        $this->newLine();
    }

    /**
     * Busy API se live stock la kar cloud items se compare.
     * @return array<string,float>|null  busy_id/code => api stock
     */
    private function checkApi(int $companyId): ?array
    {
        $this->info('2) BUSY API (live)');

        try {
            $service = new BusyNotifyService();
            $rows = $service->getProductsStockOnly();
            $meta = $service->getLastMeta();

            if (!$rows) {
                $this->error('   API ne 0 products bheje — token/companyId/financialYear check karo.');
                $this->newLine();
                return null;
            }

            $expected = isset($meta['rowCount']) ? (int) $meta['rowCount'] : null;
            $withField = 0;
            $positive = 0;
            $map = [];
            foreach ($rows as $r) {
                if ($r['product_stock'] === null) continue;
                $withField++;
                $stock = (float) $r['product_stock'];
                if ($stock > 0) $positive++;
                $map[($r['product_id'] !== null ? 'b:' . $r['product_id'] : 'c:' . $r['product_alias'])] = $stock;
            }

            $this->line('   rows received         : ' . count($rows));
            $this->line('   metadata.rowCount     : ' . ($expected ?? 'n/a'));
            if ($expected !== null && $expected > count($rows)) {
                $this->error('   ⚠ TRUNCATED/paginated response — sirf ' . count($rows) . '/' . $expected
                    . ' products aa rahe. Baaki products ka stock kabhi sync nahi hoga!');
            }
            $this->line('   rows with stock field : ' . $withField . ' / ' . count($rows));
            if ($withField === 0) {
                $this->error('   ⚠ API `product_stock` field hi nahi bhej rahi — stock kabhi sync nahi hoga'
                    . ' (busy:sync-stock ab isse 0 nahi likhta, isliye purana stock safe rehta hai).');
            }
            $this->line('   products with stock>0 : ' . $positive);

            $this->newLine();
            return $map;
        } catch (\Throwable $e) {
            $this->error('   API call failed: ' . $e->getMessage());
            $this->newLine();
            return null;
        }
    }

    /**
     * items.stock_quantity vs ledger(view ke sources) vs view rows.
     * @return array<int,array{item:object,ledger:float,view:float}>
     */
    private function checkDatabase(int $companyId, int $limit): array
    {
        $this->info('3) CLOUD DB');

        try {
            $items = DB::table('items')
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->get(['id', 'code', 'name', 'busy_id', 'stock_quantity']);
        } catch (\Throwable $e) {
            $this->error('   items read failed: ' . $e->getMessage());
            return [];
        }

        $total = $items->count();
        $zero = $items->filter(fn($i) => (float) ($i->stock_quantity ?? 0) <= 0.001)->count();
        $withBusyId = $items->filter(fn($i) => $i->busy_id !== null && $i->busy_id !== '')->count();

        $this->line('   items (Live)          : ' . $total);
        $this->line('   with busy_id          : ' . $withBusyId
            . ($withBusyId < $total ? '  ⚠ ' . ($total - $withBusyId) . ' items bina busy_id — Busy sync unhe chhodega' : ''));
        $this->line('   stock = 0             : ' . $zero . ' / ' . $total);

        if ($total === 0) {
            $this->warn('   Koi item nahi — pehle busy:import chalao.');
            $this->newLine();
            return [];
        }

        // Ledger totals (view ke exact sources se)
        $ledger = StockLedgerService::ledgerTotals($items->pluck('id')->all());

        // view_stock_detail se actual sums
        try {
            $viewRows = DB::table('view_stock_detail')
                ->where('company_id', $companyId)
                ->selectRaw("item_id, SUM(CASE WHEN type=1 THEN IFNULL(stock_quantity,0) ELSE -IFNULL(stock_quantity,0) END) AS qty")
                ->groupBy('item_id')
                ->pluck('qty', 'item_id');
        } catch (\Throwable $e) {
            $viewRows = collect();
            $this->warn('   view_stock_detail read failed: ' . $e->getMessage());
        }

        $mismatch = [];
        foreach ($items as $it) {
            $stock = (float) ($it->stock_quantity ?? 0);
            $led   = (float) ($ledger[$it->id] ?? 0);
            if (abs($stock - $led) > 0.01) {
                $mismatch[] = ['item' => $it, 'ledger' => $led, 'view' => (float) ($viewRows[$it->id] ?? 0)];
            }
        }

        $this->line('   items vs ledger mismatch : ' . count($mismatch)
            . '  ⚠ inhi items ka software/reports me stock 0 ya galat dikhega');

        if ($mismatch) {
            $this->newLine();
            $this->table(
                ['busy_id', 'code', 'name', 'items.stock', 'ledger', 'view'],
                array_slice(array_map(fn($m) => [
                    $m['item']->busy_id ?? '-',
                    $m['item']->code ?? '-',
                    mb_substr((string) $m['item']->name, 0, 30),
                    number_format((float) ($m['item']->stock_quantity ?? 0), 2),
                    number_format($m['ledger'], 2),
                    number_format($m['view'], 2),
                ], $mismatch), 0, $limit)
            );
        }

        $this->newLine();
        return $mismatch;
    }

    private function checkView(int $companyId): void
    {
        $this->info('4) VIEW (view_stock_detail — reports/software isise padhte hain)');

        try {
            $viewRows = (int) DB::table('view_stock_detail')->count();
            $itemsWithStock = (int) DB::table('items')
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('stock_quantity', '>', 0)
                ->count();

            $this->line('   rows in view          : ' . $viewRows);
            $this->line('   items with stock > 0  : ' . $itemsWithStock);

            if ($viewRows === 0 && $itemsWithStock > 0) {
                $this->error('   🔴 VIEW KHAALI HAI — ye "sab products ka stock 0" dikhne ka sabse bada karan hai.'
                    . ' Rebuild: php artisan busy:diagnose --fix (ya busy:import / busy:sync-stock chalao).');
            } elseif ($viewRows === 0) {
                $this->warn('   View khali hai (par stock wale items bhi nahi) — busy:import chalao.');
            } else {
                $this->line('   status                : OK');
            }
        } catch (\Throwable $e) {
            $this->error('   view read failed: ' . $e->getMessage());
        }

        $this->newLine();
    }

    private function runFix(int $companyId, array $mismatches): void
    {
        $this->info('5) FIX');

        if (!$mismatches) {
            $this->line('   Ledger pehle se barabar hai — sirf view rebuild ho raha hai.');
        } else {
            $desired = [];
            foreach ($mismatches as $m) {
                $desired[(int) $m['item']->id] = (float) ($m['item']->stock_quantity ?? 0);
            }
            $stats = StockLedgerService::reconcileMany($desired, $companyId, 1, 1);
            $this->line("   Reconcile: {$stats['adjusted']} SYNC_ADJ rows updated ({$stats['unchanged']} already ok)");
        }

        try {
            StockLedgerService::rebuild();
            $this->info('   view_stock_detail rebuilt (atomic) ✅');
        } catch (\Throwable $e) {
            $this->error('   rebuild failed: ' . $e->getMessage());
        }
    }
}
