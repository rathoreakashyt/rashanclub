@extends('backend.backend_layout')
@section('page-title', __('Update Profile'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/pages/cropper.min.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Update Profile -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Update Profile') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Profile'),
                    'link' => '#'
                ],
                [
                    'label' => __('Update Profile'),
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
            <!-- Profile Part -->
            <div class="card mb-4" id="profilePart">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Profile Information') }}</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" action="{{ route('user.update-profile') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="update_type" value="profile" />
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', $user->name) }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="email">{{ __('Email') }} {!! requiredField() !!}</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                        placeholder="{{ __('Email') }}" name="email" id="email" 
                                        value="{{ old('email', $user->email) }}" autocomplete="off" />
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
                                        value="{{ old('phone', $user->phone) }}" />
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="photo">{{ __('Photo') }}</label>
                                    <input type="file" class="form-control @error('photo') is-invalid @enderror" 
                                        name="photo_file" id="photo_file" accept="image/*" />
                                    <input type="hidden" name="photo" id="photo" />
                                    <input type="hidden" name="old_photo" id="old_photo" value="{{ $user->photo ? asset('uploads/' . $user->photo) : '' }}" />
                                    <div class="mt-2">
                                        <img id="imagePreview" class="border rounded" width="100" height="100" src="{{ $user->photo ? asset('uploads/' . $user->photo) : asset('uploads/dummy_images/default-picture.png') }}" alt="{{ __('User Photo') }}">
                                        <div class="d-flex gap-2 mt-2">
                                            @if($user->photo)
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
                        </div>
                        <div class="d-flex">
                            <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light">
                                <i class="ti tabler-device-floppy me-2"></i>
                                {{ __('Update Profile') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Part -->
            <div class="card mb-4" id="passwordPart">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Change Password') }}</h5>
                </div>
                <div class="card-body">
                    <form id="passwordForm" action="{{ route('user.update-profile') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="update_type" value="password" />
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="current_password">{{ __('Current Password') }} {!! requiredField() !!}</label>
                                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" 
                                        placeholder="{{ __('Enter') }} {{ __('Current Password') }}" name="current_password" id="current_password" autocomplete="off" />
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="new_password">{{ __('New Password') }} {!! requiredField() !!}</label>
                                    <input type="password" class="form-control @error('new_password') is-invalid @enderror" 
                                        placeholder="{{ __('Enter') }} {{ __('New Password') }}" name="new_password" id="new_password" autocomplete="off" />
                                    @error('new_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="new_password_confirmation">{{ __('Confirm') }} {{ __('New Password') }} {!! requiredField() !!}</label>
                                    <input type="password" class="form-control @error('new_password_confirmation') is-invalid @enderror" 
                                        placeholder="{{ __('Confirm') }} {{ __('New Password') }}" name="new_password_confirmation" id="new_password_confirmation" autocomplete="off" />
                                    @error('new_password_confirmation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="d-flex">
                            <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light">
                                <i class="ti tabler-key me-2"></i>
                                {{ __('Change Password') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Part -->
            <div class="card mb-4" id="securityPart">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Security Settings') }}</h5>
                </div>
                <div class="card-body">
                    <form id="securityForm" action="{{ route('user.update-profile') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="update_type" value="security" />
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="security_question">{{ __('Security Question') }} {!! requiredField() !!}</label>
                                    <select class="form-control select2 @error('security_question') is-invalid @enderror" id="security_question" name="security_question">
                                        <option value="">{{ __('Select a security question') }}</option>
                                        <option value="What is your mother's maiden name?" {{ old('security_question', $user->question) == "What is your mother's maiden name?" ? 'selected' : '' }}>{{ __('What is your mother\'s maiden name?') }}</option>
                                        <option value="What is the name of your first pet?" {{ old('security_question', $user->question) == "What is the name of your first pet?" ? 'selected' : '' }}>{{ __('What is the name of your first pet?') }}</option>
                                        <option value="What is the name of the town you were born?" {{ old('security_question', $user->question) == "What is the name of the town you were born?" ? 'selected' : '' }}>{{ __('What is the name of the town you were born?') }}</option>
                                        <option value="What primary school did you attend?" {{ old('security_question', $user->question) == "What primary school did you attend?" ? 'selected' : '' }}>{{ __('What primary school did you attend?') }}</option>
                                        <option value="What Is your favorite book?" {{ old('security_question', $user->question) == "What Is your favorite book?" ? 'selected' : '' }}>{{ __('What Is your favorite book?') }}</option>
                                        <option value="What was the first company that you worked for?" {{ old('security_question', $user->question) == "What was the first company that you worked for?" ? 'selected' : '' }}>{{ __('What was the first company that you worked for?') }}</option>
                                        <option value="What is your favorite food?" {{ old('security_question', $user->question) == "What is your favorite food?" ? 'selected' : '' }}>{{ __('What is your favorite food?') }}</option>
                                        <option value="Where did you meet your spouse?" {{ old('security_question', $user->question) == "Where did you meet your spouse?" ? 'selected' : '' }}>{{ __('Where did you meet your spouse?') }}</option>
                                        <option value="Where is your favorite place to vacation?" {{ old('security_question', $user->question) == "Where is your favorite place to vacation?" ? 'selected' : '' }}>{{ __('Where is your favorite place to vacation?') }}</option>
                                        <option value="What is the name of the road you grew up on?" {{ old('security_question', $user->question) == "What is the name of the road you grew up on?" ? 'selected' : '' }}>{{ __('What is the name of the road you grew up on?') }}</option>
                                    </select>
                                    @error('security_question')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="security_answer">{{ __('Security Answer') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('security_answer') is-invalid @enderror" 
                                        placeholder="{{ __('Security Answer') }}" name="security_answer" id="security_answer" 
                                        value="{{ old('security_answer', $user->answer) }}" autocomplete="off" />
                                    @error('security_answer')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="d-flex">
                            <button type="submit" value="submit" name="submit" class="btn btn-primary waves-effect waves-light">
                                <i class="ti tabler-shield-check me-2"></i>
                                {{ __('Update Security Settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
        
        // Store original image
        @if($user->photo)
            originalImageSrc = '{{ asset('uploads/' . $user->photo) }}';
        @endif

        // Image file change handler
        $(photoFileInput).on('change', function(e) {
            if (this.files && this.files[0]) {
                let file = this.files[0];
                if (!file.type.match('image.*')) {
                    alert('Please select an image file');
                    $(this).val('');
                    return;
                }
                // Validate file size (max 1MB)
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
                        $('.preview-image').show();
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
                @if($user->photo)
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

