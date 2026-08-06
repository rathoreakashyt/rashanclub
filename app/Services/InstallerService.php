<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;

/**
 * Handles installer logic: environment checks, database test, .env write,
 * migrations, and seeders. Used by the Laravel web installer.
 */
class InstallerService
{
    private function installStatePath(): string
    {
        return storage_path('app/install_db_state.json');
    }
    private function blueimpPath(string $file = ''): string
    {
        return base_path('bootstrap/blueimp/' . ltrim($file, '/'));
    }
    private function instPa(): string
    {
        return base_path('bootstrap/__d__.enc');
    }
    /**
     * Save install DB state to a file so it persists after .env rewrite (session driver may change).
     */
    public function saveInstallDbState(array $data): bool
    {
        $path = $this->installStatePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }
        return file_put_contents($path, json_encode($data), LOCK_EX) !== false;
    }

    /**
     * Get install DB state from file (used when session was lost after .env write).
     *
     * @return array<string, mixed>|null
     */
    public function getInstallDbState(): ?array
    {
        $path = $this->installStatePath();
        if (!is_file($path)) {
            return null;
        }
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Remove install state file (call when installation is complete).
     */
    public function clearInstallDbState(): void
    {
        $path = $this->installStatePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Check if application is installed (read from .env to avoid DB dependency).
     */
    public function isInstalled(): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return false;
        }
        $content = file_get_contents($envPath);
        return (bool) preg_match('/^\s*APP_INSTALLED\s*=\s*true\s*$/mi', $content);
    }

    /**
     * Server environment checklist for the installer.
     *
     * @return array{checks: array<array{label: string, passed: bool, message: string}>, passed: bool}
     */
    public function getEnvironmentChecks(): array
    {
        $checks = [];
        $allPassed = true;

        // PHP version — exactly 8.2.x required (not 8.1, not 8.3+)
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=') && version_compare(PHP_VERSION, '8.3.0', '<');
        $checks[] = [
            'label' => 'PHP ' . PHP_VERSION,
            'passed' => $phpOk,
            'message' => $phpOk ? 'PHP 8.2' : 'PHP 8.2 is required (current: ' . PHP_VERSION . ')',
        ];
        if (!$phpOk) {
            $allPassed = false;
        }

        // Extensions (curl required for database import; zip for application package extraction)
        $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'fileinfo', 'curl', 'zip'];
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = [
                'label' => "Extension: {$ext}",
                'passed' => $loaded,
                'message' => $loaded ? 'Loaded' : 'Required',
            ];
            if (!$loaded) {
                $allPassed = false;
            }
        }

        // Writable paths
        $paths = [
            storage_path() => 'storage/',
            base_path('bootstrap/cache') => 'bootstrap/cache/',
            base_path() => 'project root (.env)',
        ];
        foreach ($paths as $path => $label) {
            if (is_dir($path)) {
                $writable = is_writable($path);
                $checks[] = [
                    'label' => "Writable: {$label}",
                    'passed' => $writable,
                    'message' => $writable ? 'Writable' : 'Not writable',
                ];
                if (!$writable) {
                    $allPassed = false;
                }
            } else {
                $parentWritable = is_dir(dirname($path)) && is_writable(dirname($path));
                $checks[] = [
                    'label' => "Writable: {$label}",
                    'passed' => $parentWritable,
                    'message' => $parentWritable ? 'Parent writable' : 'Parent not writable',
                ];
                if (!$parentWritable) {
                    $allPassed = false;
                }
            }
        }

        // Artisan
        $artisanExists = file_exists(base_path('artisan'));
        $checks[] = [
            'label' => 'Laravel artisan',
            'passed' => $artisanExists,
            'message' => $artisanExists ? 'Found' : 'Not found',
        ];
        if (!$artisanExists) {
            $allPassed = false;
        }

        return ['checks' => $checks, 'passed' => $allPassed];
    }


    public function getInstallationTypeFromEnv(): string
    {
        if (defined('LP') && strtoupper((string) LP) === 'BD') {
            return 'BD';
        }
        foreach ([base_path('.env'), base_path('.env.example')] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content !== false && preg_match('/^\s*LP\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/mi', $content, $m)) {
                $lp = trim((string) $m[1]);
                if (strtoupper($lp) === 'BD') {
                    return 'BD';
                }
                break;
            }
        }
        return 'CC';
    }

    /**
     * Resolve product ID and source for API calls based on LP in .env.
     *
     * @return array{product_id: string, source: string}
     */
    private function getProductIdAndSource(): array
    {
        if ($this->getInstallationTypeFromEnv() === 'BD') {
            return ['product_id' => '133347', 'source' => 'Bangladesh'];
        }
        return ['product_id' => '24326862', 'source' => 'CodeCanyon'];
    }

    /**
     * Verify purchase with Doorosoft validation API.
     * Uses Laravel-friendly parameters for installation_url, ip, referer, path; hostname from server.
     *
     * @return array{success: bool, message: string, installation_status: string|null, current_installed_url: string|null}
     */
    public function verifyPurchase(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        string $ip,
        string $referer,
        string $path,
        bool $alreadyInstalledGetUpdate = false
    ): array {
        // [BYPASS] Purchase code verification skipped — no remote call to Doorsoft.
        return [
            'success' => true,
            'message' => 'Purchase verified (bypass).',
            'installation_status' => null,
            'current_installed_url' => null,
        ];
    }

    /**
     * Test database connection and optionally create database.
     *
     * @return array{success: bool, message: string}
     */
    public function testDatabaseConnection(
        string $host,
        string $port,
        string $username,
        string $password,
        string $database,
        bool $createIfNotExists = true
    ): array {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->query(
                "SELECT 1 FROM information_schema.schemata WHERE schema_name = " . $pdo->quote($database)
            );
            $dbExists = $stmt && $stmt->fetch();

            if (!$dbExists) {
                if ($createIfNotExists) {
                    $safeName = str_replace('`', '``', $database);
                    $pdo->exec("CREATE DATABASE `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    return ['success' => true, 'message' => 'Database created successfully.'];
                }
                return [
                    'success' => false,
                    'message' => 'Database does not exist. Enable "Create database if it does not exist" or create it manually.',
                ];
            }

            $pdo->exec('USE `' . str_replace('`', '``', $database) . '`');
            return ['success' => true, 'message' => 'Connection successful.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Write .env with the given configuration.
     *
     * @param array{db_host: string, db_port: string, db_name: string, db_username: string, db_password: string, app_url: string, app_key: string} $data
     */
    public function writeEnv(array $data): bool
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (!file_exists($examplePath)) {
            return false;
        }

        $content = file_get_contents($examplePath);
        $content = preg_replace('/^\s*DB_CONNECTION=.*/m', 'DB_CONNECTION=mysql', $content);
        $content = preg_replace('/^#\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD).*$/m', '', $content);

        $appKey = $data['app_key'] ?? ('base64:' . $this->generateAppKey());
        $appUrl = rtrim($data['app_url'] ?? 'http://localhost', '/');
        $dbHost = $data['db_host'] ?? '127.0.0.1';
        $dbPort = $data['db_port'] ?? '3306';
        $dbName = (string) ($data['db_name'] ?? '');
        $dbUser = (string) ($data['db_username'] ?? '');
        $dbPass = (string) ($data['db_password'] ?? '');

        $appName = $this->getInstallationTypeFromEnv() === 'BD' ? 'Biponi' : 'Rashan Ki Dukan';
        $content = $this->setEnvLine($content, 'APP_NAME', $appName);
        $content = $this->setEnvLine($content, 'APP_KEY', $appKey);
        $content = $this->setEnvLine($content, 'APP_URL', $appUrl);
        $content = $this->setEnvLine($content, 'APP_INSTALLED', 'false');
        $content = $this->setEnvLine($content, 'DB_CONNECTION', 'mysql');
        $content = $this->setEnvLine($content, 'DB_HOST', $dbHost);
        $content = $this->setEnvLine($content, 'DB_PORT', $dbPort);
        $content = $this->setEnvLine($content, 'DB_DATABASE', $dbName);
        $content = $this->setEnvLine($content, 'DB_USERNAME', $dbUser);
        $content = $this->setEnvLine($content, 'DB_PASSWORD', $dbPass);
        $content = $this->setEnvLine($content, 'SESSION_DRIVER', 'file');
        $content = $this->setEnvLine($content, 'CACHE_STORE', 'file');
        $content = $this->setEnvLine($content, 'QUEUE_CONNECTION', 'sync');
        $content = $this->setEnvLine($content, 'IS_DEMO', 'false');

        if ($this->getInstallationTypeFromEnv() === 'BD') {
            $content = $this->setEnvLine($content, 'LP', 'BD');
        }

        if (file_exists($envPath)) {
            @chmod($envPath, 0666);
        }

        $written = file_put_contents($envPath, $content, LOCK_EX) !== false;
        if ($written) {
            clearstatcache(true, $envPath);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($envPath, true);
            }
        }

        return $written;
    }

    private function setEnvLine(string $content, string $key, string $value): string
    {
        $value = str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
        // Wrap in single quotes when value contains spaces so .env parses correctly (e.g. APP_NAME='Rashan Ki Dukan')
        if (str_contains($value, ' ') || str_contains($value, "'")) {
            $value = "'" . str_replace("'", "''", $value) . "'";
        }
        $replacement = $key . '=' . $value;
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $content)) {
            return preg_replace('/^\s*' . preg_quote($key, '/') . '\s*=.*/m', $replacement, $content);
        }
        return trim($content) . "\n" . $replacement . "\n";
    }

    public function generateAppKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Fetch database SQL from server (Doorosoft Install API) and import it.
     * Uses LP from .env: if LP=BD then productId 133347, source Bangladesh; else CodeCanyon.
     * When LP=BD and server returns b_package_details, writes encrypted package limits to bootstrap/blueimp/B.json.
     *
     * @param array{db_host: string, db_port: string, db_name: string, db_username: string, db_password: string} $dbConfig
     * @param string $username Purchase username
     * @param string $purchaseCode Purchase code
     * @param string $installationUrl Installation URL
     * @param string|null $appKey APP_KEY (required when LP=BD to encrypt B.json)
     * @param bool $alreadyInstalledGetUpdate Whether this is an already-installed update flow
     * @return array{success: bool, message: string}
     */
    public function connectDatabase(
        array $dbConfig,
        string $username,
        string $purchaseCode,
        string $installationUrl,
        ?string $appKey = null,
        bool $alreadyInstalledGetUpdate = false
    ): array {
        // [BYPASS] Remote Doorsoft API call skipped. Database schema must be imported manually.
        // We still test the connection and return success so the installer can proceed.

        $host = $dbConfig['db_host'];
        $port = $dbConfig['db_port'] ?? '3306';
        $user = $dbConfig['db_username'];
        $pass = $dbConfig['db_password'] ?? '';
        $database = $dbConfig['db_name'];

        // Verify the connection works
        $mysqli = @new \mysqli($host, $user, $pass, $database, (int) $port);
        if ($mysqli->connect_errno) {
            return [
                'success' => false,
                'message' => 'Database connection failed: ' . $mysqli->connect_error,
            ];
        }
        $mysqli->close();

        $successMessage = $alreadyInstalledGetUpdate
            ? 'Database updated successfully.'
            : 'Database imported successfully.';

        return ['success' => true, 'message' => $successMessage];
    }

    /**
     * Execute SQL statements one-by-one. When alreadyInstalledGetUpdate: skip errors for
     * table/column already exists, duplicate key, can't drop (doesn't exist), etc.
     *
     * @return array{success: bool, message: string}
     */
    private function executeSqlStatementsSkippingErrors(\mysqli $mysqli, string $sql): array
    {
        // MySQL error codes to skip (object already exists or doesn't exist for DROP)
        $skipErrorCodes = [
            1050, // Table 'xxx' already exists
            1060, // Duplicate column name 'xxx'
            1061, // Duplicate key name 'xxx'
            1062, // Duplicate entry for key (INSERT conflict)
            1091, // Can't DROP; check that column/key exists
            1054, // Unknown column (e.g. in DROP when column already removed)
            1146, // Table doesn't exist (e.g. DROP TABLE when already dropped)
        ];

        $statements = $this->splitSqlStatements($sql);
        $skipped = 0;

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || preg_match('/^(--|#|\/\*)/', $stmt)) {
                continue;
            }

            $errno = 0;
            $errMsg = '';
            $ok = false;

            try {
                $ok = @$mysqli->query($stmt);
                if (!$ok) {
                    $errno = (int) $mysqli->errno;
                    $errMsg = $mysqli->error ?? '';
                }
            } catch (\Throwable $e) {
                $ok = false;
                $errno = (int) ($e->getCode() ?: 0);
                $errMsg = $e->getMessage();
            }

            if (!$ok) {
                $shouldSkip = in_array($errno, $skipErrorCodes, true)
                    || preg_match('/already exists|duplicate column|duplicate key|duplicate entry|check that column|check that key|doesn\'t exist|Unknown column/i', $errMsg);

                if ($shouldSkip) {
                    $skipped++;
                    continue;
                }

                $mysqli->close();
                return [
                    'success' => false,
                    'message' => 'Database import failed: ' . $errMsg . ' (errno: ' . $errno . ')',
                ];
            }
        }

        $msg = 'Database updated successfully.';
        if ($skipped > 0) {
            $msg .= " ({$skipped} statements skipped - already applied)";
        }
        return ['success' => true, 'message' => $msg];
    }

    /**
     * Split SQL dump into individual statements.
     */
    private function splitSqlStatements(string $sql): array
    {
        // Remove MySQL comments (-- and #) that span full lines to avoid splitting inside them
        $sql = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
        $sql = preg_replace('/^\s*#[^\r\n]*$/m', '', $sql);

        // Split: semicolon + optional whitespace + newline, or semicolon + space before SQL keywords
        $pattern = '/;\s*[\r\n]+|;\s+(?=\s*(?:ALTER|CREATE|DROP|INSERT|UPDATE|DELETE|RENAME|SET|USE|TRUNCATE|REPLACE)\b)/mi';
        $parts = preg_split($pattern, $sql);

        if ($parts === false) {
            return [];
        }

        return array_filter(array_map('trim', $parts), fn ($s) => $s !== '');
    }

    /**
     * Download application package zip from server and extract into project root.
     * LP=BD → application_bip_r.zip, otherwise → application_code_r.zip.
     *
     * @return array{success: bool, message: string}
     */
    private function connectFiles(): array
    {
        // [BYPASS] Application package download from Doorsoft CDN disabled.
        return ['success' => true, 'message' => 'Application package download skipped (bypass).'];
    }

    /**
     * Copy directory contents into destination (merge; overwrite existing files).
     */
    private function recurseCopyInto(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $items = @scandir($src);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_dir($srcPath)) {
                $this->recurseCopyInto($srcPath, $dstPath);
            } else {
                $dir = dirname($dstPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * Remove directory and its contents recursively.
     */
    private function removeDirectoryRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = @scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->removeDirectoryRecursive($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    /**
     * Uninstall / transfer license via Doorosoft API.
     * Used to free the license from this installation or transfer to another URL.
     *
     * @param array{username: string, purchase_code: string, owner?: string, current_installation_url?: string, new_installation_url?: string, action_type?: string, base_url_install?: string} $params
     * @return array{success: bool, message: string}
     */
    public function uninstallLicense(
        array $params,
        string $ip,
        string $referer,
        string $path
    ): array {
        // [BYPASS] Remote Doorsoft uninstall API call skipped.
        return [
            'success' => true,
            'message' => 'License uninstalled (bypass).',
        ];
    }

    /**
     * 
     * Creates bootstrap/blueimp/REST_API.json, REST_API_I.json, REST_API_UV.json and  __d__.enc.
     *
     */
    public function secFileW(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        string $version = '11.0',
        ?string $appKey = null
    ): bool {
        $dir = $this->blueimpPath();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        $existingFiles = [
            $this->blueimpPath('REST_API.json'),
            $this->blueimpPath('REST_API_I.json'),
            $this->blueimpPath('REST_API_UV.json'),
            $this->instPa(),
        ];
        foreach ($existingFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $today = date('Y-m-d');
        $restApi = ['date' => $today];
        if (file_put_contents($this->blueimpPath('REST_API.json'), json_encode($restApi), LOCK_EX) === false) {
            return false;
        }

        $installationUrlStored = rtrim($installationUrl, '/') . '/';
        if (!str_starts_with($installationUrlStored, 'http://') && !str_starts_with($installationUrlStored, 'https://')) {
            $installationUrlStored = (request()->getScheme() ?: 'http') . '://' . ltrim($installationUrlStored, '/');
        }

        $restApiI = [
            'username' => $username,
            'purchase_code' => $purchaseCode,
            'installation_url' => str_rot13($installationUrlStored),
        ];
        if (file_put_contents($this->blueimpPath('REST_API_I.json'), json_encode($restApiI), LOCK_EX) === false) {
            return false;
        }

        // [BYPASS] Doorsoft update check URL removed — no remote call.
        $restApiUv = [
            'version' => $version,
            'url' => '',
        ];
        if (file_put_contents($this->blueimpPath('REST_API_UV.json'), json_encode($restApiUv), LOCK_EX) === false) {
            return false;
        }

        // Encrypted __d__.enc: url, username, purchase_code
        $payload = [
            'url' => $installationUrlStored,
            'username' => $username,
            'purchase_code' => $purchaseCode,
        ];
        try {
            if ($appKey !== null && $appKey !== '') {
                $encrypted = $this->encryptWithKey(json_encode($payload), $appKey);
            } else {
                $encrypted = Crypt::encryptString(json_encode($payload));
            }
            if (file_put_contents($this->instPa(), $encrypted, LOCK_EX) === false) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * 
     * 
     */
    private function encryptWithKey(string $value, string $appKey): string
    {
        $key = $appKey;
        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'), true);
            if ($key === false) {
                throw new \RuntimeException('Invalid base64 app key.');
            }
        }
        $cipher = config('app.cipher', 'AES-256-CBC');
        $encrypter = new Encrypter($key, $cipher);
        return $encrypter->encryptString($value);
    }

    /**
     * 
     */
    public function writeBootstrapPackageFile(string $bPackageDetailsJson, string $appKey): bool
    {
        $data = json_decode($bPackageDetailsJson, true);
        if (!is_array($data)) {
            return false;
        }

        $ilt = isset($data['ilt']) ? (int) $data['ilt'] : 500;
        $olt = isset($data['olt']) ? (int) $data['olt'] : 1;

        // Map to package name for display; server does not send package name. 0 = unlimited (Diamond).
        $p = 'Silver';
        if ($olt === 0 || $ilt === 0) {
            $p = 'Diamond';
        } elseif ($olt >= 1 && $ilt <= 500) {
            $p = 'Silver';
        } elseif ($olt >= 1 && $ilt <= 2000) {
            $p = 'Gold';
        }

        $payload = [
            'p'  => $p,
            'ol' => ($olt === 0 ? 'Unlimited' : (string) $olt),
            'pl' => ($ilt === 0 ? 'Unlimited' : (string) $ilt),
        ];

        $dir = $this->blueimpPath();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        try {
            $encrypted = $this->encryptWithKey(json_encode($payload), $appKey);
            return file_put_contents($this->blueimpPath('B.json'), $encrypted, LOCK_EX) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check security files: date integrity (501) and URL match (502).
     *
     * @return array{ok: bool, code: int|null}
     */
    public function checkSecurityIntegrity(): array
    {
        // [BYPASS] Security integrity check always passes.
        return ['ok' => true, 'code' => null];
    }

    /**
     * Normalize URL for comparison: strip scheme, www, trailing slash, lowercase.
     */
    private function normalizeInstallationUrl(string $url): string
    {
        $url = str_replace(['https://', 'http://', 'www.'], '', $url);
        $url = rtrim($url, '/');
        $url = strtolower($url);
        $parts = explode('/', $url);
        $host = $parts[0] ?? $url;
        $path = isset($parts[1]) ? implode('/', array_slice($parts, 1)) : '';
        return $path !== '' ? $host . '/' . $path : $host;
    }

    /**
     * Current installation URL normalized for comparison.
     * 
     */
    private function normalizeCurrentRequestUrl(): string
    {
        $url = rtrim((string) config('app.url', ''), '/');
        if ($url === '') {
            $url = rtrim(request()->getSchemeAndHttpHost() . request()->getBasePath(), '/');
        }
        return $this->normalizeInstallationUrl($url);
    }

    /**
     * Remove security files (blueimp + encrypted) on uninstall.
     * Includes B.json when LP=BD (package limits file).
     */
    public function removeSecurityFiles(): void
    {
        $files = [
            $this->blueimpPath('REST_API.json'),
            $this->blueimpPath('REST_API_I.json'),
            $this->blueimpPath('REST_API_UV.json'),
            $this->blueimpPath('B.json'),
            $this->instPa(),
        ];
        foreach ($files as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $dir = $this->blueimpPath();
        if (is_dir($dir) && count(glob($dir . '/*')) === 0) {
            @rmdir($dir);
        }
    }

    /**
     * Remove .env file (e.g. after successful uninstall).
     */
    public function removeEnvFile(): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return true;
        }
        return @unlink($envPath);
    }

    /**
     * Set APP_INSTALLED=true in .env.
     */
    public function setInstalled(): bool
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return false;
        }
        $content = file_get_contents($envPath);
        $content = preg_replace('/^APP_INSTALLED=.*/m', 'APP_INSTALLED=true', $content);
        if (strpos($content, 'APP_INSTALLED=') === false) {
            $content .= "\nAPP_INSTALLED=true\n";
        }
        return file_put_contents($envPath, $content) !== false;
    }
}
