@extends('backend.backend_layout')
@section('page-title', isset($user) ? __('Edit') . ' ' . __('Employee') : __('Add') . ' ' . __('Employee'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/pages/cropper.min.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add/Edit User -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($user) ? __('Update') . ' ' . __('Employee') : __('Add') . ' ' . __('Employee') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' =>  __('Employee'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($user) ? __('Update') . ' ' . __('Employee') : __('Add') . ' ' . __('Employee'),
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
            <form id="userForm" action="{{ isset($user) ? route('user.update', $user->encrypted_id) : route('user.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if(isset($user))
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
                                    value="{{ old('name', isset($user) ? $user->name : '') }}" />
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="username_email_phone">{{ __('Username') }}, {{ __('Email') }} {{ __('or') }} {{ __('Phone') }}  {!! requiredField() !!}</label>
                                <input type="text" class="form-control @error('email') is-invalid @enderror" 
                                    placeholder="{{ __('Username') }}, {{ __('Email') }} {{ __('or') }} {{ __('Phone') }}" name="email" id="username_email_phone" 
                                    value="{{ old('email', isset($user) ? $user->email : '') }}" autocomplete="off" />
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="phone">{{ __('Phone') }} {!! requiredField() !!}</label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror" 
                                    placeholder="{{ __('Phone') }}" name="phone" id="phone" 
                                    value="{{ old('phone', isset($user) ? $user->phone : '') }}" />
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="designation">{{ __('Designation') }} {!! requiredField() !!}</label>
                                <select id="designation" name="role" class="select2 form-select @error('role') is-invalid @enderror" data-placeholder="{{ __('Select') }} {{ __('Designation') }}">
                                    <option value="">{{ __('Select') }} {{ __('Designation') }}</option>
                                    @foreach($roles as $id => $name)
                                        @php
                                            $userRoleId = isset($user) ? ($user->roles->first()?->id ?? '') : '';
                                        @endphp
                                        <option value="{{ $id }}" {{ (old('role', $userRoleId)) == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="salary">{{ __('Salary') }}</label>
                                <input type="text" class="form-control number-input @error('salary') is-invalid @enderror" 
                                    placeholder="{{ __('Salary') }}" name="salary" id="salary" 
                                    value="{{ old('salary', isset($user) ? $user->salary : '') }}" />
                                @error('salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <div class="d-flex justify-content-between">
                                   <label class="form-label" for="commission">{{ __('Commission') }}</label>
                                   <i class="icon-base ti tabler-info-circle icon-sm ms-2" 
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    title="{{ __('How much commission in percentage an employee will get per sale') }}"></i>
                                </div>
                                <input type="text" class="form-control number-input @error('commission') is-invalid @enderror" 
                                    placeholder="{{ __('Commission') }}" name="commission" id="commission" 
                                    value="{{ old('commission', isset($user) ? $user->commission : '') }}" />
                                @error('commission')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="discount_permission_code">{{ __('Discount Permission Code') }}</label>
                                <input type="text" class="form-control @error('discount_permission_code') is-invalid @enderror" 
                                    placeholder="{{ __('Discount Permission Code') }}" name="discount_permission_code" id="discount_permission_code" 
                                    value="{{ old('discount_permission_code', isset($user) ? $user->discount_permission_code : '') }}" />
                                @error('commission')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="discount_amt">{{ __('Discount(Flat/Percentage)') }}</label>
                                <input type="text" class="form-control number-input @error('discount_amt') is-invalid @enderror" 
                                    placeholder="{{ __('Discount(Flat/Percentage)') }}" name="discount_amt" id="discount_amt" 
                                    value="{{ old('discount_amt', isset($user) ? $user->discount_amt : '') }}" />
                                @error('commission')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="start_date">{{ __('Start Date') }}</label>
                                <input type="text" class="form-control datePicker @error('start_date') is-invalid @enderror" 
                                    placeholder="{{ __('Start Date') }}" name="start_date" id="start_date" 
                                    value="{{ old('start_date', isset($user) ? $user->start_date : '' ) }}" />
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="end_date">{{ __('End Date') }}</label>
                                <input type="text" class="form-control datePicker @error('end_date') is-invalid @enderror" 
                                    placeholder="{{ __('End Date') }}" name="end_date" id="end_date" 
                                    value="{{ old('end_date', isset($user) ? $user->end_date : '' ) }}" />
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="clear-fix"></div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="mb-5">
                                <label class="form-label" for="photo">{{ __('Photo') }}</label>
                                <input type="file" class="form-control @error('photo') is-invalid @enderror" 
                                    name="photo_file" id="photo_file" accept="image/*" />
                                <input type="hidden" name="photo" id="photo" />
                                <input type="hidden" name="old_photo" id="old_photo" value="{{ isset($user) && $user->photo ? asset('uploads/' . $user->photo) : '' }}" />
                                <div class="mt-2">
                                    <img id="imagePreview" class="border rounded" width="100" height="100" src="{{ isset($user) && $user->photo ? asset('uploads/' . $user->photo) : asset('uploads/dummy_images/default-picture.png') }}" alt="{{ __('User Photo') }}">
                                    <div class="d-flex gap-2 mt-2">
                                        @if(isset($user) && $user->photo)
                                            <button type="button" class="btn btn-sm btn-primary preview-image d-flex gap-2 align-items-center">
                                                <i class="ti tabler-eye"></i>
                                                <span>{{ __('Preview') }}</span>
                                            </button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-danger remove-image d-flex gap-2 align-items-center">
                                            <i class="ti tabler-trash"></i>
                                            <span>{{ __('Remove Photo') }}</span>
                                        </button>
                                    </div>
                                </div>
                                @error('photo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>


                        <div class="col-12">
                            <label class="form-label">{{ __('Will_Login') }}</label>
                            <div class="d-flex">
                                <div class="form-check me-3">
                                    <input id="will_yes" class="form-check-input" type="radio" name="will_login" value="Yes" 
                                        {{ old('will_login', isset($user) ? ($user->will_login == 'Yes' ? 'Yes' : 'No') : 'Yes') == 'Yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="will_yes">{{ __('Yes') }}</label>
                                </div>
                                <div class="form-check">
                                    <input id="will_no" class="form-check-input" type="radio" name="will_login" value="No"
                                        {{ old('will_login', isset($user) ? ($user->will_login == 'No' ? 'No' : 'Yes') : 'Yes') == 'No' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="will_no">{{ __('No') }}</label>
                                </div>  
                            </div>
                        </div>
                        <div class="row will_login_section {{ old('will_login', isset($user) ? $user->will_login : 'Yes') == 'No' ? 'd-none' : '' }}">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="password">{{ __('Password') }} {!! !isset($user) ? requiredField() : '' !!}</label>
                                    <input type="text" class="form-control @error('password') is-invalid @enderror" 
                                        placeholder="{{ __('Enter') }} {{ __('Password') }}" name="password" id="password" autocomplete="off" />
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="password_confirmation">{{ __('Confirm') }} {{ __('Password') }} {!! !isset($user) ? requiredField() : '' !!}</label>
                                    <input type="text" class="form-control @error('password_confirmation') is-invalid @enderror" 
                                        placeholder="{{ __('Confirm') }} {{ __('Password') }}" name="password_confirmation" id="password_confirmation" autocomplete="off" />
                                    @error('password_confirmation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 select2-primary">
                                <div class="mb-5">
                                    <label class="form-label" for="outlet">{{ __('Outlet') }} {!! requiredField() !!}</label>
                                    <select id="outlet" name="outlets[]" class="select2 form-select @error('outlets') is-invalid @enderror" 
                                        multiple data-placeholder="{{ __('Select') }} {{ __('Outlet') }}">
                                        @php
                                            $selectedOutlets = [];
                                            if (isset($user) && $user->outlet_id) {
                                                $selectedOutlets = explode(',', $user->outlet_id);
                                            }
                                            $oldOutlets = old('outlets', $selectedOutlets);
                                        @endphp
                                        @foreach($outlets as $id => $outlet)
                                            <option value="{{ $id }}" {{ in_array($id, $oldOutlets) ? 'selected' : '' }}>{{ $outlet }}</option>
                                        @endforeach
                                    </select>
                                    @error('outlets')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light me-4">
                            {!! submitIconWithText(isset($user) ? $user : '') !!}
                        </button>
                        <a href="{{ route('user.index') }}" type="button" class="btn btn-primary waves-effect waves-light">
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
<script src="{{ asset('backend_assets/vendor/js/cropper.js') }}"></script>
<script>
    $(document).ready(function () {
        let cropper;
        let imagePreview = document.getElementById('imagePreview');
        let photoFileInput = document.getElementById('photo_file');
        let photoHiddenInput = document.getElementById('photo');
        let oldPhotoInput = document.getElementById('old_photo');
        let cropperModal = null;
        let cropperInstance = null;
        let originalImageSrc = null;
        
        // Store original image if in edit mode
        @if(isset($user) && $user->photo)
            originalImageSrc = '{{ asset('uploads/' . $user->photo) }}';
        @endif

        $(document).on('change', 'input[name="will_login"]', function(){
            let value = $(this).val();
            if(value == 'Yes'){
                $('.will_login_section').show();
                $('#outlet').prop('required', true);
            }else{
                $('.will_login_section').hide();
                $('#outlet').prop('required', false);
            }
        });

        let willLoginValue = $('input[name="will_login"]:checked').val();
        if(willLoginValue == 'No'){
            $('.will_login_section').hide();
        }

        // Image file change handler
        $(photoFileInput).on('change', function(e) {
            if (this.files && this.files[0]) {
                let file = this.files[0];
                if (!file.type.match('image.*')) {
                    alert('Please select an image file');
                    $(this).val('');
                    return;
                }
                // Validate file size (max 5MB)
                if (file.size > 1 * 1024 * 1024) {
                    showErrorNotification('Image size should be less than 1MB');
                    $(this).val('');
                    return;
                }

                let reader = new FileReader();
                reader.onload = function(e) {
                    showCropperModal(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        $(document).on('click', '.remove-image', function() {
            if (originalImageSrc) {
                $(photoFileInput).val('');
                $(photoHiddenInput).val('');
                $(imagePreview).attr('src', originalImageSrc).show();
            } else {
                $(photoFileInput).val('');
                $(photoHiddenInput).val('');
                $(imagePreview).attr('src', '{{ asset('uploads/dummy_images/default-picture.png') }}');
                $('.remove-image').hide();
            }
        });

        // Preview image in modal
        $(document).on('click', '.preview-image', function() {
            let imageSrc = $(imagePreview).attr('src');
            let defaultImageSrc = '{{ asset('uploads/dummy_images/default-picture.png') }}';
            
            // Don't show preview for default image
            if (!imageSrc || imageSrc === defaultImageSrc) {
                return;
            }

            // Create preview modal HTML
            let previewModalHtml = `
                <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ __('Image Preview') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img src="${imageSrc}" class="img-fluid" alt="{{ __('User Photo') }}" style="max-height: 70vh;">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            $('#imagePreviewModal').remove();
            
            // Append and show modal
            $('body').append(previewModalHtml);
            let previewModalElement = document.getElementById('imagePreviewModal');
            let previewModal = new bootstrap.Modal(previewModalElement);
            
            // Clean up on modal close
            $(previewModalElement).on('hidden.bs.modal', function () {
                $(this).remove();
            });
            
            previewModal.show();
        });

        // Show cropper modal
        function showCropperModal(imageSrc) {
            $('#cropperModal').remove();
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
            if (cropperModal) {
                cropperModal.dispose();
                cropperModal = null;
            }

            // Create modal HTML
            let modalHtml = `
                <div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ __('Crop Image') }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="image-cropper-container" style="min-height: 400px;">
                                    <img id="cropperImage" src="${imageSrc}" style="max-width: 100%; display: block;">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="cropImageBtn">{{ __('Crop & Save') }}</button>
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            let modalElement = document.getElementById('cropperModal');
            cropperModal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
            $(modalElement).on('shown.bs.modal', function () {
                let cropperImage = document.getElementById('cropperImage');
                if (cropperImage && typeof Cropper !== 'undefined') {
                    cropperInstance = new Cropper(cropperImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 0.8,
                        responsive: true,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        ready: function() {
                            console.log('Cropper initialized successfully');
                        }
                    });
                } else {
                    console.error('Cropper image element not found or Cropper library not loaded');
                }
            });

            $(modalElement).on('click', '#cropImageBtn', function() {
                if (cropperInstance) {
                    let canvas = cropperInstance.getCroppedCanvas({
                        width: 400,
                        height: 400,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high',
                    });

                    if (canvas) {
                        let croppedImage = canvas.toDataURL('image/jpeg', 0.9);
                        $(imagePreview).attr('src', croppedImage).show();
                        $(photoHiddenInput).val(croppedImage);
                        $('.remove-image').show();
                        @if(isset($user))
                            $('.preview-image').show();
                        @endif
                        cropperModal.hide();
                    }
                }
            });
            $(modalElement).on('hidden.bs.modal', function () {
                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }
                $(this).remove();
                cropperModal = null;
            });
            cropperModal.show();
        }
        function checkAndShowRemoveButton() {
            let previewSrc = $(imagePreview).attr('src');
            let defaultImageSrc = '{{ asset('uploads/dummy_images/default-picture.png') }}';
            if ($(imagePreview).is(':visible') && previewSrc && previewSrc !== defaultImageSrc) {
                $('.remove-image').show();
                // Show preview button only if image is not default and we're in edit mode
                @if(isset($user))
                    $('.preview-image').show();
                @endif
            } else {
                $('.remove-image').hide();
                $('.preview-image').hide();
            }
        }
        checkAndShowRemoveButton();
    });
</script>
@endpush

