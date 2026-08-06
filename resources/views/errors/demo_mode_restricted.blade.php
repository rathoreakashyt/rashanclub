@extends('backend.backend_layout')
@section('page-title', __('Demo Mode'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column align-items-center justify-content-center min-vh-75 py-5">
        <div class="card shadow-lg border-0 w-100 overflow-hidden" style="max-width: 560px;">
            {{-- Decorative header strip --}}
            <div class="position-relative overflow-hidden" style="height: 8px; background: linear-gradient(90deg, #696cff 0%, #8592ff 50%, #696cff 100%); background-size: 200% 100%; animation: shimmer 2s ease-in-out infinite;"></div>
            <div class="card-body text-center p-5">
                {{-- Icon with gradient background --}}
                <div class="mb-4">
                    <div class="avatar avatar-xl rounded-circle d-inline-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #e7e7ff 0%, #f0f0ff 100%); border: 3px solid #696cff20;">
                        <i class="ti tabler-info-circle icon-lg text-primary" style="font-size: 3.5rem;"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-2">{{ __('Demo Mode Active') }}</h4>
                <p class="text-muted mb-4 fs-6 lh-lg">
                    {{ session('demo_mode_message', __('This information could not be updated or deleted in Demo Mode.')) }}
                </p>
                <div class="bg-light rounded-3 p-4 mb-4 text-start">
                    <p class="mb-2 small fw-semibold text-dark">
                        <i class="ti tabler-info-square-rounded me-1 text-primary"></i>
                        {{ __('What you can do in Demo Mode:') }}
                    </p>
                    <ul class="mb-0 small text-muted ps-3">
                        <li>{{ __('View all data and reports') }}</li>
                        <li>{{ __('Create new records (where allowed)') }}</li>
                        <li>{{ __('Explore features and navigation') }}</li>
                    </ul>
                </div>
                <div class="bg-soft-warning rounded-3 p-4 mb-4 text-start">
                    <p class="mb-2 small fw-semibold text-dark">
                        <i class="ti tabler-lock me-1 text-warning"></i>
                        {{ __('Restricted in Demo Mode:') }}
                    </p>
                    <ul class="mb-0 small text-muted ps-3">
                        <li>{{ __('Create,Update,Delate Outlet') }}</li>
                        <li>{{ __('Deleting any data') }}</li>
                        <li>{{ __('Updating profile or password') }}</li>
                        <li>{{ __('Changing system settings') }}</li>
                    </ul>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="ti tabler-arrow-left me-1"></i>{{ __('Go Back') }}
                    </a>
                    @if(Auth::check() && Auth::user()->can('dashboard.dashboard'))
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="ti tabler-gauge me-1"></i>{{ __('Dashboard') }}
                    </a>
                    @elseif(Auth::check())
                    <a href="{{ url('/user-home') }}" class="btn btn-primary">
                        <i class="ti tabler-home me-1"></i>{{ __('Home') }}
                    </a>
                    @else
                    <a href="{{ route('login') }}" class="btn btn-primary">
                        <i class="ti tabler-login me-1"></i>{{ __('Login') }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@push('page-js')
<style>
    @keyframes shimmer {
        0%, 100% { background-position: 200% 0; }
        50% { background-position: -200% 0; }
    }
</style>
@endpush
@endsection
