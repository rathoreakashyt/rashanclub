@extends('auth.auth-layout')
@section('page_title', 'Forgot Password')
@section('auth_content')
    <div class="card">
        <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center mb-6">
                <a href="{{ route('login') }}" class="app-brand-link login-page">
                    <img src="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (getWhiteLabel('site_logo') ?? 'initial-logo.png'))) !!}" alt="Site Logo" class="app-brand-logo">
                </a>
            </div>
            <!-- /Logo -->
            <h4 class="mb-1">Forgot Password? 🔒</h4>
            <p class="mb-6">Enter your email and we'll send you instructions to reset your password</p>
            <form id="formAuthentication" class="mb-6 fv-plugins-bootstrap5 fv-plugins-framework" action="{{ route('send.email.for.varify') }}" method="POST" novalidate="novalidate">
                @csrf
                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                    <label for="email" class="form-label">Email {!! requiredField() !!}</label>
                    <input type="text" class="form-control" id="email" name="email" placeholder="Enter your email" autofocus="">
                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                    <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                </div>
                <button class="btn btn-primary d-grid w-100 waves-effect waves-light">Click to next step</button>
            </form>
            <div class="text-center">
                <a href="{{ route('login') }}" class="d-flex justify-content-center">
                    <i class="icon-base ti tabler-chevron-left scaleX-n1-rtl me-1_5"></i>
                    Back to login
                </a>
            </div>
        </div>
    </div>
@endsection
