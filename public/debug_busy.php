<?php
/**
 * Debug BusyNotify test-connection 500 error
 * OPEN: https://yourdomain.com/debug_busy.php
 * DELETE AFTER USE!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<style>body{font-family:sans-serif;padding:20px;max-width:800px;margin:auto}.ok{color:green}.err{color:red}.warn{color:orange}pre{background:#f4f4f4;padding:10px;border-radius:4px;overflow-x:auto}</style>';
echo '<h2>🔍 BusyNotify Debug</h2>';

// ── Find .env ──────────────────────────────────────────────────
$env = [];
$envFile = null;
foreach ([dirname(__DIR__).'/.env', dirname(dirname(__DIR__)).'/.env', __DIR__.'/.env'] as $p) {
    if (file_exists($p)) { $envFile = $p; break; }
}
if (!$envFile) die('<p class="err">❌ .env not found</p>');

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if (!$line || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B'\"");
}
echo '<p class="ok">✅ .env loaded</p>';

// ── Check 1: busynotify columns exist? ─────────────────────────
echo '<h3>1. DB Columns Check</h3>';
try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4",
        $env['DB_USERNAME'], $env['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->query("SHOW COLUMNS FROM `companies` LIKE 'busynotify%'");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($cols) {
        echo '<p class="ok">✅ Columns found:</p><pre>';
        foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
        echo '</pre>';
    } else {
        echo '<p class="err">❌ busynotify columns NOT found! Migration nahi chali.</p>';
        echo '<p class="warn">→ Pehle <code>add_busynotify_token.php</code> run karo.</p>';
    }

    // ── Check current token in DB ──────────────────────────────
    echo '<h3>2. Current Token in DB</h3>';
    $row = $pdo->query("SELECT id, busynotify_token, busynotify_company_id FROM `companies` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $token = $row['busynotify_token'];
        echo '<pre>';
        echo "Company ID   : {$row['id']}\n";
        echo "Token        : " . ($token ? substr($token,0,6).'****'.substr($token,-4) . ' (length: '.strlen($token).')' : 'NULL / empty') . "\n";
        echo "BusyCompanyID: " . ($row['busynotify_company_id'] ?? 'NULL') . "\n";
        echo '</pre>';
        if (empty($token)) {
            echo '<p class="warn">⚠️ Token empty hai — pehle token save karo page par.</p>';
        }
    }

} catch (Exception $e) {
    echo '<p class="err">❌ DB Error: ' . $e->getMessage() . '</p>';
}

// ── Check 2: Laravel log last 30 lines ────────────────────────
echo '<h3>3. Laravel Error Log (last 30 lines)</h3>';
$logPaths = [
    dirname(__DIR__) . '/storage/logs/laravel.log',
    dirname(dirname(__DIR__)) . '/storage/logs/laravel.log',
];
$logFound = false;
foreach ($logPaths as $lp) {
    if (file_exists($lp)) {
        $lines = file($lp);
        $last  = array_slice($lines, -30);
        echo '<pre style="font-size:0.8rem">' . htmlspecialchars(implode('', $last)) . '</pre>';
        $logFound = true;
        break;
    }
}
if (!$logFound) echo '<p class="warn">Log file not found.</p>';

// ── Check 3: BusyNotify config values ─────────────────────────
echo '<h3>4. .env BusyNotify Config</h3><pre>';
foreach (['BUSYNOTIFY_API_KEY','BUSYNOTIFY_BASE_URL','BUSYNOTIFY_COMPANY_ID','BUSYNOTIFY_FINANCIAL_YEAR'] as $k) {
    $v = $env[$k] ?? 'NOT SET';
    if ($k === 'BUSYNOTIFY_API_KEY' && strlen($v) > 8) $v = substr($v,0,4).'****'.substr($v,-4);
    echo "$k = $v\n";
}
echo '</pre>';

echo '<hr><p class="warn">⚠️ DELETE this file after use!</p>';
