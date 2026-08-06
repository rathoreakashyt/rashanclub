@extends('auth.auth-layout')
@section('page_title', __('Login'))
@section('auth_content')

@php
    $demoCredentials = null;
    if (config('app.is_demo')) {
        $jsonPath = resource_path('demo/login.json');
        if (file_exists($jsonPath)) {
            $demoCredentials = json_decode(file_get_contents($jsonPath), true) ?? [];
        }
    }
@endphp

@if(session('success'))
    {!! insertSuccess(session('success')) !!}
@endif
@if(session('error'))
    {!! insertFailed(session('error')) !!}
@endif
@if(request('session_expired') || session('session_expired'))
    {!! insertFailed(__('Your session has expired. Please log in again.')) !!}
@endif



<!-- Login -->
<div class="card">
    <div class="card-body">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-6">
            <a href="{{ route('login') }}" class="app-brand-link login-page">
                <img src="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (getWhiteLabel('site_logo') ?? 'initial-logo.png'))) !!}" alt="Site Logo" class="app-brand-logo">
            </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-3">{{ __('Login') }}</h4>
        <form id="formAuthentication" method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-6 form-control-validation">
                <label for="email" class="form-label">{{ __('Email or Username') }} {!! requiredField() !!}</label>
                <input type="text" class="form-control" id="email" name="email" value="{{ ($demoCredentials ?? [])['email'] ?? '' }}" placeholder="{{ __('Enter your Email or Username') }}" autofocus />
                <div class="invalid-feedback">{{ $errors->first('email') }}</div>
            </div>
            <div class="mb-6 form-password-toggle form-control-validation">
                <label class="form-label" for="password">{{ __('Password') }} {!! requiredField() !!}</label>
                <div class="input-group input-group-merge">
                    <input type="password" id="password" class="form-control" name="password" value="{{ ($demoCredentials ?? [])['password'] ?? '' }}" placeholder="{{ __('Enter Your Password') }}" aria-describedby="password" />
                    <span class="input-group-text cursor-pointer">
                        <i class="icon-base ti tabler-eye-off"></i>
                    </span>
                </div>
                <div class="invalid-feedback">{{ $errors->first('password') }}</div>
            </div>
            <div class="my-4">
                <div class="d-flex justify-content-end">
                    <!-- <div class="form-check mb-0 ms-2">
                        <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                        <label class="form-check-label" for="remember-me"> Remember Me </label>
                    </div> -->
                    <a href="{{ route('password.request') }}">
                        <p class="mb-0">{{ __('Forgot Password?') }}</p>
                    </a>
                </div>
            </div>
            <button class="btn btn-primary d-grid w-100" type="submit">{{ __('Login') }}</button>
        </form>
    </div>
</div>
<!-- /Login -->
@endsection