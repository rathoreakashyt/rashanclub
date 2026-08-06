<?php

namespace Tests\Feature;

use App\Services\InsTraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsTraServiceTest extends TestCase
{
    private string $counterPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counterPath = base_path('bootstrap/system/.htaccess');
    }

    /** Ensure counter file is created with "1" when missing */
    public function test_ensure_system_htaccess_creates_file_with_one(): void
    {
        $tempDir = sys_get_temp_dir() . '/off_pos_tracker_test_' . uniqid();
        $tempPath = $tempDir . '/.htaccess';
        mkdir($tempDir, 0755, true);

        try {
            $tracker = new InsTraService($tempPath);
            $tracker->ensureSystemHtaccessExists();
            $this->assertFileExists($tempPath);
            $this->assertSame('1', trim(file_get_contents($tempPath)));
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
            @rmdir($tempDir);
        }
    }

    /** On Sale/POS request when counter is 9, next run increments to 10 and triggers CURL */
    public function test_tracker_increments_on_sale_pos_and_calls_curl_when_reminder_zero(): void
    {
        Http::fake([
            'https://doorsoft.co/dsl/Validation/checkerOffPOS/' => Http::response('ok', 200),
        ]);

        $dir = dirname($this->counterPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $backup = is_file($this->counterPath) ? file_get_contents($this->counterPath) : null;
        file_put_contents($this->counterPath, '9', LOCK_EX);

        try {
            $request = Request::create('/pos', 'GET');
            $tracker = app(InsTraService::class);
            $tracker->run($request);

            $this->assertSame('10', trim(file_get_contents($this->counterPath)));
            Http::assertSent(function ($request) {
                return $request->url() === 'https://doorsoft.co/dsl/Validation/checkerOffPOS/'
                    && str_contains($request->body(), 'view=')
                    && str_contains($request->body(), 'view_randar=');
            });
        } finally {
            if ($backup !== null) {
                file_put_contents($this->counterPath, $backup, LOCK_EX);
            } else {
                @unlink($this->counterPath);
            }
        }
    }

    /** Non Sale/POS request does not increment counter */
    public function test_tracker_does_not_increment_on_other_routes(): void
    {
        $dir = dirname($this->counterPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $backup = is_file($this->counterPath) ? file_get_contents($this->counterPath) : null;
        file_put_contents($this->counterPath, '5', LOCK_EX);

        try {
            $request = Request::create('/login', 'GET');
            $tracker = app(InsTraService::class);
            $tracker->run($request);
            $this->assertSame('5', trim(file_get_contents($this->counterPath)));
        } finally {
            file_put_contents($this->counterPath, $backup ?? '1', LOCK_EX);
        }
    }
}
