@extends('backend.backend_layout')
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-4"><i class="ti tabler-toggle-left me-2"></i>Feature Activation</h4>
    <p class="text-muted mb-4">Enable or disable features. Disabled features will be hidden from sidebar on both web and desktop app.</p>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('feature-activation.update') }}" method="POST">
        @csrf
        @method('PUT')

        @foreach($grouped as $group => $features)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0 text-capitalize">{{ str_replace('_', ' ', $group) }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($features as $feature)
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   name="features[]" 
                                   value="{{ $feature->feature_key }}" 
                                   id="feature_{{ $feature->feature_key }}"
                                   {{ $feature->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="feature_{{ $feature->feature_key }}">
                                {{ $feature->feature_name }}
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="ti tabler-check me-1"></i>Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
