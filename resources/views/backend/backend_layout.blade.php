<!doctype html>
<html
  lang="{{ app()->getLocale() }}"
  class="layout-navbar-fixed layout-menu-fixed layout-compact"
  dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
  data-skin="default"
  data-assets-path="{{ asset('backend_assets/') }}/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Rashan Ki Dukan - Digital Akash | @yield('page-title')</title>
    <meta name="description" content="" />
    @include('backend.components.pwa_meta')
    <!-- Favicon -->
    <link rel="icon" type="{{ lpFaviconLogoType() }}" href="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (getWhiteLabel('site_favicon') ?? 'initial-fav.ico'))) !!}" />
    <!-- Header CSS Part -->
    @include('backend.components.header_css_part')
    <!-- Page-css -->
    @stack('page-css')
  </head>

  <body @if(defined('LP') && strtoupper((string) LP) === 'BD') data-lp="BD" @endif>
    <!-- Include Global Usaged Data -->
    @include('backend.components.global_used_data')
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->
        @include('backend.components.sidebar')
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->
          @include('backend.components.header')
          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->
            @yield('page-content')
            <!-- / Content -->

            <!-- Footer -->
            @include('backend.components.footer')
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>

      <!-- Drag Target Area To SlideIn Menu On Small Screens -->
      <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Main JS Part -->
    @include('backend.components.main_js_part')
    <!-- Main JS -->
    @stack('page-js')
    <script src="{{ asset('backend_assets/js/main.js') }}"></script>
    <script src="{{ asset('backend_assets/js/custom_js/helper_main.js') }}"></script>
    <script src="{{ asset('backend_assets/js/custom_js/register.js') }}"></script>
    <script src="{{ asset('backend_assets/js/custom_js/backend_brand.js') }}"></script>
    @include('backend.components.pwa_scripts')
  </body>
</html>
