@extends('backend.backend_layout')
@section('page-title', 'Installed Modules')
@push('page-css')
<style>
    .module-toggle { cursor: pointer; }
    .badge-enabled { background-color: #28a745; }
    .badge-disabled { background-color: #dc3545; }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Installed Modules') }}</h4>
        <a href="{{ route('modules.create') }}" class="btn btn-primary">
            <i class="ti tabler-plus me-1"></i> {{ __('Install Module') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if($modules->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Version') }}</th>
                            <th>{{ __('Author') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $index => $module)
                        <tr id="module-row-{{ $module->id }}">
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $module->name }}</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($module->description, 60) }}</td>
                            <td><span class="badge bg-label-info">{{ $module->version ?? '—' }}</span></td>
                            <td>{{ $module->author ?? '—' }}</td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input module-toggle" type="checkbox"
                                        data-id="{{ $module->id }}"
                                        {{ $module->is_enabled ? 'checked' : '' }}
                                        aria-label="Toggle module {{ $module->name }}">
                                </div>
                            </td>
                            <td>
                                <form action="{{ route('modules.destroy', $module->id) }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to remove this module?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-5">
                <i class="ti tabler-puzzle" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3 text-muted">{{ __('No modules installed yet.') }}</p>
                <a href="{{ route('modules.create') }}" class="btn btn-outline-primary btn-sm">
                    {{ __('Install your first module') }}
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('page-js')
<script>
document.querySelectorAll('.module-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        const moduleId = this.dataset.id;
        fetch(`{{ url('modules') }}/${moduleId}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Revert toggle on failure
                toggle.checked = !toggle.checked;
            }
        })
        .catch(() => {
            toggle.checked = !toggle.checked;
        });
    });
});
</script>
@endpush
