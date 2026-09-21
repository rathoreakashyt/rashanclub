<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Modules\Configuration\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $user = Auth::user();
        $isSaas = strtolower((string) ($user->is_saas ?? '')) === 'check';
        if (!$isSaas) {
            if ($user->del_status === 'Deleted' || $user->active_status === 'Inactive') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                throw ValidationException::withMessages([
                    'email' => __('Your account is deactivated or deleted and cannot sign in.'),
                ]);
            }
        }

        $company = $user->company_id
            ? Company::find($user->company_id)
            : null;
        $company = $company ?? Company::first();

        if (!$company) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                'email' => __('No company is assigned to your account. Please contact administrator.'),
            ]);
        }

        // White Label
        if (isset($company->white_label) && $company->white_label) {
            $whiteLabelData = json_decode($company->white_label, true);
            if ($whiteLabelData) {
                $request->session()->put('white_label', [
                    'site_name' => $whiteLabelData['site_name'] ?? null,
                    'site_footer' => $whiteLabelData['site_footer'] ?? null,
                    'site_title' => $whiteLabelData['site_title'] ?? null,
                    'site_link' => $whiteLabelData['site_link'] ?? null,
                    'site_logo' => $whiteLabelData['site_logo'] ?? null,
                    'site_favicon' => $whiteLabelData['site_favicon'] ?? null
                ]);
            }
        } else {
            $request->session()->put('white_label', [
                'site_name' => null,
                'site_footer' => null,
                'site_title' => null,
                'site_link' => null,
                'site_logo' => null,
                'site_favicon' => null
            ]);
        }

        $request->session()->put('company', [
            'company_id' => $company->id,
            'business_name' => $company->business_name,
            'short_name' => $company->short_name,
            'company_email' => $company->email,
            'date_format' => $company->date_format,
            'zone_name' => $company->zone_name,
            'currency' => $company->currency,
            'currency_position' => $company->currency_position,
            'precision' => $company->precision,
            'default_customer' => $company->default_customer,
            'default_cursor_position' => $company->default_cursor_position,
            'product_display' => $company->product_display,
            'onscreen_keyboard_status' => $company->onscreen_keyboard_status,
            'default_payment' => $company->default_payment,
            'payment_settings' => $company->payment_settings,
            'company_address' => $company->address,
            'inv_logo_is_show' => $company->inv_logo_is_show,
            'invoice_logo' => $company->invoice_logo,
            'invoice_configuration' => $company->invoice_configuration,
            'collect_tax' => $company->collect_tax,
            'tax_title' => $company->tax_title,
            'tax_registration_no' => $company->tax_registration_no,
            'tax_is_gst' => $company->tax_is_gst,
            'sms_enable_status' => $company->sms_enable_status,
            'smtp_enable_status' => $company->smtp_enable_status,
            'e_commerce_checker' => $company->e_commerce_checker,
            'white_label_status' => $company->white_label_status,
            'thousands_separator' => $company->thousands_separator,
            'decimals_separator' => $company->decimals_separator,
            'purchase_price_show_hide' => $company->purchase_price_show_hide,
            'allow_less_sale' => $company->allow_less_sale,
            'is_rounding_enable' => $company->is_rounding_enable,
            'direct_cart' => $company->direct_cart,
            'register_content' => $company->register_content,
            'grocery_experience' => $company->grocery_experience,
            'generic_name_search_option' => $company->generic_name_search_option,
            'product_code_start_from' => $company->product_code_start_from,
            'invoice_configuration' => $company->invoice_configuration,
            'smtp_default_selected_in_pos' => $company->smtp_default_selected_in_pos,
            'sms_default_selected_in_pos' => $company->sms_default_selected_in_pos,
            'whatsapp_default_selected_in_pos' => $company->whatsapp_default_selected_in_pos,
            'invoice_footer' => $company->invoice_footer,
            'term_conditions' => $company->term_conditions,
        ]);

        // Put user in session
        $photoUrl = $user->photo
            ? asset('uploads/' . $user->photo)
            : asset('uploads/dummy_images/admin.png');

        // Fetch Role name and keep it session
        $roleName = $user->roles->first()->name ?? '';

        $request->session()->put('user', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'photo' => $photoUrl,
            'role' => $roleName,
        ]);

        // before go to dashboard check if the outlet is set
        if (!session()->has('outlet.outlet_id')) {
            return redirect()->route('outlet.index')->with('error', 'Please select an outlet first.');
        } else {
            // Cashier/Salesman → direct POS (no dashboard)
            $userRole = Auth::user()->roles->first()->name ?? '';
            if (in_array($userRole, ['Cashier', 'Salesman'])) {
                return redirect()->route('pos.index');
            }
            // if have dashboard access then redirect to dashboard else home page
            if (Auth::user()->hasPermissionTo('dashboard.dashboard')) {
                return redirect()->intended(route('dashboard', absolute: false));
            } else {
                return redirect()->route('home');
            }
        }
    }

    public function sendEmailForVarify(Request $request)
    {
        $validationRules = [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
        $request->validate($validationRules);
        try {
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return redirect()->back()->with('error', 'User not found');
            }
            return view('auth.security-question', compact('user'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function securityQuestion($auth_id = '')
    {
        $user = User::findOrFail(decrypt($auth_id));
        return view('auth.security-question', compact('user'));
    }

    public function sendQuestionForVarify(Request $request)
    {
        $data = [];
        $validationRules = [
            'security_question' => ['required', 'string', 'max:255'],
            'security_answer' => ['required', 'string', 'max:255'],
            'auth_id' => ['required', 'string']
        ];
        $auth_id = decrypt($request->auth_id);
        $user = User::findOrFail($auth_id);
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return redirect()->route('security.question', $request->auth_id)->withErrors($validator)->withInput();
        }
        if (!$user) {
            return redirect()->route('security.question', $request->auth_id)->with('error', 'User not found');
        }
        if (trim($user->question) != trim($request->security_question)) {
            return redirect()->route('security.question', $request->auth_id)->with('error', 'Security question is incorrect');
        }
        if (trim($user->answer) != trim($request->security_answer)) {
            return redirect()->route('security.question', $request->auth_id)->with('error', 'Security answer is incorrect');
        }
        return view('auth.new-password-set', compact('user'));
    }

    public function setNewPassword(Request $request)
    {
        $validationRules = [
            'password' => ['required', 'string', 'min:6'],
            'confirm-password' => ['required', 'string', 'min:6', 'same:password'],
        ];
        $auth_id = decrypt($request->auth_id);
        $user = User::findOrFail($auth_id);
        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->fails()) {
            return view('auth.new-password-set', compact('user'))->withInput($request->all())->withErrors($validator);
        }
        if (!$user) {
            return view('auth.new-password-set', compact('user'))->with('error', 'User not found');
        }
        $user->password = Hash::make($request->password);
        $user->save();
        return redirect()->route('login')->with('success', 'Password updated successfully');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget('company');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['redirect' => url('/')]);
        }

        return redirect('/');
    }
}
