<?php
// ═══════════════════════════════════════════════════════════════
// cPanel Deployment: public_html/index.php
// ---------------------------------------------------------------
// 1. Is file ka naam rename karo: index_cpanel_template.php → index.php
// 2. APP_PATH mein apna actual server path daalo
//    (cPanel → File Manager → rashankidukan folder ka full path copy karo)
// ═══════════════════════════════════════════════════════════════

// ─── APNA PATH YAHAN DAALO ────────────────────────────────────
// Example: '/home/yourusername/rashankidukan'
define('APP_PATH', '/home/yourusername/rashankidukan');
// ─────────────────────────────────────────────────────────────

define('LARAVEL_START', microtime(true));

if (file_exists(APP_PATH . '/storage/framework/maintenance.php')) {
    require APP_PATH . '/storage/framework/maintenance.php';
}

require APP_PATH . '/vendor/autoload.php';

$app = require_once APP_PATH . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
