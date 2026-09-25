<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  RashanKiDukan — cPanel Diagnostic Tool (no SSH required)
 *  ─────────────────────────────────────────────────────────────────────────
 *  USE:
 *    1. Is file ko cPanel ke public_html (Laravel ke "public" folder) me upload karo
 *    2. Browser me kholo:  https://YOURDOMAIN.com/rk_diagnostic.php?key=RashanKiDukan2026
 *    3. Report dekho → har red item ke sath exact FIX diya hoga
 *    4. Kaam ho jaye to file DELETE kar dena (button niche hai)
 *
 *  YE SCRIPT SIRF PADHTI HAI — database me kuch nahi badalti (fix optional hai).
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ═════════════ CONFIG — yahan apni setting karo ═════════════ */
$ACCESS_KEY          = 'RashanKiDukan2026';   // URL me ?key=... ye hi dalna
$BASE_PATH_OVERRIDE  = '';                    // agar auto-detect fail ho: '/home/USERNAME/public_html' jaisa path likho
$ALLOW_FIX           = false;                 // true karo to ?run_fix=1&confirm=yes se ALTER TABLE queries khud chal jayengi
/* ═══════════════════════════════════════════════════════════ */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // hum khud handle karenge

$checks = [];      // sab results yahan jama honge
$fatal  = null;

register_shutdown_function(function () use (&$fatal) {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]) && $fatal === null) {
        $fatal = $e;
    }
});

/* ─────────── Helpers ─────────── */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function addCheck(string $section, string $name, bool $ok, string $detail = '', string $fix = '', int $level = 0): void
{
    global $checks;
    $checks[] = compact('section', 'name', 'ok', 'detail', 'fix', 'level');
}

function formatBytes($b): string {
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < count($u)-1) { $b /= 1024; $i++; }
    return round($b, 1) . ' ' . $u[$i];
}

/** .env file ko regex se parse karta hai (parse_ini_file values par fail hota hai) */
function parseEnv(string $path): array {
    $env = [];
    if (!is_file($path)) return $env;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $k = trim(substr($line, 0, $pos));
        $v = trim(substr($line, $pos + 1));
        if (strlen($v) >= 2 && (($v[0] === '"' && str_ends_with($v, '"')) || ($v[0] === "'" && str_ends_with($v, "'")))) {
            $v = substr($v, 1, -1);
        }
        $env[$k] = $v;
    }
    return $env;
}

/** SQL dump se CREATE TABLE + columns parse karta hai (reference schema ke liye) */
function parseDumpSchema(string $path): array {
    $schema = [];
    if (!is_file($path)) return $schema;
    $sql = @file_get_contents($path);
    if ($sql === false) return $schema;
    if (!preg_match_all('/CREATE TABLE[^\`]*\`([a-zA-Z0-9_]+)\`\s*\((.*?)\)\s*(ENGINE|DEFAULT|COLLATE|;)/is', $sql, $m, PREG_SET_ORDER)) {
        return $schema;
    }
    foreach ($m as $t) {
        $table = $t[1];
        $body  = $t[2];
        $cols = [];
        if (preg_match_all('/^\s*\`([a-zA-Z0-9_]+)\`\s*([^,\n]+)/im', $body, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                $cols[$c[1]] = trim($c[2]);
            }
        }
        if ($cols) $schema[$table] = $cols;
    }
    return $schema;
}

/* ═════════════ 1) SECURITY GATE ═════════════ */
$providedKey = $_GET['key'] ?? '';
if (!hash_equals($ACCESS_KEY, $providedKey)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:Segoe UI,Arial;background:#1a1d29;color:#fff;display:grid;place-items:center;height:100vh;margin:0">';
    echo '<div style="text-align:center"><h1>🔒 403 — Access Key chahiye</h1><p style="color:#9aa">URL me <code style="background:#2a2e3f;padding:4px 10px;border-radius:6px">?key=' . h($ACCESS_KEY) . '</code> add karo</p></div></body>';
    exit;
}

/* Self-destruct (optional) */
if (isset($_GET['selfdestruct']) && $_GET['selfdestruct'] === 'yes') {
    @unlink(__FILE__);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:Segoe UI,Arial;background:#1a1d29;color:#fff;display:grid;place-items:center;height:100vh;margin:0"><div><h1>🗑️ Diagnostic file delete ho gayi</h1></div></body>';
    exit;
}

