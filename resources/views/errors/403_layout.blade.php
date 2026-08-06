@extends('backend.backend_layout')
@section('page-title', __('Access Denied'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column align-items-center justify-content-center py-5">
        <div class="card shadow-sm border-0 w-100" style="max-width: 480px;">
            <div class="card-body text-center p-5">
                <div class="mb-4">
                    <span class="avatar avatar-xl rounded-circle bg-label-danger d-inline-flex align-items-center justify-content-center">
                        <i class="ti tabler-lock-off icon-lg" style="font-size: 3rem;"></i>
                    </span>
                </div>
                <h1 class="display-4 fw-bold text-danger mb-2">403</h1>
                <h4 class="mb-3">{{ __('Access Denied') }}</h4>
                <p class="text-muted mb-4">
                    {{ __('You do not have the right permissions to access this page.') }}
                    @if(isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'User does not have the right permissions.')
                        <br><small class="text-body-secondary mt-2 d-block">{{ $exception->getMessage() }}</small>
                    @endif
                </p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="ti tabler-arrow-left me-1"></i>{{ __('Go Back') }}
                    </a>
                    @if(Auth::user()->can('dashboard.dashboard'))
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="ti tabler-gauge me-1"></i>{{ __('Dashboard') }}
                    </a>
                    @else
                    <a href="{{ url('/user-home') }}" class="btn btn-primary">
                        <i class="ti tabler-home me-1"></i>{{ __('Home') }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
