@extends('backend.backend_layout')
@section('page-title', isset($role) ? __('Update') . ' ' . __('Role') : __('Add') . ' ' . __('Role'))
@push('page-css')
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($role) ? __('Update') : __('Add') }} {{ __('Role') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Role'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($role) ? __('Update') : __('Add') . ' ' . __('Role'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="row">
        <div class="col-12">
            <form id="addRoleForm" action="{{ isset($role) ? route('role.update', $role->encrypted_id) : route('role.store') }}" method="POST">
            @csrf
            @if(isset($role))
                @method('PUT')
            @endif
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 form-control-validation mb-3">
                            <label class="form-label" for="modalRoleName">{{ __('Role Name') }}</label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                class="form-control @error('name') is-invalid @enderror" 
                                placeholder="{{ __('Enter Role Name') }}"
                                value="{{ isset($role) ? $role->name : old('name') }}"
                            />
                            @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <!-- Permission table -->
                            <div class="row mt-3">
                                <div class="col-12 mb-4">
                                    <div class="d-flex align-items-center">
                                        <h6 class="fw-medium mb-0">{{ __('Role Permissions') }}</h6>
                                        <i class="icon-base ti tabler-info-circle icon-xs ms-2" 
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="top" 
                                        title="{{ __('Allows full access to the system') }}"></i>
                                        <div class="form-check mb-0 ms-4">
                                            <input class="form-check-input" type="checkbox" id="selectAll" />
                                            <label class="form-check-label" for="selectAll">{{ __('Select All') }}</label>
                                        </div>
                                    </div>
                                </div>

                                @foreach ($permissions as $group => $permission_list)
                                <div class="col-lg-3 col-md-4 col-sm-6 col-6 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">{{ Str::title(str_replace('_', ' ', $group)) }}</h5>
                                        </div>
                                        <div class="card-body">
                                            @foreach($permission_list as $permission)
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                    type="checkbox" 
                                                    name="permissions[]" 
                                                    id="permission_{{ $permission->id }}" 
                                                    value="{{ $permission->id }}"
                                                    @if(isset($role) && $role->hasPermissionTo($permission->name)) checked @endif />
                                                <label class="form-check-label" for="permission_{{ $permission->id }}">
                                                    {{ Str::title(str_replace('_', ' ', explode('-', $permission->name)[1])) }}
                                                </label>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <!-- Permission table -->
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <button type="submit" name="submit" value="submit" class="btn btn-primary waves-effect waves-light me-4">
                            {!! submitIconWithText(isset($role) ? true : false) !!}
                        </button>
                        <a href="{{ route('role.index') }}" type="button" class="btn btn-primary waves-effect waves-light">
                            {!! backIconWithText() !!}
                        </a>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('page-js')
<script>
    $(document).ready(function() {
        // Select all functionality
        $('#selectAll').change(function() {
            if ($(this).is(':checked')) {
                $('input[name="permissions[]"]').prop('checked', true);
            } else {
                $('input[name="permissions[]"]').prop('checked', false);
            }
        });
        
        // Uncheck select all if any permission is unchecked
        $('input[name="permissions[]"]').change(function() {
            if ($('input[name="permissions[]"]:checked').length == $('input[name="permissions[]"]').length) {
                $('#selectAll').prop('checked', true);
            } else {
                $('#selectAll').prop('checked', false);
            }
        });
        
        // Check if all permissions are selected on page load
        if ($('input[name="permissions[]"]:checked').length == $('input[name="permissions[]"]').length) {
            $('#selectAll').prop('checked', true);
        }
    });
</script>
@endpush

