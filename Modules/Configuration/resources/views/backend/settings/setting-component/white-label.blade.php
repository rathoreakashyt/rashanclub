<div class="tab-pane fade {{ request()->route('tab') == 'whitelabel_setting' ? 'active show' : '' }}" id="whitelabel_setting" role="tabpanel">
    <form id="whitelabel_setting_form">
        <div class="card mb-4">
            @php 
            $whiteLabelDetails = json_decode($company->white_label ?? '{}', true);
            @endphp
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Whitelabel Setting') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="site_name">{{ __('Site Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" placeholder="{{ __('Enter') }} {{ __('Site Name') }}" value="{{ $whiteLabelDetails['site_name'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="site_footer">{{ __('Site Footer') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="site_footer" name="site_footer" placeholder="{{ __('Enter') }} {{ __('Site Footer') }}" value="{{ $whiteLabelDetails['site_footer'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="site_title">{{ __('Site Title') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="site_title" name="site_title" placeholder="{{ __('Enter') }} {{ __('Site Title') }}" value="{{ $whiteLabelDetails['site_title'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="site_link">{{ __('Site Link') }} {!! requiredField() !!}</label>
                        <input type="url" class="form-control" id="site_link" name="site_link" placeholder="{{ __('Enter') }} {{ __('Site Link') }}" value="{{ $whiteLabelDetails['site_link'] ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="site_logo">{{ __('Site Logo') }} {!! requiredField() !!}</label>
                        <input type="file" class="form-control" id="site_logo_file" name="site_logo_file" accept="image/*">
                        <input type="hidden" name="site_logo" id="site_logo">
                        <input type="hidden" name="site_logo_hidden" id="site_logo_hidden" value="{{ $whiteLabelDetails['site_logo'] ?? '' }}">
                        <div class="mt-2">
                            <img id="site_logo_preview" src="{{ isset($whiteLabelDetails['site_logo']) ? asset('uploads/whitelabel/' . $whiteLabelDetails['site_logo']) : asset('uploads/dummy_images/default-picture.png') }}" alt="Site Logo" style="border:1px dashed #d1d0d4; padding: 10px; border-radius: 5px; max-width: 100px; display: block;">
                            <div class="d-flex gap-2 mt-2">
                                @if(isset($whiteLabelDetails['site_logo']))
                                    <button type="button" class="btn btn-sm btn-primary preview-site-logo d-flex gap-2 align-items-center">
                                        <i class="ti tabler-eye"></i>
                                        <span>{{ __('Preview') }}</span>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-danger remove-site-logo d-flex gap-2 align-items-center">
                                    <i class="ti tabler-trash"></i>
                                    <span>{{ __('Remove') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="site_favicon">{{ __('Site Favicon') }} {!! requiredField() !!}</label>
                        <input type="file" class="form-control" id="site_favicon_file" name="site_favicon_file" accept="image/*">
                        <input type="hidden" name="site_favicon" id="site_favicon">
                        <input type="hidden" name="site_favicon_hidden" id="site_favicon_hidden" value="{{ $whiteLabelDetails['site_favicon'] ?? '' }}">
                        <div class="mt-2">
                            <img id="site_favicon_preview" src="{{ isset($whiteLabelDetails['site_favicon']) ? asset('uploads/whitelabel/' . $whiteLabelDetails['site_favicon']) : asset('uploads/dummy_images/default-picture.png') }}" alt="Site Favicon" style="border:1px dashed #d1d0d4; padding: 10px; border-radius: 5px; max-width: 100px; display: block;">
                            <div class="d-flex gap-2 mt-2">
                                @if(isset($whiteLabelDetails['site_favicon']))
                                    <button type="button" class="btn btn-sm btn-primary preview-site-favicon d-flex gap-2 align-items-center">
                                        <i class="ti tabler-eye"></i>
                                        <span>{{ __('Preview') }}</span>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-danger remove-site-favicon d-flex gap-2 align-items-center">
                                    <i class="ti tabler-trash"></i>
                                    <span>{{ __('Remove') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button type="submit" class="btn btn-primary waves-effect waves-light whitelabel_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>