<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>403 - {{ __('Access Denied') }}</title>
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/theme-default.css') }}">
    <style>
        :root { --bs-body-font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #dee2e6 100%);
            padding: 1rem;
        }
        .error-page-card {
            max-width: 440px;
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.25rem 1.5rem rgba(0,0,0,.08);
        }
        .error-icon-wrap {
            width: 5rem;
            height: 5rem;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }
        .error-icon-wrap span { font-size: 2.5rem; }
        .error-code { font-size: 3.5rem; font-weight: 700; color: #dc3545; line-height: 1; margin-bottom: 0.5rem; }
        .btn-action { border-radius: 0.375rem; padding: 0.5rem 1rem; }
    </style>
</head>
<body>
    <div class="card error-page-card">
        <div class="card-body text-center p-5">
            <div class="error-icon-wrap">
                <span style="font-size: 2.5rem;">&#x1f512;</span>
            </div>
            <h1 class="error-code">403</h1>
            <h4 class="mb-2">{{ __('Access Denied') }}</h4>
            <p class="text-muted mb-4">
                {{ __('You do not have the right permissions to access this page.') }}
                @if(isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'User does not have the right permissions.')
                    <br><small class="text-body-secondary mt-2 d-block">{{ $exception->getMessage() }}</small>
                @endif
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-action">
                    {{ __('Go Back') }}
                </a>
                @auth
                    @if(Auth::user()->can('dashboard.dashboard'))
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-action">
                        {{ __('Dashboard') }}
                    </a>
                    @else
                    <a href="{{ url('/user-home') }}" class="btn btn-primary btn-action">
                        {{ __('Home') }}
                    </a>
                    @endif
                @else
                <a href="{{ route('login') }}" class="btn btn-primary btn-action">
                    {{ __('Back to Login') }}
                </a>
                @endauth
            </div>
        </div>
    </div>
</body>
</html>
