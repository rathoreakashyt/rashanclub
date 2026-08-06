@extends('backend.backend_layout')
@section('page-title', __('Bulk Item Import'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Item -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Bulk Item Import') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Items'), 
                    'link' => '#'
                ],
                [
                    'label' => __('Bulk Item Import'),
                    'active' => true
                ]
            ]
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                @if (session('success'))
                    <div class="alert alert-success m-4" role="alert">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->has('file'))
                    <div class="alert alert-danger m-4" role="alert">
                        {{ $errors->first('file') }}
                    </div>
                @endif

                @php
                    $importFailures = is_array(session('import_failures')) ? session('import_failures') : [];
                @endphp

                <!-- @if (count($importFailures) > 0)
                    <div class="alert alert-danger m-4" role="alert">
                        <h6 class="alert-heading fw-bold mb-3">{{ __('Validation Errors') }}</h6>
                        <p class="mb-2">{{ __('The following rows could not be imported. Please fix the spreadsheet and re-upload.') }}</p>
                        <ul class="mb-0 ps-4">
                            @foreach ($importFailures as $failure)
                                @foreach ($failure['errors'] ?? [] as $error)
                                    <li class="mb-1">{{ $error }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif -->

                @if (session('import_summary'))
                    @php($summary = session('import_summary'))
                    <div class="card-body pt-4 pb-0">
                        <h6 class="fw-bold mb-3">{{ __('Import Summary') }}</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('Processed') }}</p>
                                    <p class="mb-0 fs-4">{{ $summary['processed'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('Created') }}</p>
                                    <p class="mb-0 fs-4 text-success">{{ $summary['created'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('Updated') }}</p>
                                    <p class="mb-0 fs-4 text-primary">{{ $summary['updated'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('Skipped') }}</p>
                                    <p class="mb-0 fs-4 text-warning">{{ $summary['skipped'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if (count($importFailures) > 0)
                    <div class="card-body pt-0 pb-4">
                        <div class="alert alert-warning mt-4" role="alert">
                            {{ __('Some rows could not be imported. Review the errors below, fix the spreadsheet, and re-upload.') }}
                        </div>
                        <div class="table-responsive alert alert-danger">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <!-- <th>Field</th> -->
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($importFailures as $failure)
                                        <tr>
                                            <td>{{ $failure['row'] ?? '-' }}</td>
                                            <!-- <td>{{ $failure['attribute'] ?? '-' }}</td> -->
                                            <td>
                                                <ul class="mb-0 ps-3">
                                                    @foreach ($failure['errors'] ?? [] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <form action="{{ route('bulk-item-import-store') }}" method="POST" id="itemForm" enctype="multipart/form-data">
                    @csrf
                    <div class="card-header">
                        <h5 class="card-tile mb-0">{{ __('Item Basic Information') }}</h5>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="file">{{ __('Bulk Import File') }} {!! requiredField() !!}</label>
                                    <input type="file" class="form-control" placeholder="{{ __('Item Name') }}" name="file" id="file" accept=".xlsx,.xls,.csv" />
                                    <small class="text-muted d-block mt-2">{{ __('Accepted formats: .xlsx, .xls, .csv (max 50 MB)') }}</small>
                                </div>
                                <div class="my-5">
                                    <a href="{{ route('bulk-item-import-sample') }}" class="btn btn-outline-primary" download>
                                        <i class="ti tabler-download me-1"></i> {{ __('Download Sample') }}
                                    </a>
                                </div>
                                <div class="mb-5">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_duplicate_items" id="remove_duplicate_items" value="1" {{ old('remove_duplicate_items') ? 'checked' : '' }} />
                                        <label class="form-check-label" for="remove_duplicate_items">{{ __('Remove Duplicate Items') }}</label>
                                    </div>
                                    <small class="text-muted d-block mt-1 mb-3">{{ __('If checked, existing items will be soft-deleted before import so file rows replace/update by code.') }}</small>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="remove_all_items" id="remove_all_items" value="1" {{ old('remove_all_items') ? 'checked' : '' }} />
                                        <label class="form-check-label" for="remove_all_items">{{ __('Remove All Item') }}</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">{{ __('If checked, the items table will be emptied (all items for this company deleted) before import.') }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-3 p-4 bg-light">
                            <h6 class="mb-3">{{ __('Expected Spreadsheet Columns') }}</h6>
                            <p class="text-muted small mb-3">{{ __('Excel header names must match exactly (e.g. Item Type, Category Name, Unit Type). Code must be unique.') }}</p>
                            <div class="row g-4">
                                <div class="col-md-6 col-lg-4">
                                    <ul class="mb-0">
                                        <li><strong>Name</strong> (required)</li>
                                        <li><strong>Code</strong> (required, unique)</li>
                                        <li><strong>Item Type</strong> (required): General Product, IMEI Product, Serial Product, Medicine Product, Service Product</li>
                                        <li><strong>Category Name</strong> (required)</li>
                                        <li><strong>Unit Type</strong> (required): Single Unit, Double Unit</li>
                                    </ul>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <ul class="mb-0">
                                        <li><strong>Sale Unit</strong> (required if Single Unit)</li>
                                        <li><strong>Purchase Unit</strong>, <strong>Sale Unit</strong>, <strong>Conversion Rate</strong> (required if Double Unit)</li>
                                        <li><strong>Sale Price</strong> (required)</li>
                                        <li><strong>MRP Price</strong>, <strong>Purchase Price</strong>, <strong>Whole Sale Price</strong> (optional)</li>
                                        <li><strong>Expiry Date Maintain</strong> (required if Item Type = Medicine Product): Yes or No</li>
                                        <li class="text-muted">Optional: Supplier Name, Brand Name, Alternative Name, Loyalty Point, Alert Quantity</li>
                                    </ul>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <ul class="mb-0">
                                        <li class="text-muted">Optional: <strong>Warranty</strong> (number, e.g. 1, 2, 3), <strong>Warranty Date</strong> (one of: day, month, year), <strong>Guarantee</strong> (number, e.g. 1, 2, 3), <strong>Guarantee Date</strong> (one of: day, month, year), Generic Name</li>
                                        <li class="text-muted">Supplier Name / Brand Name — created if not exists (like Category).</li>
                                    </ul>
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

