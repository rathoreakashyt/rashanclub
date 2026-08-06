@extends('auth.auth-layout')
@section('auth_content')

@if(session('success'))
    {!! insertSuccess(session('success')) !!}
@endif
@if(session('error'))
    {!! insertFailed(session('error')) !!}
@endif

<div class="card">
    <div class="card-body">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-6">
            <a href="{{ route('login') }}" class="app-brand-link login-page">
                <img src="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (getWhiteLabel('site_logo') ?? 'initial-logo.png'))) !!}" alt="Site Logo" class="app-brand-logo">
            </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-1">Reset Password 🔒</h4>
        <p class="mb-6">
            <span class="fw-medium">Your new password</span>
        </p>
        <form id="formAuthentication" action="{{ route('set.new.password') }}" method="POST" class="fv-plugins-bootstrap5 fv-plugins-framework" novalidate="novalidate">
            @csrf
            <div class="mb-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                <label class="form-label" for="password">New Password {!! requiredField() !!}</label>
                <div class="input-group input-group-merge has-validation">
                    <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="············" aria-describedby="password" value="{{ old('password') }}">
                    <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                </div>
                <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                <div class="invalid-feedback">{{ $errors->first('password') }}</div>
            </div>
            <div class="mb-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                <label class="form-label" for="confirm-password">Confirm Password {!! requiredField() !!}</label>
                <div class="input-group input-group-merge has-validation">
                    <input type="password" id="confirm-password" class="form-control @error('confirm-password') is-invalid @enderror" name="confirm-password" placeholder="············" aria-describedby="password" value="{{ old('confirm-password') }}">
                    <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                </div>
                <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                <div class="invalid-feedback">{{ $errors->first('confirm-password') }}</div>
                <input type="hidden" name="auth_id" value="{{ encrypt($user->id) }}">
            </div>
            <button class="btn btn-primary d-grid w-100 mb-6 waves-effect waves-light">Set new password</button>
            <div class="text-center">
                <a href="{{ route('login') }}" class="d-flex justify-content-center">
                    <i class="icon-base ti tabler-chevron-left scaleX-n1-rtl me-1_5"></i>
                    Back to login
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
