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
            <h4 class="mb-1">Security Question 🔒</h4>
            <p class="mb-6">Enter your security question and answer to reset your password</p>
            <form id="formAuthentication" class="mb-6 fv-plugins-bootstrap5 fv-plugins-framework" action="{{ route('send.question.for.varify') }}" method="POST" novalidate="novalidate">
                @csrf
                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                    <label for="security_question" class="form-label">Security Question {!! requiredField() !!}</label>
                    <select class="form-control select2" id="security_question" name="security_question" required>
                        <option value="">Select a security question</option>
                        <option value="What is your mother's maiden name?" {{ old('security_question') == "What is your mother's maiden name?" ? 'selected' : '' }}>What is your mother's maiden name?</option>
                        <option value="What is the name of your first pet?" {{ old('security_question') == "What is the name of your first pet?" ? 'selected' : '' }}>What is the name of your first pet?</option>
                        <option value="What is the name of the town you were born?" {{ old('security_question') == "What is the name of the town you were born?" ? 'selected' : '' }}>What is the name of the town you were born?</option>
                        <option value="What primary school did you attend?" {{ old('security_question') == "What primary school did you attend?" ? 'selected' : '' }}>What primary school did you attend?</option>
                        <option value="What Is your favorite book?" {{ old('security_question') == "What Is your favorite book?" ? 'selected' : '' }}>What Is your favorite book?</option>
                        <option value="What was the first company that you worked for?" {{ old('security_question') == "What was the first company that you worked for?" ? 'selected' : '' }}>What was the first company that you worked for?</option>
                        <option value="What is your favorite food?" {{ old('security_question') == "What is your favorite food?" ? 'selected' : '' }}>What is your favorite food?</option>
                        <option value="Where did you meet your spouse?" {{ old('security_question') == "Where did you meet your spouse?" ? 'selected' : '' }}>Where did you meet your spouse?</option>
                        <option value="Where is your favorite place to vacation?" {{ old('security_question') == "Where is your favorite place to vacation?" ? 'selected' : '' }}>Where is your favorite place to vacation?</option>
                        <option value="What is the name of the road you grew up on?" {{ old('security_question') == "What is the name of the road you grew up on?" ? 'selected' : '' }}>What is the name of the road you grew up on?</option>
                    </select>
                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                    <div class="invalid-feedback">{{ $errors->first('security_question') }}</div>
                </div>

                <div class="mb-6 form-control-validation fv-plugins-icon-container">
                    <label for="security_answer" class="form-label">Security Answer {!! requiredField() !!}</label>
                    <input type="text" class="form-control" id="security_answer" name="security_answer" placeholder="Enter your security answer" value="{{ isset($request->security_answer) ? $request->security_answer : old('security_answer') }}" required>
                    <div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                    <div class="invalid-feedback">{{ $errors->first('security_answer') }}</div>
                    <input type="hidden" name="auth_id" value="{{ encrypt($user->id) }}">
                </div>
                <button type="submit" value="submit" name="submit" class="btn btn-primary d-grid w-100 waves-effect waves-light">Click to next step</button>
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
