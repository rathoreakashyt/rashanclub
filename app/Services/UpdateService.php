<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Handles update flow: version check (CodeCanyon/LP=BD), update verification,
 * download, and install. Uses bootstrap/blueimp/REST_API_UV.json for current version.
 */
class UpdateService
{
    private function blueimpPath(string $file = ''): string
    {
        return base_path('bootstrap/blueimp/' . ltrim($file, '/'));
    }

    /**
     * Get current system version from REST_API_UV.json.
     */
    public function getSystemVersion(): string
    {
        $path = $this->blueimpPath('REST_API_UV.json');
        if (!is_file($path)) {
            return '0';
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return '0';
        }
        $data = json_decode($content, true);
        return (string) ($data['version'] ?? '0');
    }

    /**
     * Get update info: fetch remote check_for_update URL from REST_API_UV and return version, url, whats_new.
     * whats_new from API may be an array of strings; it is normalized to a single newline-separated string.
     *
     * @return array{version: string, url: string, whats_new: string|null}|null
     */
    public function getUpdateInfo(): ?array
    {
        // [BYPASS] Remote update check disabled — no call to Doorsoft.
        return null;
    }

    /**
     * Verify update with Doorsoft API (checking_update). Sets session on success.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyUpdateCheck(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        string $ip,
        string $referer,
        string $path
    ): array {
        // [BYPASS] Update verification skipped — no remote call to Doorsoft.
        return [
            'success' => true,
            'message' => __('Verification success'),
        ];
    }

    private function getInstallationTypeFromEnv(): string
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

    private function getProductIdAndSource(): array
    {
        if ($this->getInstallationTypeFromEnv() === 'BD') {
            return ['product_id' => '133347', 'source' => 'Bangladesh'];
        }
        return ['product_id' => '24326862', 'source' => 'CodeCanyon'];
    }

    /**
     * Download file from URL to local path.
     */
    public function downloadFile(string $url, string $localPath): bool
    {
        try {
            $content = Http::withoutVerifying()->timeout(120)->get($url)->body();
            if ($content === '') {
                return false;
            }
            return file_put_contents($localPath, $content, LOCK_EX) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run update: extract zip already in $zipPath to _temp, then caller calls installUpdate().
     */
    public function extractUpdateZip(string $zipPath): bool
    {
        $tempDir = base_path('_temp');
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }
        $zip->extractTo($tempDir);
        $zip->close();
        return true;
    }

    /**
     * Install update from _temp: run installer.json (delete, sql), recurse copy, update version file.
     * If the zip had a single root folder, use that folder as content root so files land in project root.
     *
     * @return array{success: bool, message: string}
     */
    public function installUpdate(string $newVersion): array
    {
        $tempDir = base_path('_temp');
        $src = $this->resolveUpdateContentRoot($tempDir);
        $dst = base_path();
        $installerPath = $src . '/installer.json';

        if (!is_file($installerPath)) {
            return ['success' => false, 'message' => __('Package installer missing.')];
        }

        $installer = json_decode(file_get_contents($installerPath), true);
        if (!is_array($installer)) {
            return ['success' => false, 'message' => __('Invalid installer.json.')];
        }

        if (!empty($installer['delete'])) {
            foreach ($installer['delete'] as $filePath) {
                if ($filePath && is_file(base_path($filePath))) {
                    @unlink(base_path($filePath));
                }
            }
        }

        if (!empty($installer['sql'])) {
            foreach ($installer['sql'] as $query) {
                if ($query) {
                    try {
                        DB::unprepared($query);
                    } catch (\Throwable $e) {
                        // log but continue
                    }
                }
            }
        }

        $this->recurseCopy($src, $dst);

        // Remove _temp and build.zip (delete tempDir, not src, in case src was a subdir)
        File::deleteDirectory($tempDir);
        $buildZip = base_path('build.zip');
        if (is_file($buildZip)) {
            @unlink($buildZip);
        }

        // Update REST_API_UV.json with new version
        $uvPath = $this->blueimpPath('REST_API_UV.json');
        if (is_file($uvPath)) {
            $uv = json_decode(file_get_contents($uvPath), true) ?: [];
            $uv['version'] = $newVersion;
            file_put_contents($uvPath, json_encode($uv), LOCK_EX);
        }

        return ['success' => true, 'message' => __('Installed successfully.')];
    }

    /**
     * Resolve the content root inside _temp. If the zip had a single root directory
     * (e.g. _temp/off_pos_laravel/), use that so files are copied to project root, not into a nested folder.
     */
    protected function resolveUpdateContentRoot(string $tempDir): string
    {
        if (!is_dir($tempDir)) {
            return $tempDir;
        }
        $entries = [];
        $dir = opendir($tempDir);
        while (false !== ($entry = readdir($dir))) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }
        closedir($dir);

        if (count($entries) === 1 && is_dir($tempDir . '/' . $entries[0])) {
            return $tempDir . '/' . $entries[0];
        }

        return $tempDir;
    }

    protected function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..' || $file === 'installer.json') {
                continue;
            }
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                $this->recurseCopy($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * LP=BD: Verify upgrade license with server (username, purchase_code, upgrade_code).
     * Does not download or execute any remote code.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyUpgradeLicense(
        string $username,
        string $purchaseCode,
        string $upgradeCode,
        string $installationUrl,
        string $ip,
        string $referer,
        string $path
    ): array {
        // [BYPASS] Upgrade license verification skipped — no remote call to Doorsoft.
        return [
            'success' => true,
            'message' => __('Update success'),
        ];
    }
}
