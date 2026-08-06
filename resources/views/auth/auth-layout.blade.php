
<!doctype html>
<html
  lang="en"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="{{ asset('backend_assets/') }}/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>@if(defined('LP') && strtoupper((string) LP) === 'BD')@yield('page_title')@else{{ getWhiteLabel('site_name') }} | @yield('page_title')@endif</title>
    <meta name="description" content="" />
    @include('backend.components.pwa_meta')
    <!-- Favicon -->
    <link rel="icon" type="{{ lpFaviconLogoType() }}" href="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (getWhiteLabel('site_favicon') ?? 'initial-fav.ico'))) !!}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/fonts/iconify-icons.css') }}" />
    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/pickr/pickr-themes.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('backend_assets/css/demo.css') }}" />
    <!-- Select 2 -->
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/select2/select2.css') }}" />
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <!-- endbuild -->
    <!-- Vendor -->
    <!-- <link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/@form-validation/form-validation.css') }}" /> -->
    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/pages/page-auth.css') }}" />
    <!-- Helpers -->
    <script src="{{ asset('backend_assets/vendor/js/helpers.js') }}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="{{ asset('backend_assets/vendor/js/template-customizer.js') }}"></script>
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('backend_assets/js/config.js') }}"></script>
  </head>

  <body class="overflow-x-hidden" @if(defined('LP') && strtoupper((string) LP) === 'BD') data-lp="BD" @endif>
    <!-- Content -->

    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6">
          @yield('auth_content')
        </div>
      </div>
    </div>

    <!-- / Content -->
    <!-- Core JS -->
    <!-- build:js assets/vendor/js/theme.js -->
    <script src="{{ asset('backend_assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/@algolia/autocomplete-js.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/pickr/pickr.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/js/menu.js') }}"></script>
    <!-- endbuild -->
    <!-- Select 2 -->
    <script src="{{ asset('backend_assets/vendor/libs/select2/select2.js') }}"></script>
    <!-- Vendors JS -->
    <!-- <script src="{{ asset('backend_assets/vendor/libs/@form-validation/popular.js') }}"></script> -->
    <script src="{{ asset('backend_assets/vendor/libs/@form-validation/bootstrap5.js') }}"></script>
    <script src="{{ asset('backend_assets/vendor/libs/@form-validation/auto-focus.js') }}"></script>
    <!-- Main JS -->
    <script src="{{ asset('backend_assets/js/main.js') }}"></script>
    <!-- Page JS -->
    <!-- <script src="{{ asset('backend_assets/js/pages-auth.js') }}"></script> -->
    <script>
      $(document).ready(function() {
        $('.select2').select2();
      });
    </script>
    <script src="{{ asset('backend_assets/js/custom_js/backend_brand.js') }}"></script>
    @include('backend.components.pwa_scripts')
  </body>
</html>