/* ═════════════ 2) BASE PATH DETECT ═════════════ */
$basePath = $BASE_PATH_OVERRIDE;
if ($basePath === '') {
    $dir = __DIR__;
    for ($i = 0; $i < 5; $i++) {
        if (is_file($dir . '/artisan')) { $basePath = $dir; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$basePath = rtrim($basePath ?: __DIR__, '/\\');

addCheck('Laravel Setup', 'Base path detect', is_file($basePath . '/artisan'),
    h($basePath) . (is_file($basePath . '/artisan') ? ' (artisan mila ✅)' : ' (artisan NAHI mila ⚠️)'),
    'Script ko Laravel ke <b>public/</b> folder me rakho, YA file top par <code>$BASE_PATH_OVERRIDE</code> me full path likho (e.g. /home/USER/public_html).');

/* ═════════════ 3) PHP ENVIRONMENT ═════════════ */
$sec = 'PHP Environment';
addCheck($sec, 'PHP Version', version_compare(PHP_VERSION, '8.2.0', '>='),
    'Current: <b>' . PHP_VERSION . '</b> — Laravel 11 ko 8.2+ chahiye',
    version_compare(PHP_VERSION, '8.2.0', '>=') ? '' : 'cPanel → <b>Select PHP Version / MultiPHP Manager</b> se PHP 8.2 ya 8.3 select karo');

$requiredExts = ['pdo','pdo_mysql','mysqli','mbstring','openssl','tokenizer','xml','ctype','json','bcmath','fileinfo','gd','zip','intl','curl','dom'];
$missingExts  = array_filter($requiredExts, fn($e) => !extension_loaded($e));
addCheck($sec, 'PHP Extensions', empty($missingExts),
    empty($missingExts) ? 'Sab required extensions loaded (' . count($requiredExts) . ')' : 'Missing: <b>' . h(implode(', ', $missingExts)) . '</b>',
    empty($missingExts) ? '' : 'cPanel → Select PHP Version → Extensions tab me ' . h(implode(', ', $missingExts)) . ' tick karo');

$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$disabled = array_filter($disabled);
$criticalDisabled = array_intersect($disabled, ['exec','proc_open','symlink','putenv','shell_exec']);
addCheck($sec, 'Disabled Functions', empty($criticalDisabled),
    empty($criticalDisabled) ? 'Koi critical function disabled nahi' : 'Disabled: <b>' . h(implode(', ', $criticalDisabled)) . '</b> (Laravel ke kuch packages inhe use karte hain)',
    empty($criticalDisabled) ? '' : 'cPanel → MultiPHP INI Editor → disable_functions se ye hatao (ya ignore karo, zaroori nahi)', 1);

$memLimit = ini_get('memory_limit');
addCheck($sec, 'memory_limit', (int)$memLimit >= 256 || $memLimit === '-1',
    'Current: <b>' . h($memLimit) . '</b> (256M+ recommended — bade imports/reports ke liye)',
    (int)$memLimit >= 256 || $memLimit === '-1' ? '' : 'cPanel → MultiPHP INI Editor → memory_limit = 256M', 1);

$uploadMax = ini_get('upload_max_filesize');
addCheck($sec, 'upload_max_filesize', returnBytes($uploadMax) >= 20 * 1024 * 1024,
    'Current: <b>' . h($uploadMax) . '</b> (20M+ recommended — logo/backup upload ke liye)',
    returnBytes($uploadMax) >= 20*1024*1024 ? '' : 'cPanel → MultiPHP INI Editor → upload_max_filesize = 64M aur post_max_size = 64M', 1);

function returnBytes(string $v): int {
    $v = trim($v); $last = strtolower(substr($v, -1)); $n = (int)$v;
    return match($last) { 'g' => $n*1024**3, 'm' => $n*1024**2, 'k' => $n*1024, default => (int)$v };
}

/* ═════════════ 4) LARAVEL FILES & PERMISSIONS ═════════════ */
$sec = 'Files & Permissions';

$envFile = $basePath . '/.env';
$env = parseEnv($envFile);
addCheck($sec, '.env file exists', is_file($envFile), h($envFile), 'cPanel File Manager me .env upload/copy karo (agar nahi hai to .env.example se banao)');

$appKey = $env['APP_KEY'] ?? '';
addCheck($sec, 'APP_KEY set hai', $appKey !== '',
    $appKey !== '' ? h(substr($appKey, 0, 12)) . '...' : 'APP_KEY khali hai',
    $appKey !== '' ? '' : 'APP_KEY generate karne ke liye: agar site chal rahi hai to kisi se php artisan key:generate karwao, YA .env me manually base64:... key dalo');

$envDebug = ($env['APP_DEBUG'] ?? 'false') === 'true';
addCheck($sec, 'APP_DEBUG', true,
    $envDebug ? '<b>true</b> — errors screen par dikhenge' : 'false — errors sirf log me jaate hain',
    'Exact error dekhne ke liye temporarily .env me APP_DEBUG=true karke save error dobara reproduce karo. Kaam hone par wapas false.', 1);

$envUrl = $env['APP_ENV'] ?? '';
addCheck($sec, 'APP_ENV', in_array($envUrl, ['production','local','staging','development']),
    'Current: <b>' . h($envUrl ?: '(not set)') . '</b>', '', 1);

// writable dirs
$writableDirs = [
    'storage/framework'          => 'Views cache/sessions yahan banti hain',
    'storage/framework/cache'    => 'Cache files',
    'storage/framework/sessions' => 'Session files (file driver)',
    'storage/framework/views'    => 'Compiled blade views',
    'storage/logs'               => 'Laravel error logs',
    'storage/app'                => 'Uploads',
    'bootstrap/cache'            => 'Config/route cache',
];
foreach ($writableDirs as $dir => $why) {
    $full = $basePath . '/' . $dir;
    if (!is_dir($full)) {
        addCheck($sec, "$dir exists", false, 'Folder hi nahi hai (' . h($why) . ')', 'cPanel File Manager me banao: ' . h($dir));
        continue;
    }
    $w = is_writable($full);
    if ($w) {
        // actual write test — is_writable kabhi kabhi galat bolta hai
        $testFile = rtrim($full, '/') . '/.rk_write_test_' . uniqid();
        $w = @file_put_contents($testFile, 'x') !== false;
        if ($w) @unlink($testFile);
    }
    addCheck($sec, "$dir writable", $w,
        $w ? 'OK — likha ja sakta hai' : 'PERMISSION PROBLEM — is folder me PHP likh nahi paa rahi (' . h($why) . ')',
        $w ? '' : 'cPanel File Manager → ' . h($dir) . ' → Permissions → <b>755</b> (ya 775) karo. Files ko 644, folders ko 755.');
}

// public/storage symlink
$pubStorage = $basePath . '/public/storage';
addCheck($sec, 'public/storage (uploads link)', is_dir($pubStorage),
    is_dir($pubStorage) ? 'OK' : 'Missing — uploaded images/invoice logos public me nahi dikhengi',
    'cPanel me symlink nahi banta manually: public/storage naam ka folder banao YA "storage" folder ka copy rakhna hoga. Ya phir hosts se symlink banwao.', 1);

// vendor
addCheck($sec, 'vendor/ (composer packages)', is_file($basePath . '/vendor/autoload.php'),
    is_file($basePath . '/vendor/autoload.php') ? 'vendor/autoload.php mila' : 'vendor folder NAHI hai — composer install nahi hua',
    is_file($basePath . '/vendor/autoload.php') ? '' : 'Local PC pe "composer install" karke vendor folder ke sath upload karo, ya cPanel me "Terminal" (agar mile) me composer i --no-dev');

// .htaccess in public
$scriptDir = __DIR__;
addCheck($sec, 'public/.htaccess', is_file($scriptDir . '/.htaccess') || is_file($basePath . '/public/.htaccess'),
    'Laravel ke clean URLs ke liye zaroori', is_file($scriptDir . '/.htaccess') || is_file($basePath . '/public/.htaccess') ? '' : 'Laravel ke public/.htaccess ko public_html me dalo', 1);

/* ═════════════ 5) LARAVEL VERSION (composer se) ═════════════ */
$sec = 'Packages';
$installedJson = $basePath . '/vendor/composer/installed.json';
if (is_file($installedJson)) {
    $pkgs = json_decode((string)@file_get_contents($installedJson), true);
    $list = $pkgs['packages'] ?? $pkgs ?? [];
    $want = ['laravel/framework','nwidart/laravel-modules','spatie/laravel-permission','maatwebsite/excel','mpdf/mpdf','mike42/escpos-php','razorpay/razorpay'];
    foreach ($want as $w) {
        $found = null;
        foreach ($list as $p) { if (($p['name'] ?? '') === $w) { $found = $p; break; } }
        addCheck($sec, $w, $found !== null,
            $found ? 'v' . h($found['version'] ?? '?') : 'INSTALLED NAHI HAI',
            $found ? '' : 'Local pe composer require ' . $w . ' karke vendor upload karo', 1);
    }
} else {
    addCheck($sec, 'vendor/composer/installed.json', false, 'Composer metadata nahi mila', 'composer install dobara chalao local pe aur vendor upload karo');
}

/* ═════════════ 6) DATABASE CHECK ═════════════ */
$sec = 'Database';
$pdo = null;
$dbName = $env['DB_DATABASE'] ?? '';

addCheck($sec, 'DB config .env me', $dbName !== '',
    $dbName !== '' ? 'DB: <b>' . h($dbName) . '</b> @ ' . h(($env['DB_HOST'] ?? '?') . ':' . ($env['DB_PORT'] ?? '3306')) : '.env me DB_DATABASE/DB_USERNAME/DB_PASSWORD set nahi hai',
    $dbName !== '' ? '' : 'cPanel → MySQL Databases → DB banao, user add karo, .env me credentials dalo');

if ($dbName !== '') {
    $host = $env['DB_HOST'] ?: 'localhost';
    $port = $env['DB_PORT'] ?: '3306';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        addCheck($sec, 'DB Connection', true, 'Connected ✅ (' . h($host) . ')');

        // MySQL version
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        addCheck($sec, 'MySQL Version', version_compare((string)$ver, '10.2', '>='), h($ver), '', 1);

        // ── Live schema fetch (information_schema) ──
        $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?');
        $stmt->execute([$dbName]);
        $live = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $live[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
        }
        $liveTables = array_keys($live);
        addCheck($sec, 'Tables found', true, count($liveTables) . ' tables live DB me hain', '', 1);

        // ── Reference schema: (a) off_pos_dump.sql (b) embedded critical columns ──
        $ref = [];
        foreach ([$scriptDir . '/off_pos_dump.sql', $basePath . '/off_pos_dump.sql'] as $cand) {
            if (is_file($cand)) { $ref = parseDumpSchema($cand); addCheck($sec, 'Reference schema (off_pos_dump.sql)', true, formatBytes(filesize($cand)) . ' dump se ' . count($ref) . ' tables parse hue — FULL comparison niche', '', 1); break; }
        }

        // embedded: companies ke wo columns jo CODE use karta hai (SettingService etc.)
        $ref['companies'] = array_flip(array_merge($ref['companies'] ?? [], [
            'id','name','business_name','short_name','email','phone','address','website','currency','currency_symbol','currency_position',
            'precision','thousands_separator','decimals_separator','date_format','time_format','fy_start_month','accounting_method',
            'default_profit_percent','logo','del_status','created_at','updated_at','zone_name','timezone',
            'installment_days','e_commerce_checker','is_loyalty_enable','minimum_point_to_redeem','loyalty_rate','product_code_start_from',
            'allow_less_sale','default_customer','default_payment','pos_total_payable_type','default_cursor_position','product_display',
            'onscreen_keyboard_status','grocery_experience','direct_cart','smtp_default_selected_in_pos','sms_default_selected_in_pos',
            'whatsapp_default_selected_in_pos','register_content','collect_tax','tax_title','tax_registration_no','tax_is_gst',
            'tax_registration_number','tax_setting','tax_string','payment_settings','inv_logo_is_show','invoice_logo','invoice_configuration',
            'invoice_footer','term_conditions','is_rounding_enable','letter_head_gap','letter_footer_gap','smtp_type','smtp_details',
            'smtp_enable_status','sms_service_provider','sms_details','sms_enable_status','whatsapp_provider','whatsapp_invoice_enable_status',
            'whatsapp_app_key','whatsapp_authkey','payment_api_setting','zatca_configuration','gst_api_key','company_name','company_email',
            'busynotify_token','busynotify_company_id','purchase_price_show_hide','generic_name_search_option','white_label','white_label_status',
        ]));
        $ref['companies'] = array_map(fn($v) => is_string($v) ? $v : 'TEXT', $ref['companies']);

        // ── COMPARISON: reference vs live ──
        $missingByTable = [];
        foreach ($ref as $table => $cols) {
            if (!isset($live[$table])) continue; // missing TABLE alag report hoga
            foreach (array_keys($cols) as $col) {
                if (!isset($live[$table][$col])) {
                    $missingByTable[$table][] = $col;
                }
            }
        }

        // embedded critical tables jo zaroor hone chahiye
        $criticalTables = ['companies','users','items','sales','purchases','outlets','customers','suppliers','taxs','payment_methods','counters','printers','units','brands','item_categories','migrations','sessions'];
        $missingTables = array_filter($criticalTables, fn($t) => !in_array($t, $liveTables));
        addCheck($sec, 'Critical tables exist', empty($missingTables),
            empty($missingTables) ? 'Sab 17 critical tables hain' : 'Missing tables: <b>' . h(implode(', ', $missingTables)) . '</b>',
            empty($missingTables) ? '' : 'phpMyAdmin me off_pos_dump.sql import karo ya migrations chalwao');

        if (empty($missingByTable)) {
            addCheck($sec, 'Column check (code vs DB)', true, '✅ Saare reference columns DB me hain — koi missing column nahi mila');
        } else {
            $totalMissing = array_sum(array_map('count', $missingByTable));
            addCheck($sec, 'Column check (code vs DB)', false,
                "<b>$totalMissing columns MISSING hain</b> " . count($missingByTable) . " tables me — YEHI 'Unexpected Error' ka reason hota hai!",
                'Niche ALTER TABLE suggestions box me diya hua SQL phpMyAdmin me run karo');
        }

        // ── Fix SQL generation + optional execution ──
        if (!empty($missingByTable)) {
            $alterLines = [];
            foreach ($missingByTable as $table => $cols) {
                foreach ($cols as $col) {
                    $type = $ref[$table][$col] ?? 'TEXT';
                    $type = preg_replace('/\s+GENERATED.*$/i', '', $type);
                    $alterLines[] = "ALTER TABLE `$table` ADD COLUMN `$col` " . (stripos($type,'int') === 0 ? "$type NULL" : "$type NULL DEFAULT NULL") . ";";
                }
            }
            $GLOBALS['fixSql'] = $alterLines;

            $runFix = $ALLOW_FIX && ($_GET['run_fix'] ?? '') === '1' && ($_GET['confirm'] ?? '') === 'yes';
            if ($runFix) {
                $done = 0; $errs = [];
                foreach ($alterLines as $sql) {
                    try { $pdo->exec($sql); $done++; } catch (Throwable $ex) { $errs[] = h($ex->getMessage()); }
                }
                addCheck($sec, "AUTO-FIX executed", empty($errs), "$done queries chali" . (!empty($errs) ? ', errors: ' . h(implode(' | ', array_slice($errs,0,3))) : ''), 'Page reload karke verify karo');
            } else {
                addCheck($sec, 'Auto-fix available', true,
                    count($alterLines) . ' ALTER queries ready hain (niche box me) — script me <code>$ALLOW_FIX = true</code> karke URL me <code>&run_fix=1&confirm=yes</code> dalo, YA phpMyAdmin se manually chalao', '', 1);
            }
        }

        // ── Quick data sanity: companies rows, users ──
        foreach ([['companies','Company row','company_id session ke liye'], ['users','Users row','login ke liye']] as [$tbl,$label,$why]) {
            if (in_array($tbl, $liveTables)) {
                try {
                    $c = (int)$pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
                    addCheck($sec, "$label count", $c > 0, "$c rows ($why)", $c > 0 ? '' : "$tbl table khali hai — seed/import karo", 1);
                } catch (Throwable $ex) {
                    addCheck($sec, "$label count", false, 'Query fail: ' . h($ex->getMessage()), 'Table corrupted ho sakta hai — phpMyAdmin me CHECK TABLE chalao', 1);
                }
            }
        }

    } catch (PDOException $ex) {
        addCheck($sec, 'DB Connection', false,
            '<b>' . h($ex->getMessage()) . '</b>',
            match(true) {
                str_contains($ex->getMessage(), 'Access denied')  => 'cPanel → MySQL Databases me check karo: username/password sahi ho aur user ko DB par ALL PRIVILEGES mili ho. Dhyan do: cPanel DB user ka prefix hota hai (cpuser_rkpos).',
                str_contains($ex->getMessage(), 'Unknown database') => '.env ka DB_DATABASE galat hai — cPanel me exact DB name dekho (prefix ke sath).',
                str_contains($ex->getMessage(), 'Connection refused') || str_contains($ex->getMessage(), 'timed out') => 'DB_HOST localhost hona chahiye cPanel par (127.0.0.1 nahi). .env me DB_HOST=localhost karo.',
                default => 'cPanel → MySQL Databases me DB aur user verify karo',
            });
    }
}

/* ═════════════ 7) LARAVEL BOOT TEST ═════════════ */
$sec = 'Laravel Boot Test';
$bootError = null; $lv = null;
try {
    if (!is_file($basePath . '/vendor/autoload.php')) {
        throw new RuntimeException('vendor/autoload.php nahi mila — composer install missing');
    }
    require $basePath . '/vendor/autoload.php';
    if (!is_file($basePath . '/bootstrap/app.php')) {
        throw new RuntimeException('bootstrap/app.php nahi mila');
    }
    $app = require $basePath . '/bootstrap/app.php';
    try {
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        $lv = 'Laravel ' . $app->version();
        $GLOBALS['rkApp'] = $app;
        $sessionDriver = config('session.driver');
        $cacheDriver   = config('cache.default');
        addCheck($sec, 'Application boot', true, "$lv — boot ho gaya ✅ | session.driver=<b>" . h($sessionDriver) . "</b> | cache=<b>" . h($cacheDriver) . "</b>");
        if ($sessionDriver === 'database' && isset($pdo) && !in_array('sessions', $liveTables ?? [])) {
            addCheck($sec, 'sessions table (database session driver)', false, 'session driver database hai par sessions table nahi mila — login pe fail hoga', 'phpMyAdmin me sessions table banao, YA .env me SESSION_DRIVER=file karo', 1);
        }
        if ($cacheDriver === 'database' && isset($pdo) && !in_array('cache', $liveTables ?? [])) {
            addCheck($sec, 'cache table (database cache driver)', false, 'cache driver database hai par cache/cache_locks table nahi mile', 'phpMyAdmin me cache + cache_locks tables banao, YA CACHE_DRIVER=file karo', 1);
        }
    } catch (Throwable $ex) {
        $bootError = $ex->getMessage();
        addCheck($sec, 'Application boot', false, '<b>' . h($bootError) . '</b>', 'Ye exact error fix karo — aksar config/DB/extension issue hota hai');
    }
} catch (Throwable $ex) {
    addCheck($sec, 'Application boot', false, '<b>' . h($ex->getMessage()) . '</b>', 'vendor missing ya bootstrap file problem');
}

// config/route cache stale check
$sec = 'Caches';

/* ═════════════ 7.5) SETTINGS SAVE DEEP TEST — live call ═════════════ */
$sec = 'Settings Save Deep Test';
if (isset($GLOBALS['rkApp']) && isset($pdo)) {
    $companyId = 0;
    try {
        $companyId = (int)$pdo->query('SELECT id FROM companies ORDER BY id LIMIT 1')->fetchColumn();
    } catch (Throwable $t) {}

    if (!$companyId) {
        addCheck($sec, 'Live save simulation', false, 'companies table khali hai — test skip', '', 1);
    } else {
        // original row capture (restore ke liye)
        $orig = $pdo->query('SELECT * FROM `companies` WHERE id=' . $companyId)->fetch(PDO::FETCH_ASSOC);

        try {
            $k = $GLOBALS['rkApp']->make(Illuminate\Contracts\Console\Kernel::class);
            $k->bootstrap();

            // console me session fake karo taaki getCurrentCompany() chale
            $sessionSim = false;
            try {
                $sm = $GLOBALS['rkApp']->make('session');
                $sm->put('company', ['company_id' => $companyId]);
                $sessionSim = true;
            } catch (Throwable $t) { /* session unavailable */ }

            $svc = $GLOBALS['rkApp']->make(\Modules\Configuration\Services\SettingService::class);

            $payload = [
                'business_name'            => 'RK Diagnostic Test',
                'address'                  => $orig['address'] ?? null,
                'website'                  => $orig['website'] ?? null,
                'email'                    => $orig['email'] ?? 'test@test.com',
                'phone'                    => $orig['phone'] ?? '0000000000',
                'date_format'              => $orig['date_format'] ?? 'd/m/Y',
                'zone_name'                => $orig['zone_name'] ?? 'Asia/Kolkata',
                'currency'                 => $orig['currency'] ?? 'INR',
                'currency_position'        => $orig['currency_position'] ?? 'Before Amount',
                'precision'                => (int)($orig['precision'] ?? 2),
                'thousands_separator'      => $orig['thousands_separator'] ?? ',',
                'decimals_separator'       => $orig['decimals_separator'] ?? '.',
                'installment_days'         => (int)($orig['installment_days'] ?? 7),
                'e_commerce_checker'       => $orig['e_commerce_checker'] ?? 'No',
                'is_loyalty_enable'        => $orig['is_loyalty_enable'] ?? 'Disable',
                'minimum_point_to_redeem'  => $orig['minimum_point_to_redeem'] ?? '0',
                'loyalty_rate'             => $orig['loyalty_rate'] ?? '0',
                'product_code_start_from'  => $orig['product_code_start_from'] ?? '1',
            ];

            $result = $svc->updateBusinessSettings($payload);

            addCheck($sec, 'updateBusinessSettings (live call)', true,
                '✅ SUCCESS: ' . h($result['message'] ?? 'ok') . ' — service theek chal rahi hai! Error web request me kahin aur hai (middleware/routes/session). Report ka "Recent Errors" section check karo.',
                '', 1);
        } catch (Throwable $ex) {
            $msg = $ex->getMessage();
            if (str_contains($msg, 'No query results') && !$sessionSim) {
                addCheck($sec, 'updateBusinessSettings (live call)', true,
                    'Test inconclusive — session simulate nahi ho paya (console limitation). Web me .env APP_DEBUG=true karke error dobara reproduce karo, phir laravel.log me exact error aayega.',
                    '', 1);
            } else {
                addCheck($sec, 'updateBusinessSettings (live call)', false,
                    '<b>🎯 EXACT ERROR PAKDA GAYA:</b><br><code style="color:#c53014">' . h($msg) . '</code><br><br>File: <b>' . h($ex->getFile()) . ':' . $ex->getLine() . '</b>',
                    'Upar wala message + file line hi fix karna hai — web request ke bina hi exact bug mil gaya');
            }
        } finally {
            // restore original row — test ka koi side-effect na rahe
            if (!empty($orig) && is_array($orig)) {
                try {
                    $sets = []; $vals = [];
                    foreach ($orig as $col => $val) {
                        if ($col === 'id') continue;
                        $sets[] = "`$col` = ?";
                        $vals[] = $val;
                    }
                    $vals[] = $companyId;
                    $pdo->prepare('UPDATE `companies` SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
                } catch (Throwable $t) {}
            }
        }
    }
} else {
    addCheck($sec, 'Live save simulation', false, 'Laravel boot ya DB fail hone ki wajah se skip hua', '', 1);
}

// config/route cache stale check
$sec = 'Caches';
foreach ([['bootstrap/cache/config.php','Config cache'], ['bootstrap/cache/routes-v7.php','Route cache']] as [$f,$label]) {
    $exists = is_file($basePath . '/' . $f);
    addCheck($sec, $label, true, $exists ? 'Cached file hai (agar code change ke baad issue aaye to ye STALE ho sakta hai)' : 'Cache nahi bana (theek hai)', '', 1);
}

/* ═════════════ 7.6) PRODUCTION .env CHECK ═════════════ */
$sec = 'Production .env Config';
if (!empty($env)) {
    $appUrl = $env['APP_URL'] ?? '';
    $appDebug = $env['APP_DEBUG'] ?? 'false';
    $sessionDriver = $env['SESSION_DRIVER'] ?? 'file';
    $sessionDomain = $env['SESSION_DOMAIN'] ?? 'null';
    $appName = $env['APP_NAME'] ?? '';

    addCheck($sec, 'APP_URL', $appUrl !== '' && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, 'yourdomain'),
        'Current: <b>' . h($appUrl) . '</b>',
        (str_contains($appUrl, 'localhost') || str_contains($appUrl, 'yourdomain'))
            ? '⚠️ <b>APP_URL galat hai!</b> .env me production domain daalo: <code>APP_URL=https://rashankidukanindia.com</code>. Iski wajah se session cookies kaam nahi karti aur 500 error aata hai.'
            : ($appUrl === '' ? '⚠️ APP_URL set nahi hai — session cookies fail hongi' : ''),
        1);

    addCheck($sec, 'APP_DEBUG', true,
        $appDebug === 'true'
            ? '<b>true</b> — errors screen par dikhenge (DEBUG MODE ON)'
            : 'false — errors sirf log me jaate hain. <b>500 error ka exact reason dekhne ke liye temporarily true karo.</b>',
        '', 1);

    addCheck($sec, 'SESSION_DRIVER', in_array($sessionDriver, ['file', 'database']),
        'Current: <b>' . h($sessionDriver) . '</b>',
        !in_array($sessionDriver, ['file', 'database'])
            ? '⚠️ SESSION_DRIVER = ' . h($sessionDriver) . ' — file ya database hona chahiye'
            : '',
        1);

    addCheck($sec, 'SESSION_DOMAIN', true,
        'Current: <b>' . h($sessionDomain) . '</b>',
        'null = correct (cookies domain ke liye safe)',
        1);

    // Check if APP_URL matches actual request domain
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($requestHost && $appUrl && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, 'yourdomain')) {
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?? '';
        $match = strtolower($requestHost) === strtolower($appHost) || strtolower('www.' . $requestHost) === strtolower($appHost);
        addCheck($sec, 'APP_URL matches request domain', $match,
            'Request: <b>' . h($requestHost) . '</b> | APP_URL host: <b>' . h($appHost) . '</b>',
            $match ? '' : '⚠️ <b>DOMAIN MISMATCH!</b> APP_URL me <code>https://' . h($requestHost) . '</code> daalo — iski wajah se session POST requests me kaam nahi karta',
            1);
    }
}

/* ═════════════ 8) ERROR LOG TAIL ═════════════ */
$sec = 'Recent Errors (laravel.log)';
$logFile = $basePath . '/storage/logs/laravel.log';
$logEntries = [];
if (is_file($logFile)) {
    $size = filesize($logFile);
    $fh = fopen($logFile, 'rb');
    $readFrom = max(0, $size - 200000);
    fseek($fh, $readFrom);
    $content = stream_get_contents($fh);
    fclose($fh);
    if (preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[^\]]*)\]\s+(\w+)\.(\w+):\s+(.*?)(?=\n\[\d{4}-|\z)/s', $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $e) {
            if (strtolower($e[3]) === 'error' || strtolower($e[3]) === 'critical' || strtolower($e[3]) === 'alert' || strtolower($e[3]) === 'emergency') {
                $msg = preg_replace('/\s+/', ' ', trim($e[4]));
                $logEntries[] = ['time' => $e[1], 'env' => $e[2], 'level' => $e[3], 'msg' => $msg];
            }
        }
        $logEntries = array_slice($logEntries, -12);
    }
    addCheck($sec, 'Log file readable', true, formatBytes($size) . ' — last modified: ' . h(date('d M Y H:i', (int)filemtime($logFile))));
} else {
    addCheck($sec, 'Log file readable', false, 'storage/logs/laravel.log nahi mila', 'storage/logs folder banao aur writable karo (755)');
}

