<?php

namespace App\Http\Controllers;

use App\Services\InstallerService;
use App\Services\UpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Update flow: verification (CodeCanyon/LP=BD), check version, download & install.
 * LP=BD also has upgrade license (package upgrade).
 */
class UpdateController extends Controller
{
    public function __construct(
        protected UpdateService $updateService,
        protected InstallerService $installerService
    ) {}

    /**
     * Show update verification form (purchase code + username).
     */
    public function updateVerification(): View|RedirectResponse
    {
        return view('update.update_verification', [
            'lpIsBd' => $this->installerService->getInstallationTypeFromEnv() === 'BD',
        ]);
    }

    /**
     * POST: Verify purchase for update; set session and redirect to update index on success.
     */
    public function verifyUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'purchase_code' => ['nullable', 'string', 'max:100'],
        ], [
            'username.required' => __('Username is required.'),
        ]);

        $installationUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/';
        $referer = $request->fullUrl();
        $path = base_path();
        $ip = $request->ip() ?? '';

        $result = $this->updateService->verifyUpdateCheck(
            $validated['username'],
            $validated['purchase_code'],
            $installationUrl,
            $ip,
            $referer,
            $path
        );

        if (!$result['success']) {
            return back()
                ->withInput()
                ->with('error', $result['message']);
        }

        Session::put('check_update_session', true);
        if ($request->user()) {
            Cache::put('update_verified_' . $request->user()->id, true, now()->addMinutes(60));
        }

        return redirect()
            ->route('update.index')
            ->with('success', $result['message']);
    }

    /**
     * Whether the current request has passed update verification (session, cache, or one-time install token).
     */
    private function hasUpdateVerification(): bool
    {
        if (Session::get('check_update_session')) {
            return true;
        }
        $user = request()->user();
        if ($user && Cache::has('update_verified_' . $user->id)) {
            return true;
        }
        return $this->consumeInstallToken();
    }

    /**
     * One-time install token set after successful download so Install step works even if session is lost.
     */
    private function consumeInstallToken(): bool
    {
        $token = request()->input('install_token');
        if (!$token) {
            return false;
        }
        $stored = Cache::pull('update_install_token');
        return $stored !== null && hash_equals($stored, $token);
    }

    /**
     * Update index: show "new version available" or "up to date". Requires verification session.
     */
    public function index(): View|RedirectResponse
    {
        if (!$this->hasUpdateVerification()) {
            return redirect()->route('update.verification');
        }

        $systemVersion = $this->normalizeVersion($this->updateService->getSystemVersion());
        $updateInfo = $this->updateService->getUpdateInfo();

        $updatedVersion = $this->normalizeVersion((string) ($updateInfo['version'] ?? ''));
        $hasNewVersion = $updatedVersion !== '' && version_compare($updatedVersion, $systemVersion, '>');

        $color = '#16a085';
        $message = __('Your software version is up to date. Current version is ') . $systemVersion;
        $updateUrl = null;
        $whatsNew = null;

        if ($hasNewVersion) {
            $color = '#4b7bec';
            $message = __('A NEW VERSION IS AVAILABLE');
            $updateUrl = route('update.do');
            $whatsNew = $updateInfo['whats_new'] ?? null;
        }

        return view('update.index', [
            'color' => $color,
            'message' => $message,
            'updateUrl' => $updateUrl,
            'whatsNew' => $whatsNew,
            'systemVersion' => $systemVersion,
            'lpIsBd' => $this->installerService->getInstallationTypeFromEnv() === 'BD',
        ]);
    }

    /**
     * Download update zip and extract to _temp. Expects AJAX or normal request.
     */
    public function doUpdate(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->hasUpdateVerification()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => __('Session expired. Please verify again.')], 403);
            }
            return redirect()->route('update.verification');
        }

        $updateInfo = $this->updateService->getUpdateInfo();
        if (!$updateInfo || empty($updateInfo['url'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Could not get update URL.'),
                    'action' => url('/'),
                    'caption' => __('Contact Us'),
                ]);
            }
            return redirect()->route('update.index')->with('error', __('Could not get update URL.'));
        }

        $zipPath = base_path('build.zip');
        $downloaded = $this->updateService->downloadFile($updateInfo['url'], $zipPath);

        if (!$downloaded || !is_file($zipPath)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Download failed.'),
                    'action' => url('/'),
                    'caption' => __('Contact Us'),
                ]);
            }
            return redirect()->route('update.index')->with('error', __('Download failed.'));
        }

        $extracted = $this->updateService->extractUpdateZip($zipPath);
        if (!$extracted) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Could not extract package.'),
                    'action' => url('/'),
                    'caption' => __('Contact Us'),
                ]);
            }
            return redirect()->route('update.index')->with('error', __('Could not extract package.'));
        }

        if ($request->expectsJson()) {
            $installToken = Str::random(64);
            Cache::put('update_install_token', $installToken, now()->addMinutes(5));

            return response()->json([
                'status' => 'success',
                'message' => __('Downloaded successfully!'),
                'action' => route('update.install'),
                'caption' => __('Install Update'),
                'install_token' => $installToken,
            ]);
        }

        return redirect()->route('update.index')
            ->with('success', __('Downloaded successfully.') . ' ' . __('Click "Install Update" to continue.'));
    }

    /**
     * Install update from _temp (installer.json + recurse copy). Updates version file.
     */
    public function installUpdate(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->hasUpdateVerification()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => __('Session expired.')], 403);
            }
            return redirect()->route('update.verification');
        }

        $updateInfo = $this->updateService->getUpdateInfo();
        $newVersion = $updateInfo['version'] ?? $this->updateService->getSystemVersion();

        $result = $this->updateService->installUpdate($newVersion);

        Session::forget('check_update_session');
        if ($request->user()) {
            Cache::forget('update_verified_' . $request->user()->id);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'action' => url('/'),
                'caption' => __('Login Now'),
            ]);
        }

        if ($result['success']) {
            return redirect()->to(url('/'))->with('success', $result['message']);
        }

        return redirect()->route('update.index')->with('error', $result['message']);
    }

    /**
     * Show upgrade license form (LP=BD package upgrade).
     */
    public function upgradeLicense(): View
    {
        $status = session('upgrade_status');
        $message = session('upgrade_message');
        $color = session('upgrade_color', 'red');

        return view('update.package_upgrade', [
            'status' => $status,
            'message' => $message,
            'color' => $color,
            'lpIsBd' => $this->installerService->getInstallationTypeFromEnv() === 'BD',
        ]);
    }

    /**
     * POST: Verify upgrade license (LP=BD).
     */
    public function submitUpgradeLicense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'purchase_code' => ['nullable', 'string', 'max:100'],
            'upgrade_code' => ['required', 'string', 'max:100'],
        ], [
            'username.required' => __('Username is required.'),
            'upgrade_code.required' => __('Upgrade code is required.'),
        ]);

        $installationUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/';
        $referer = $request->fullUrl();
        $path = base_path();
        $ip = $request->ip() ?? '';

        $result = $this->updateService->verifyUpgradeLicense(
            $validated['username'],
            $validated['purchase_code'],
            $validated['upgrade_code'],
            $installationUrl,
            $ip,
            $referer,
            $path
        );

        if ($result['success']) {
            return redirect()
                ->route('update.upgrade-license')
                ->with('upgrade_status', 'success')
                ->with('upgrade_message', $result['message'])
                ->with('upgrade_color', 'green')
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('update.upgrade-license')
            ->withInput()
            ->with('upgrade_status', 'error')
            ->with('upgrade_message', $result['message'])
            ->with('upgrade_color', 'red')
            ->with('error', $result['message']);
    }

    /**
     * Normalize version string for comparison: trim and strip leading 'v'.
     */
    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        return $version === '' ? '' : preg_replace('/^v/i', '', $version);
    }
}
