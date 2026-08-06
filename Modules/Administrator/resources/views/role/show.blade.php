@extends('backend.backend_layout')
@section('page-title', __('Role') . ' ' . __('Details'))
@push('page-css')
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Role') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Role'), 
                    'link' => route('role.index')
                ],
                [
                    'label' => __('Role') . ' ' . __('Details'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Role') }} {{ __('Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Role Name') }}</label>
                        <p class="form-control-static">{{ $role->name }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Created At') }}</label>
                        <p class="form-control-static">{{ $role->created_at->format('d M Y H:i A') }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Updated At') }}</label>
                        <p class="form-control-static">{{ $role->updated_at->format('d M Y H:i A') }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Users') }} {{ __('Count') }}</label>
                        <p class="form-control-static">{{ $role->users->count() }}</p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        @if (auth()->user()->can('role-edit'))
                        <a href="{{ route('role.edit', $role->encrypted_id) }}" class="btn btn-warning waves-effect waves-light me-2">
                            <i class="ti tabler-edit me-1"></i> {{ __('Edit') }}
                        </a>
                        @endif
                        <a href="{{ route('role.index') }}" class="btn btn-primary waves-effect waves-light">
                            <i class="ti tabler-arrow-left me-1"></i> {{ __('Back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Role Permissions') }}</h5>
                </div>
                <div class="card-body">
                    @if($role->permissions->count() > 0)
                        @foreach($role->permissions->groupBy('group_name') as $group => $permissions)
                        <div class="mb-4">
                            <h6 class="fw-medium mb-2">{{ Str::title(str_replace('_', ' ', $group)) }}</h6>
                            <div class="row">
                                @foreach($permissions as $permission)
                                <div class="col-md-6 mb-2">
                                    <span class="badge bg-label-primary">{{ Str::title(explode('-', $permission->name)[1]) }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted">{{ __('No permissions assigned') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    @if($role->users->count() > 0)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Users with this Role') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('SN') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($role->users as $index => $user)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->phone ?? __('N/A') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@endsection

@push('page-js')
@endpush
