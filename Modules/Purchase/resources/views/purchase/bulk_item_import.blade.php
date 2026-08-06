@extends('backend.backend_layout')
@section('page-title','Bulk Item Import')
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Bulk Item Import -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">Bulk Item Import</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => 'Purchase', 
                    'link' => '#'
                ],
                [
                    'label' => 'Bulk Item Import',
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ route('bulk-item-import-store') }}" method="POST" id="itemForm" enctype="multipart/form-data">
                    @csrf
                    <div class="card-header">
                        <h5 class="card-tile mb-0">Item Basic Information</h5>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="file">Bulk Import File {!! requiredField() !!}</label>
                                    <input type="file" class="form-control" placeholder="Item Name" name="file" id="file" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="card-footer">
                        <button type="submit" name="submit" value="submit" class="btn btn-primary add_item_submit">
                            {!! submitIconWithText('') !!}
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
    
</div>

@endsection

@push('page-js')
@routes
@endpush

