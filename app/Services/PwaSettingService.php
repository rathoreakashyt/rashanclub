<?php

namespace App\Services;

use App\Models\PwaSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;

class PwaSettingService
{
    /**
     * PWA icon sizes to generate.
     */
    protected array $iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];

    /**
     * Get PWA settings for the current company.
     */
    public function getSettings(?int $companyId = null): ?PwaSetting
    {
        $companyId = $companyId ?? session('company.company_id', 1);
        return PwaSetting::forCompany($companyId)->first();
    }

    /**
     * Get or create PWA settings for the current company.
     */
    public function getOrCreateSettings(?int $companyId = null): PwaSetting
    {
        $companyId = $companyId ?? session('company.company_id', 1);
        $settings = PwaSetting::forCompany($companyId)->first();

        if (!$settings) {
            $settings = PwaSetting::create([
                'company_id' => $companyId,
                'app_name' => config('app.name', 'POS App'),
                'short_name' => 'POS',
                'theme_color' => '#7367f0',
                'background_color' => '#ffffff',
                'start_url' => url('/'),
            ]);
        }

        return $settings;
    }

    /**
     * Update PWA settings and optionally process logo upload.
     */
    public function updateSettings(array $data, ?UploadedFile $logoFile = null, ?int $companyId = null): array
    {
        $settings = $this->getOrCreateSettings($companyId);
        $companyId = $settings->company_id ?? session('company.company_id', 1);

        $updateData = [
            'app_name' => $data['app_name'] ?? $settings->app_name,
            'short_name' => $data['short_name'] ?? $settings->short_name,
            'theme_color' => $data['theme_color'] ?? $settings->theme_color,
            'background_color' => $data['background_color'] ?? $settings->background_color,
            'start_url' => $data['start_url'] ?? $settings->start_url,
        ];

        if ($logoFile) {
            $logoPath = $this->processLogoAndGenerateIcons($logoFile, $companyId);
            if ($logoPath) {
                $this->removeOldIcons($settings->logo, $companyId);
                $updateData['logo'] = $logoPath;
            }
        }

        $settings->update($updateData);

        $this->generateManifest($companyId);

        return [
            'status' => 'success',
            'message' => __('PWA settings saved successfully'),
        ];
    }

    /**
     * Process uploaded logo and generate all PWA icon sizes.
     */
    public function processLogoAndGenerateIcons(UploadedFile $file, ?int $companyId = null): ?string
    {
        $companyId = $companyId ?? session('company.company_id', 1);
        $iconsDir = $this->getIconsDirectory($companyId);

        createDirectory($iconsDir);

        try {
            $image = Image::read($file->getRealPath());

            $width = $image->width();
            $height = $image->height();

            if ($width < 512 || $height < 512) {
                throw new \InvalidArgumentException(__('Logo must be at least 512x512 pixels.'));
            }

            foreach ($this->iconSizes as $size) {
                $outputPath = $iconsDir . DIRECTORY_SEPARATOR . "icon-{$size}x{$size}.png";
                Image::read($file->getRealPath())
                    ->cover($size, $size, 'center')
                    ->toPng()
                    ->save($outputPath);
            }

            return $this->getRelativeLogoPath($companyId);
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    /**
     * Generate manifest.json for the given company.
     */
    public function generateManifest(?int $companyId = null): string
    {
        $settings = $this->getOrCreateSettings($companyId);
        $companyId = $settings->company_id ?? session('company.company_id', 1);

        $baseUrl = rtrim(url('/'), '/');
        $startUrl = $settings->start_url;
        if (!str_starts_with($startUrl, 'http')) {
            $startUrl = $baseUrl . '/' . ltrim($startUrl, '/');
        }

        $iconsPath = $this->getIconsWebPath($companyId);
        $icons = [];
        foreach ([192, 512] as $size) {
            $icons[] = [
                'src' => $iconsPath . "/icon-{$size}x{$size}.png",
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
            ];
        }

        $manifest = [
            'name' => $settings->app_name,
            'short_name' => $settings->short_name,
            'start_url' => $startUrl,
            'display' => 'standalone',
            'background_color' => $settings->background_color,
            'theme_color' => $settings->theme_color,
            'icons' => $icons,
        ];

        $manifestDir = public_path('pwa');
        createDirectory($manifestDir);

        $cid = $companyId ?? 1;
        $manifestPath = $manifestDir . DIRECTORY_SEPARATOR . "manifest-{$cid}.json";

        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifestPath;
    }

    /**
     * Get manifest content for response (dynamic, no file needed for default).
     */
    public function getManifestContent(?int $companyId = null): array
    {
        $settings = $this->getOrCreateSettings($companyId);
        $companyId = $settings->company_id ?? session('company.company_id', 1);

        $baseUrl = rtrim(url('/'), '/');
        $startUrl = $settings->start_url;
        if (!str_starts_with($startUrl, 'http')) {
            $startUrl = $baseUrl . '/' . ltrim($startUrl, '/');
        }

        $iconsPath = $this->getIconsWebPath($companyId);

        return [
            'name' => $settings->app_name,
            'short_name' => $settings->short_name,
            'start_url' => $startUrl,
            'display' => 'standalone',
            'background_color' => $settings->background_color,
            'theme_color' => $settings->theme_color,
            'icons' => [
                [
                    'src' => $iconsPath . '/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => $iconsPath . '/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ];
    }

    /**
     * Get the icons directory path for a company.
     */
    protected function getIconsDirectory(?int $companyId = null): string
    {
        $companyId = $companyId ?? 1;
        $base = public_path('pwa' . DIRECTORY_SEPARATOR . 'icons');
        return $base . DIRECTORY_SEPARATOR . $companyId;
    }

    /**
     * Get the relative logo path (for DB storage).
     */
    protected function getRelativeLogoPath(?int $companyId = null): string
    {
        $companyId = $companyId ?? 1;
        return "icons/{$companyId}";
    }

    /**
     * Get the web path for icons (URL path).
     */
    protected function getIconsWebPath(?int $companyId = null): string
    {
        $companyId = $companyId ?? 1;
        return rtrim(asset('pwa/icons/' . $companyId), '/');
    }

    /**
     * Remove old icon files when logo is replaced.
     */
    protected function removeOldIcons(?string $oldLogoPath, ?int $companyId = null): void
    {
        if (!$oldLogoPath) {
            return;
        }

        $cid = $companyId ?? 1;
        $iconsDir = public_path('pwa/icons/' . $cid);

        if (!is_dir($iconsDir)) {
            return;
        }

        foreach ($this->iconSizes as $size) {
            $file = $iconsDir . DIRECTORY_SEPARATOR . "icon-{$size}x{$size}.png";
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}
