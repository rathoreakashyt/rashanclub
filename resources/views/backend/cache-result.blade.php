@extends('backend.backend_layout')
@section('page-title', $title ?? __('Cache'))
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column align-items-center justify-content-center min-vh-75 py-5">
        <div class="card shadow-lg border-0 w-100 overflow-hidden" style="max-width: 560px;">
            <div class="position-relative overflow-hidden" style="height: 8px; background: linear-gradient(90deg, #696cff 0%, #8592ff 50%, #696cff 100%);"></div>
            <div class="card-body text-center p-5">
                <div class="mb-4">
                    <div class="avatar avatar-xl rounded-circle d-inline-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #e7e7ff 0%, #f0f0ff 100%); border: 3px solid #696cff20;">
                        <i class="ti tabler-check icon-lg text-primary" style="font-size: 3.5rem;"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-2">{{ $title ?? __('Done') }}</h4>
                <p class="text-muted mb-4 fs-6 lh-lg">{{ $message ?? '' }}</p>
                @if(!empty($output))
                <div class="bg-light rounded-3 p-4 mb-4 text-start">
                    <pre class="mb-0 small text-muted" style="white-space: pre-wrap;">{{ $output }}</pre>
                </div>
                @endif
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="{{ route('cache.clear-software') }}" class="btn btn-outline-secondary">
                        <i class="ti tabler-trash me-1"></i>{{ __('Clear Software Cache') }}
                    </a>
                    <a href="{{ route('cache.clear-pwa') }}" class="btn btn-outline-secondary">
                        <i class="ti tabler-device-mobile me-1"></i>{{ __('Clear PWA Cache') }}
                    </a>
                    <a href="{{ route('cache.generate-key') }}" class="btn btn-outline-secondary">
                        <i class="ti tabler-key me-1"></i>{{ __('Generate Key') }}
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="ti tabler-gauge me-1"></i>{{ __('Dashboard') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
