<div class="tab-pane fade {{ request()->route('tab') == 'pwa_setting' ? 'active show' : '' }}" id="pwa_setting" role="tabpanel">
    <form id="pwa_setting_form">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('PWA Settings') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="pwa_app_name">{{ __('App Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="pwa_app_name" name="app_name" placeholder="{{ __('Enter') }} {{ __('App Name') }}" value="{{ $pwaSettings->app_name ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="pwa_short_name">{{ __('Short Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="pwa_short_name" name="short_name" placeholder="{{ __('Enter') }} {{ __('Short Name') }}" value="{{ $pwaSettings->short_name ?? '' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="pwa_theme_color">{{ __('Theme Color') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="pwa_theme_color" name="theme_color" placeholder="#7367f0" value="{{ $pwaSettings->theme_color ?? '#7367f0' }}">
                    </div>

                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1" for="pwa_background_color">{{ __('Background Color') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="pwa_background_color" name="background_color" placeholder="#ffffff" value="{{ $pwaSettings->background_color ?? '#ffffff' }}">
                    </div>

                    <div class="col-12 validate_wrapper">
                        <label class="form-label mb-1" for="pwa_start_url">{{ __('Start URL') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="pwa_start_url" name="start_url" placeholder="http://localhost/ratila-pos/" value="{{ $pwaSettings->start_url ?? url('/') }}">
                        <small class="text-muted italic">{{ __('eg: https://retail-pos.com') }}</small>
                    </div>

                    <div class="col-12 validate_wrapper">
                        <label class="form-label mb-1" for="pwa_logo_file">{{ __('Logo') }} ({{ __('Minimum') }} 512x512)</label>
                        <input type="file" class="form-control" id="pwa_logo_file" name="logo_file" accept="image/*">
                        <div class="mt-2">
                            @php
                                $logoPath = $pwaSettings->logo ?? null;
                                $companyId = session('company.company_id', 1);
                                $iconPath = public_path('pwa/icons/' . $companyId . '/icon-512x512.png');
                                $logoUrl = ($logoPath && file_exists($iconPath)) ? asset('pwa/icons/' . $companyId . '/icon-512x512.png') : asset('uploads/dummy_images/default-picture.png');
                            @endphp
                            <img id="pwa_logo_preview" src="{{ $logoUrl }}" alt="PWA Logo" style="border:1px dashed #d1d0d4; padding: 10px; border-radius: 5px; max-width: 100px; display: block;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button type="submit" class="btn btn-primary waves-effect waves-light pwa_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>