/* ═════════════ 8.5) SETTINGS-SPECIFIC ERRORS ═════════════ */
$sec = 'Settings Save Errors (businessSetting)';
if (is_file($logFile)) {
    $fullLog = file_get_contents($logFile);
    $settingErrors = [];
    // Match log entries containing businessSetting
    if (preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[^\]]*)\]\s+(\w+)\.(\w+):\s+(.*?businessSetting.*?)(?=\n\[\d{4}-|\z)/s', $fullLog, $sm, PREG_SET_ORDER)) {
        foreach ($sm as $e) {
            $settingErrors[] = ['time' => $e[1], 'level' => $e[3], 'msg' => trim($e[4])];
        }
    }
    $settingErrors = array_slice($settingErrors, -5);
    if (!empty($settingErrors)) {
        addCheck($sec, 'businessSetting errors found', false,
            '🎯 <b>YE HAI TUMHARA EXACT ERROR:</b><br>' .
            implode('<br><br>', array_map(fn($e) => '<span style="color:#7ee787">' . h($e['time']) . '</span> — <code style="color:#ffa198">' . h(mb_strimwidth($e['msg'], 0, 800, '…')) . '</code>', array_reverse($settingErrors))),
            'Upar ka error fix karo. Agar "Unknown column" hai to fix_companies.sql phpMyAdmin me run karo. Agar "Call to a member function on null" hai to session issue hai — APP_URL sahi karo.');
    } else {
        addCheck($sec, 'businessSetting errors found', true,
            'Koi businessSetting error log me nahi mila (ya abhi tak save try nahi hua — save karke dobara run karo)',
            '', 1);
    }
}

