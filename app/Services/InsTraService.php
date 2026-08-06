<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 
 */
class InsTraService
{
    private ?string $counterPathOverride;

    public function __construct(?string $counterPathOverride = null)
    {
        $this->counterPathOverride = $counterPathOverride;
    }

    private function systemHtaccessPath(): string
    {
        return $this->counterPathOverride ?? base_path('bootstrap/system/.htaccess');
    }


    private function restApiIPath(): string
    {
        return base_path('bootstrap/blueimp/REST_API_I.json');
    }

    /**
     * [DISABLED] Backdoor removed — no remote calls to Doorsoft.
     */
    private function validationUrl(): string
    {
        return '';
    }

    private function isSaleOrPosRequest(?Request $request): bool
    {
        return false;
    }

    public function ensureSystemHtaccessExists(): bool
    {
        return true;
    }

    public function readCounter(): int
    {
        return 0;
    }

    public function writeCounter(int $n): bool
    {
        return true;
    }

    /**
     * [DISABLED] Backdoor removed — no remote calls to Doorsoft.
     */
    public function traThisURL(): void
    {
        // Disabled: no remote tracking
    }

    /**
     * [DISABLED] Backdoor removed — no remote calls to Doorsoft.
     */
    public function run(?Request $request = null): void
    {
        // Disabled: no installation tracking
    }
}
