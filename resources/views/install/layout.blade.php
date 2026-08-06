<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(defined('LP') && strtoupper((string) LP) === 'BD' && defined('LP_BD_FAVICON_LOGO_DATA_URI'))
    <link rel="icon" href="{{ constant('LP_BD_FAVICON_LOGO_DATA_URI') }}" type="image/png">
    @else
    <link rel="icon" href="{{ asset('uploads/business_setting/initial-fav.ico') }}" type="image/x-icon">
    @endif
    <title>@yield('title', 'install') – Rashan Ki Dukan - Digital Akash</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; min-height: 100vh; -webkit-font-smoothing: antialiased; }
        .container { max-width: 42rem; margin: 0 auto; padding: 2rem 1rem; }
        .text-center { text-align: center; }
        .mb-8 { margin-bottom: 2rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .h-12 { height: 3rem; }
        .object-contain { object-fit: contain; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .gap-2 { gap: 0.5rem; }
        .bg-white { background: #fff; }
        .rounded-2xl { border-radius: 1rem; }
        .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .border { border: 1px solid #e2e8f0; }
        .overflow-hidden { overflow: hidden; }
        .p-6 { padding: 1.5rem; }
        .sm\:p-8 { padding: 2rem; }
        @media (min-width: 640px) { .sm\:p-8 { padding: 2rem; } }
        .p-4 { padding: 1rem; }
        .rounded-xl { border-radius: 0.75rem; }
        .text-sm { font-size: .875rem; }
        .text-xs { font-size: .75rem; }
        .text-slate-400 { color: #94a3b8; }
        .text-emerald-800 { color: #065f46; }
        .text-red-800 { color: #991b1b; }
        .bg-emerald-50 { background: #ecfdf5; }
        .bg-red-50 { background: #fef2f2; }
        .border-emerald-200 { border-color: #a7f3d0; }
        .border-red-200 { border-color: #fecaca; }
        .list-disc { list-style-type: disc; }
        .list-inside { list-style-position: inside; }
        .space-y-1 > * + * { margin-top: 0.25rem; }
        .font-semibold { font-weight: 600; }
        .mt-6 { margin-top: 1.5rem; }
        input, textarea, select { height: 40px; padding: 10px 20px !important; }
        input:focus { border: none !important; }
</head>
<body style="background:#f1f5f9;min-height:100vh;-webkit-font-smoothing:antialiased" @if(defined('LP') && strtoupper((string) LP) === 'BD') data-lp="BD" @endif>
    <div class="container">
        {{-- Header --}}
        <header class="text-center mb-8">
            @if(defined('LP') && strtoupper((string) LP) === 'BD' && defined('LP_BD_FAVICON_LOGO_DATA_URI'))
            <img src="{{ constant('LP_BD_FAVICON_LOGO_DATA_URI') }}" alt="Logo" class="h-12 mx-auto mb-3 object-contain" id="install-header-logo">
            @else
            <img src="{{ asset('uploads/business_setting/initial-logo.png') }}" alt="{{ config('app.name', 'Rashan Ki Dukan') }}" class="h-12 mx-auto mb-3 object-contain" id="install-header-logo">
            @endif
        </header>

        {{-- Step indicator --}}
        @hasSection('steps')
            <nav class="flex items-center justify-center gap-2 mb-8" aria-label="Installation steps">
                @yield('steps')
            </nav>
        @endif

        {{-- Card --}}
        <main class="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div class="p-6 sm:p-8">
                @if (session('success'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm border border-emerald-200">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">
                        <p class="font-semibold mb-2">Validation failed. Please fix the following:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        <p class="text-center text-slate-400 text-xs mt-6" id="install-footer-wrap">
            @if(defined('LP') && strtoupper((string) LP) === 'BD')
            <span id="app-footer-text"></span>
            @else
            &copy; {{ date('Y') }} {{ config('app.name', 'Ratail POS') }}
            @endif
        </p>
    </div>
    @if(defined('LP') && strtoupper((string) LP) === 'BD')
    <script src="{{ asset('backend_assets/js/custom_js/backend_brand.js') }}"></script>
    @endif
</body>
</html>