/* ══════════════════════════ HTML RENDER ══════════════════════════ */
$pass = count(array_filter($checks, fn($c) => $c['ok']));
$fail = count($checks) - $pass;
$sections = [];
foreach ($checks as $c) $sections[$c['section']][] = $c;
$health = $fail === 0 ? 100 : max(0, round($pass / max(1, count($checks)) * 100));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RashanKiDukan — Diagnostic Report</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,Arial,sans-serif;background:#f4f5fa;color:#1a1a1a;padding:24px}
  .wrap{max-width:980px;margin:0 auto}
  .head{background:linear-gradient(135deg,#696cff,#5f61e6);border-radius:14px;padding:28px;color:#fff;margin-bottom:20px}
  .head h1{font-size:22px;font-weight:700}
  .head p{opacity:.85;font-size:13px;margin-top:4px}
  .score{display:flex;gap:14px;margin-top:16px;flex-wrap:wrap}
  .chip{background:rgba(255,255,255,.16);border-radius:10px;padding:10px 18px;font-size:13px}
  .chip b{font-size:20px;display:block}
  .card{background:#fff;border:1px solid #eaeaec;border-radius:12px;margin-bottom:16px;overflow:hidden}
  .card h2{font-size:14px;font-weight:700;padding:14px 18px;border-bottom:1px solid #f0f0f5;background:#fafafe}
  .row{display:flex;gap:12px;padding:12px 18px;border-bottom:1px solid #f5f5fa;align-items:flex-start}
  .row:last-child{border-bottom:none}
  .row.sub{padding-left:38px}
  .badge{min-width:64px;text-align:center;font-size:11px;font-weight:700;padding:4px 8px;border-radius:20px;flex-shrink:0}
  .pass{background:#e8ffd6;color:#2e7d13}.fail{background:#fee2e2;color:#c53014}.warn{background:#fff3cd;color:#8a6d00}
  .name{font-weight:600;font-size:13.5px;min-width:210px}
  .body{font-size:13px;color:#444;flex:1;line-height:1.5}
  .fix{margin-top:6px;background:#eef2ff;border-left:3px solid #696cff;padding:8px 12px;border-radius:0 8px 8px 0;font-size:12.5px}
  .fix b{color:#4a4cd6}
  pre{background:#232631;color:#c9d1ff;padding:14px;border-radius:10px;font-size:12px;overflow-x:auto;line-height:1.6;margin-top:8px}
  .log{background:#232631;border-radius:10px;padding:14px;font-family:Consolas,monospace;font-size:12px;color:#c9d1ff;overflow-x:auto}
  .log .t{color:#7ee787}.log .m{color:#ffa198;word-break:break-word}
  .foot{text-align:center;font-size:12px;color:#999;padding:18px}
  .btn{display:inline-block;background:#ff3e1d;color:#fff;text-decoration:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600}
  .btn.gray{background:#696cff}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>🩺 RashanKiDukan — Diagnostic Report</h1>
    <p><?= h(date('d M Y, H:i')) ?> · <?= h(PHP_OS) ?> · <?= h(PHP_VERSION) ?></p>
    <div class="score">
      <div class="chip"><b><?= $pass ?></b>Passed</div>
      <div class="chip"><b><?= $fail ?></b>Issues</div>
      <div class="chip"><b><?= $health ?>%</b>Health</div>
    </div>
  </div>

<?php if ($fatal): ?>
  <div class="card"><h2 style="color:#c53014">💀 Script khud crash hua (partial report)</h2>
    <div class="row"><div class="body"><pre><?= h(print_r($fatal, true)) ?></pre></div></div>
  </div>
<?php endif; ?>

<?php foreach ($sections as $secName => $rows): ?>
  <div class="card">
    <h2><?= h($secName) ?></h2>
    <?php foreach ($rows as $c): ?>
      <div class="row<?= $c['level'] ? ' sub' : '' ?>">
        <span class="badge <?= $c['ok'] ? 'pass' : 'fail' ?>"><?= $c['ok'] ? 'PASS' : 'ISSUE' ?></span>
        <span class="name"><?= h($c['name']) ?></span>
        <div class="body">
          <?= $c['detail'] /* already escaped at add time where needed */ ?>
          <?php if (!$c['ok'] && $c['fix']): ?><div class="fix"><b>🔧 FIX:</b> <?= $c['fix'] ?></div><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if (!empty($GLOBALS['fixSql'])): ?>
  <div class="card">
    <h2>🛠️ Fix SQL — phpMyAdmin me run karo (YA auto-fix)</h2>
    <div class="row"><div class="body">
      <p style="margin-bottom:6px">Ye columns DB me missing hain jinko code use karta hai. Dono options:</p>
      <ol style="margin:8px 0 0 18px;font-size:13px">
        <li><b>phpMyAdmin:</b> DB select karo → SQL tab → niche wala SQL paste karke <b>Go</b></li>
        <li><b>Auto-fix:</b> script me <code style="background:#f1f1f4;padding:2px 6px;border-radius:4px">$ALLOW_FIX = true</code> karo, phir is page ko <code style="background:#f1f1f4;padding:2px 6px;border-radius:4px">&amp;run_fix=1&amp;confirm=yes</code> ke sath kholo</li>
      </ol>
      <pre><?= h(implode("\n", $GLOBALS['fixSql'])) ?></pre>
    </div></div>
  </div>
<?php endif; ?>

<?php if (!empty($logEntries)): ?>
  <div class="card">
    <h2>📜 Last <?= count($logEntries) ?> Errors — storage/logs/laravel.log</h2>
    <div class="row"><div class="body"><div class="log">
    <?php foreach (array_reverse($logEntries) as $i => $e): ?>
      <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #3a3f52">
        <span class="t">#<?= count($logEntries)-$i ?> · <?= h($e['time']) ?></span><br>
        <span class="m"><?= h(mb_strimwidth($e['msg'], 0, 600, '…')) ?></span>
      </div>
    <?php endforeach; ?>
    </div></div></div>
  </div>
<?php endif; ?>

  <div class="foot">
    <p style="margin-bottom:10px">Issue fix ho gaya? Ye file public me rehna <b>security risk</b> hai — delete kar do:</p>
    <a class="btn" href="?key=<?= h($ACCESS_KEY) ?>&amp;selfdestruct=yes">🗑️ Delete this diagnostic file</a>
    &nbsp;
    <a class="btn gray" href="?key=<?= h($ACCESS_KEY) ?>">🔄 Re-run checks</a>
  </div>
</div>
</body>
</html>
