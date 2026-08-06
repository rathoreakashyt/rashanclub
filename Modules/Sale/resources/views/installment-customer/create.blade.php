@extends('backend.backend_layout')
@section('page-title', isset($customer) ? __('Update') . ' ' . __('Installment Customer') : __('Add') . ' ' . __('Installment Customer') )
@push('page-css')
<style>
    .img-wrap img {
        border: 1px dashed gray;
        padding: 5px;
        border-radius: 5px;
    }
    .img-wrap img.preview-image {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .img-wrap img.preview-image:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    #imagePreviewModal .modal-body {
        text-align: center;
        padding: 20px;
    }
    #imagePreviewModal .modal-body img {
        max-width: 100%;
        max-height: 70vh;
        border-radius: 8px;
    }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($customer) ? __('Update') . ' ' . __('Installment Customer') : __('Add') . ' ' . __('Installment Customer') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Installment Customer'),
                    'link' => '#'
                ],
                [
                    'label' => isset($customer) ? __('Update') . ' ' . __('Installment Customer') : __('Add') . ' ' . __('Installment Customer'),
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
            <form action="{{ isset($customer) ? route('installment-customer.update', encrypt($customer->id)) : route('installment-customer.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @if(isset($customer))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($customer) ? $customer->name : '') }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="phone">{{ __('Phone') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                        placeholder="{{ __('Phone') }}" name="phone" id="phone" 
                                        value="{{ old('phone', isset($customer) ? $customer->phone : '') }}" />
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="email">{{ __('Email') }}</label>
                                    <input type="text" class="form-control @error('email') is-invalid @enderror" 
                                        placeholder="{{ __('Email') }}" name="email" id="email" 
                                        value="{{ old('email', isset($customer) ? $customer->email : '') }}" />
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="form-label" for="opening_balance">{{ __('opening_balance') }}</label>
                                <div class="d-flex jsutify-content-between w-100">
                                    <div class="mb-5 me-1 flex-grow-1 w-50">
                                        <input type="text" class="form-control @error('opening_balance') is-invalid @enderror" 
                                            placeholder="{{ __('opening_balance') }}" name="opening_balance" id="opening_balance" 
                                            value="{{ old('opening_balance', isset($customer) ? $customer->opening_balance : '') }}" />
                                        @error('opening_balance')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="mb-5 flex-grow-1 w-50">
                                        <select class="w-100 form-select select2 form-select @error('opening_balance_type') is-invalid @enderror" 
                                            name="opening_balance_type" id="opening_balance_type" data-placeholder="{{ __('opening_balance_type') }}">
                                            <option value="">{{ __('opening_balance_type') }}</option>
                                            <option value="Debit" {{ old('opening_balance_type', $customer->opening_balance_type ?? '') == 'Debit' ? 'selected' : '' }}>
                                                {{ __('Debit') }}
                                            </option>
                                            <option value="Credit" {{ old('opening_balance_type', $customer->opening_balance_type ?? '') == 'Credit' ? 'selected' : '' }}>
                                                {{ __('Credit') }}
                                            </option>
                                        </select>
                                        @error('opening_balance_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 img-wrap">
                                    <label class="form-label" for="customer_nid">{{ __('Customer NID') }} {!! requiredField() !!}</label>
                                    <input type="file" class="form-control @error('customer_nid') is-invalid @enderror"
                                        name="customer_nid" id="customer_nid" accept="image/*" />
                                    @if(isset($customer) && $customer->customer_nid)
                                        <img src="{{ asset(''.$customer->customer_nid) }}" alt="Customer NID" width="100" 
                                            class="mt-2 preview-image" onclick="openImageModal(this.src, 'Customer NID')" 
                                            title="{{ __('Click to view') }}" />
                                    @endif
                                    @error('customer_nid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 img-wrap">
                                    <label class="form-label" for="photo">{{ __('Customer Photo') }} {!! requiredField() !!}</label>
                                    <input type="file" class="form-control @error('photo') is-invalid @enderror"
                                        name="photo" id="photo" accept="image/*" />
                                    @if(isset($customer) && $customer->photo)
                                        <img src="{{ asset(''.$customer->photo) }}" alt="Customer Photo" width="100" 
                                            class="mt-2 preview-image" onclick="openImageModal(this.src, 'Customer Photo')" 
                                            title="{{ __('Click to view') }}" />
                                    @endif
                                    @error('photo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="address">{{ __('Address') }} {!! requiredField() !!}</label>
                                    <textarea type="text" class="form-control @error('address') is-invalid @enderror"
                                        placeholder="{{ __('Address') }}" name="address" id="address"
                                        >{{ old('address', isset($customer) ? $customer->address : '') }}</textarea>
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="permanent_address">{{ __('Permanent Address') }} {!! requiredField() !!}</label>
                                    <textarea type="text" class="form-control @error('permanent_address') is-invalid @enderror"
                                        placeholder="{{ __('Permanent Address') }}" name="permanent_address" id="permanent_address"
                                        >{{ old('permanent_address', isset($customer) ? $customer->permanent_address : '') }}</textarea>
                                    @error('permanent_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="work_address">{{ __('Work Address') }} {!! requiredField() !!}</label>
                                    <textarea type="text" class="form-control @error('work_address') is-invalid @enderror"
                                        placeholder="{{ __('Work Address') }}" name="work_address" id="work_address"
                                        >{{ old('work_address', isset($customer) ? $customer->work_address : '') }}</textarea>
                                    @error('work_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="clear-fix"></div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date_of_birth">{{ __('date_of_birth') }}</label>
                                    <input type="text" class="form-control datePicker @error('date_of_birth') is-invalid @enderror" 
                                        placeholder="{{ __('date_of_birth') }}" name="date_of_birth" id="date_of_birth" 
                                        value="{{ old('date_of_birth', isset($customer) ? $customer->date_of_birth : '') }}" readonly />
                                    @error('date_of_birth')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="date_of_anniversary">{{ __('date_of_anniversary') }}</label>
                                    <input type="text" class="form-control datePicker @error('date_of_anniversary') is-invalid @enderror"
                                        placeholder="{{ __('date_of_anniversary') }}" name="date_of_anniversary" id="date_of_anniversary"
                                        value="{{ old('date_of_anniversary', isset($customer) ? $customer->date_of_anniversary : '') }}" readonly />
                                    @error('date_of_anniversary')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h5 class="mt-4">{{ __('Guarantor Information') }}</h5>
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="g_name">{{ __('Guarantor Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('g_name') is-invalid @enderror"
                                        placeholder="{{ __('Guarantor Name') }}" name="g_name" id="g_name"
                                        value="{{ old('g_name', isset($customer) ? $customer->g_name : '') }}" />
                                    @error('g_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="g_mobile">{{ __('Guarantor Mobile') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('g_mobile') is-invalid @enderror"
                                        placeholder="{{ __('Guarantor Mobile') }}" name="g_mobile" id="g_mobile"
                                        value="{{ old('g_mobile', isset($customer) ? $customer->g_mobile : '') }}" />
                                    @error('g_mobile')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="g_pre_address">{{ __('Guarantor Present Address') }} {!! requiredField() !!}</label>
                                    <textarea type="text" class="form-control @error('g_pre_address') is-invalid @enderror"
                                        placeholder="{{ __('Guarantor Present Address') }}" name="g_pre_address" id="g_pre_address"
                                        >{{ old('g_pre_address', isset($customer) ? $customer->g_pre_address : '') }}</textarea>
                                    @error('g_pre_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="g_work_address">{{ __('Guarantor Work Address') }} {!! requiredField() !!}</label>
                                    <textarea type="text" class="form-control @error('g_work_address') is-invalid @enderror"
                                        placeholder="{{ __('Guarantor Work Address') }}" name="g_work_address" id="g_work_address"
                                        >{{ old('g_work_address', isset($customer) ? $customer->g_work_address : '') }}</textarea>
                                    @error('g_work_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 img-wrap">
                                    <label class="form-label" for="g_nid">{{ __('Guarantor NID') }}</label>
                                    <input type="file" class="form-control @error('g_nid') is-invalid @enderror"
                                        name="g_nid" id="g_nid" accept="image/*" />
                                    @if(isset($customer) && $customer->g_nid)
                                        <img src="{{ asset(''.$customer->g_nid) }}" alt="Guarantor NID" width="100" 
                                            class="mt-2 preview-image" onclick="openImageModal(this.src, 'Guarantor NID')" 
                                            title="{{ __('Click to view') }}" />
                                    @endif
                                    @error('g_nid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 img-wrap">
                                    <label class="form-label" for="g_photo">{{ __('Guarantor Photo') }}</label>
                                    <input type="file" class="form-control @error('g_photo') is-invalid @enderror"
                                        name="g_photo" id="g_photo" accept="image/*" />
                                    @if(isset($customer) && $customer->g_photo)
                                        <img src="{{ asset(''.$customer->g_photo) }}" alt="Guarantor Photo" width="100" 
                                            class="mt-2 preview-image" onclick="openImageModal(this.src, 'Guarantor Photo')" 
                                            title="{{ __('Click to view') }}" />
                                    @endif
                                    @error('g_photo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($customer) ? $customer : '') !!}
                            </button>
                            <a href="{{ route('installment-customer.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePreviewModalLabel">{{ __('Image Preview') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img src="" alt="Preview" id="modalPreviewImage" />
            </div>
            <div class="modal-footer">
                <a href="" id="downloadImageBtn" class="btn btn-success" download>
                    <i class="ti tabler-download me-1"></i> {{ __('Download') }}
                </a>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="ti tabler-x me-1"></i> {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('page-js')
<script>
    /**
     * Opens image preview modal with the given image source
     * @param {string} imageSrc - The source URL of the image
     * @param {string} title - The title to display in the modal header
     */
    function openImageModal(imageSrc, title) {
        // Set the image source in the modal
        document.getElementById('modalPreviewImage').src = imageSrc;
        
        // Set the modal title
        document.getElementById('imagePreviewModalLabel').textContent = title;
        
        // Set the download link
        document.getElementById('downloadImageBtn').href = imageSrc;
        
        // Extract filename from the image path for download
        const filename = imageSrc.split('/').pop();
        document.getElementById('downloadImageBtn').setAttribute('download', filename);
        
        // Show the modal
        var imageModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        imageModal.show();
    }
</script>
<script src="{{ asset('backend_assets/js/pages_js/installment_customer_form.js') }}"></script>
@endpush
