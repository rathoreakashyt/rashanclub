@extends('install.layout')

@section('title', 'Complete')

@section('steps')
    @include('install.partials.steps', ['step' => 'complete'])
@endsection

@section('content')
    <div class="text-center py-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h2 class="text-xl font-semibold text-slate-800 mb-2">Installation completed!</h2>
        <p class="text-slate-600 text-sm mb-6 max-w-md mx-auto">
            The database has been imported from the server. You can now log in and use the application.
        </p>

        <div class="mb-6 p-4 rounded-xl bg-slate-50 text-slate-800 text-sm border border-slate-200 text-left max-w-sm mx-auto">
            @if($already_installed_get_update ?? false)
                <p class="text-slate-600 mb-0">You're existing user, Login using your old credentials.</p>
            @else
                <p class="font-medium text-slate-700 mb-2">Please log in using the following credentials:</p>
                <p class="mb-1"><span class="text-slate-500">Username:</span> <strong class="font-mono">{{ $login_email }}</strong></p>
                <p class="mb-0"><span class="text-slate-500">Password:</span> <strong class="font-mono">{{ $login_password }}</strong></p>
            @endif
        </div>
        @if(!($already_installed_get_update ?? false))
            <p class="text-amber-600 text-sm font-medium mb-6">Please change your credentials after login.</p>
        @endif

        <a href="{{ $login_url }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Go to Login Page →
        </a>
    </div>
@endsection
