<?php

namespace App\Http\Controllers;

use App\Services\InstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function __construct(
        protected InstallerService $installer
    ) {}

    /**
     * Step 1: Welcome.
     */
    public function welcome(): View
    {
        return view('install.welcome');
    }

    /**
     * Step 2: Server environment checklist.
     */
    public function environment(): View|RedirectResponse
    {
        $result = $this->installer->getEnvironmentChecks();
        return view('install.environment', $result);
    }

    /**
     * Step 3: Purchase verification form.
     */
    public function purchase(): View|RedirectResponse
    {
        return view('install.purchase');
    }

    /**
     * Step 3 POST: Verify purchase with API, then redirect to database on success.
     */
    public function verifyPurchase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'purchase_code' => ['nullable', 'string', 'max:255'],
            'already_installed_get_update' => ['nullable'],
        ], [
            'username.required' => 'Username is required.',
            'already_installed_get_update.boolean' => 'Already installed get update must be yes or no.',
        ]);

        $alreadyInstalledGetUpdate = $request->boolean('already_installed_get_update');
        $installationUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/';
        $referer = $request->fullUrl();
        $path = base_path();
        $ip = $request->ip() ?? '';

        $result = $this->installer->verifyPurchase(
            $validated['username'],
            $validated['purchase_code'],
            $installationUrl,
            $ip,
            $referer,
            $path,
            $alreadyInstalledGetUpdate
        );

        $allowNextStep = $result['success'];
        if (!$result['success'] && $alreadyInstalledGetUpdate) {
            $currentInstalledUrl = $result['current_installed_url'] ?? null;
            $normalizedCurrent = $currentInstalledUrl ? rtrim($currentInstalledUrl, '/') . '/' : '';
            $normalizedInstall = rtrim($installationUrl, '/') . '/';
            if ($currentInstalledUrl && $normalizedCurrent === $normalizedInstall) {
                $allowNextStep = true;
            }
        }

        if (!$allowNextStep) {
            $errorMsg = $result['message'];
            if ($alreadyInstalledGetUpdate && !$result['success']) {
                $currentInstalledUrl = $result['current_installed_url'] ?? null;
                if (!$currentInstalledUrl || rtrim($currentInstalledUrl, '/') . '/' !== rtrim($installationUrl, '/') . '/') {
                    $errorMsg .= ' Installation URL mismatch.';
                }
            }
            return back()
                ->withInput()
                ->with('error', $errorMsg);
        }

        Session::put('install_purchase', [
            'username' => $validated['username'],
            'purchase_code' => $validated['purchase_code'],
            'installation_status' => $result['installation_status'],
            'installation_url' => $installationUrl,
            'already_installed_get_update' => $alreadyInstalledGetUpdate,
        ]);

        return redirect()->route('install.database')
            ->with('success', $result['message']);
    }

    /**
     * Step 4: Database configuration form.
     */
    public function database(): View|RedirectResponse
    {
        if (!Session::has('install_purchase')) {
            return redirect()->route('install.purchase');
        }

        $db = Session::get('install_db', []);
        return view('install.database', [
            'db_host' => $db['db_host'] ?? '127.0.0.1',
            'db_port' => $db['db_port'] ?? '3306',
            'db_username' => $db['db_username'] ?? '',
            'db_password' => $db['db_password'] ?? '',
            'db_name' => $db['db_name'] ?? '',
            'create_db' => $db['create_db'] ?? true,
        ]);
    }

    /**
     * Step 4 POST: Validate DB, test connection, write .env, import database from server, then redirect to complete.
     */
    public function storeDatabase(Request $request): RedirectResponse
    {
        $rules = [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string'],
            'db_name' => ['required', 'string', 'max:255'],
            'create_db' => ['nullable', 'boolean'],
        ];

        $attributes = [
            'db_host' => 'Database Host',
            'db_port' => 'Port',
            'db_username' => 'Database Username',
            'db_password' => 'Database Password',
            'db_name' => 'Database Name',
            'create_db' => 'Create database if not exists',
        ];

        $messages = [
            'db_host.required' => 'Database Host is required.',
            'db_host.max' => 'Database Host must not exceed 255 characters.',
            'db_port.max' => 'Port must not exceed 10 characters.',
            'db_username.required' => 'Database Username is required.',
            'db_username.max' => 'Database Username must not exceed 255 characters.',
            'db_name.required' => 'Database Name is required.',
            'db_name.max' => 'Database Name must not exceed 255 characters.',
            'create_db.boolean' => 'Create database option must be yes or no.',
        ];

        $validated = $request->validate($rules, $messages, $attributes);

        $host = $validated['db_host'];
        $port = $validated['db_port'] ?? '3306';
        $username = $validated['db_username'];
        $password = $validated['db_password'] ?? '';
        $database = $validated['db_name'];
        $createDb = $request->boolean('create_db');

        $result = $this->installer->testDatabaseConnection(
            $host,
            $port,
            $username,
            $password,
            $database,
            $createDb
        );

        if (!$result['success']) {
            return back()
                ->withInput()
                ->with('error', $result['message']);
        }

        $installDb = [
            'db_host' => $host,
            'db_port' => $port,
            'db_username' => $username,
            'db_password' => $password,
            'db_name' => $database,
            'create_db' => $createDb,
        ];
        Session::put('install_db', $installDb);
        $this->installer->saveInstallDbState($installDb);

        $appKey = 'base64:' . $this->installer->generateAppKey();
        $appUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/');

        $writeOk = $this->installer->writeEnv([
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $database,
            'db_username' => $username,
            'db_password' => $password,
            'app_url' => $appUrl,
            'app_key' => $appKey,
        ]);

        if (!$writeOk) {
            return back()
                ->withInput()
                ->with('error', 'Could not write .env file. Check permissions on project root.');
        }

        $purchase = Session::get('install_purchase');
        if (!$purchase) {
            return back()
                ->withInput()
                ->with('error', 'Purchase session expired. Please start from the purchase verification step.');
        }

        $importResult = $this->installer->connectDatabase(
            [
                'db_host' => $host,
                'db_port' => $port,
                'db_name' => $database,
                'db_username' => $username,
                'db_password' => $password,
            ],
            $purchase['username'],
            $purchase['purchase_code'],
            $purchase['installation_url'] ?? $appUrl . '/',
            $appKey,
            $purchase['already_installed_get_update'] ?? false
        );

        if (!$importResult['success']) {
            return back()
                ->withInput()
                ->with('error', $importResult['message']);
        }

        $this->installer->setInstalled();
        Config::set('app.key', $appKey);
        App::forgetInstance('encrypter');
        $this->installer->secFileW(
            $purchase['username'],
            $purchase['purchase_code'],
            $appUrl . '/',
            '11.0',
            $appKey
        );
        $this->installer->clearInstallDbState();
        $alreadyInstalledGetUpdate = $purchase['already_installed_get_update'] ?? false;
        Session::forget(['install_db', 'install_app_key', 'install_purchase']);

        return redirect()->route('install.complete', [
            'already_installed_get_update' => $alreadyInstalledGetUpdate ? '1' : '0',
        ])->with('success', $importResult['message']);
    }

    /**
     * Step 5: Installation complete (login credentials + button).
     */
    public function complete(): View|RedirectResponse
    {
        if (!$this->installer->isInstalled()) {
            return redirect()->route('install.welcome');
        }

        $appUrl = rtrim(request()->getSchemeAndHttpHost() . request()->getBasePath(), '/');

        return view('install.complete', [
            'login_url' => $appUrl,
            'login_email' => 'admin@example.com',
            'login_password' => '123456',
            'already_installed_get_update' => request()->query('already_installed_get_update') === '1',
        ]);
    }

    /**
     * Show uninstall license form (when app is installed).
     */
    public function uninstall(): View
    {
        $status = session('uninstall_status');
        $message = session('uninstall_message');
        $color = session('uninstall_color', 'red');

        return view('install.uninstall', [
            'status' => $status,
            'message' => $message,
            'color' => $color,
        ]);
    }

    /**
     * POST: Uninstall / transfer license via API.
     */
    public function uninstallLicense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'purchase_code' => ['nullable', 'string', 'max:100'],
            'owner' => ['nullable', 'string', 'max:100'],
            'current_installation_url' => ['nullable', 'string', 'max:500'],
            'new_installation_url' => ['nullable', 'string', 'max:500'],
            'action_type' => ['nullable', 'string', 'in:uninstall,transfer'],
            'base_url_install' => ['nullable', 'string', 'max:500'],
        ], [
            'username.required' => __('Username is required.'),
        ]);

        $installationUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/';
        $referer = $request->fullUrl();
        $path = base_path();
        $ip = $request->ip() ?? '';

        $params = [
            'username' => $validated['username'],
            'purchase_code' => $validated['purchase_code'],
            'owner' => $validated['owner'] ?? 'local',
            'current_installation_url' => $validated['current_installation_url'] ?? $installationUrl,
            'new_installation_url' => $validated['new_installation_url'] ?? '',
            'action_type' => $validated['action_type'] ?? 'uninstall',
            'base_url_install' => $validated['base_url_install'] ?? $installationUrl,
        ];

        $result = $this->installer->uninstallLicense($params, $ip, $referer, $path);

        if ($result['success']) {
            $this->installer->removeSecurityFiles();
            $this->installer->removeEnvFile();
            return redirect()
                ->route('uninstall.license')
                ->with('uninstall_status', 'success')
                ->with('uninstall_message', $result['message'])
                ->with('uninstall_color', 'green')
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('uninstall.license')
            ->withInput()
            ->with('uninstall_status', 'error')
            ->with('uninstall_message', $result['message'])
            ->with('uninstall_color', 'red')
            ->with('error', $result['message']);
    }
}
