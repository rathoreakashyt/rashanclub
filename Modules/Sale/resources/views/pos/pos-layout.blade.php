<!doctype html>
<html
  lang="en"
  class="layout-navbar-fixed layout-menu-fixed layout-compact"
  dir="ltr"
  data-skin="default"
  data-assets-path="/../public/backend_assets/"
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
    <link rel="icon" type="{{ lpFaviconLogoType() }}" href="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (session('white_label.site_favicon') ?? 'initial-fav.ico'))) !!}" />
    <!-- Header CSS Part -->
    @include('backend.components.pos_header_css_part')
    @stack('page-css')
  </head>

  <body style="overflow: hidden; margin: 0; padding: 0; height: 100vh;">
    <!-- Include Global Usaged Data -->
    @include('backend.components.global_used_data')
    <!-- POS Layout - Full Screen without Sidebar -->
    <div style="height: 100vh; width: 100vw; overflow: hidden; position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
      <!-- Content -->
      @yield('page-content')
      <!-- / Content -->
    </div>

    <!-- POS Search Autocomplete Container -->
    <div id="pos-autocomplete"></div>

    <!-- POS  Modals -->
    @include('sale::pos.component.customer')
    @include('sale::pos.component.sales')
    @include('sale::pos.component.sale-returns')
    @include('sale::pos.component.item-info')
    @include('sale::pos.component.tax-breakdown')
    @include('sale::pos.component.payment')
    @include('sale::pos.component.hold')
    @include('sale::pos.component.calculator')
    @include('sale::pos.component.register-summary')
    <!-- POS Modals End -->

    <!-- Main JS Part -->
    @include('backend.components.pos_main_js_part')
    <!-- Main JS -->
    @stack('page-js')
    <script src="{{ asset('backend_assets/js/main.js') }}"></script>
    <script src="{{ asset('backend_assets/js/custom_js/helper_main.js') }}"></script>
    <script src="{{ asset('backend_assets/js/custom_js/register.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_indexeddb.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_invoice_offline.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_main.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_cart.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_products.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_payment.js') }}"></script>
    <script src="{{ asset('pos_assets/js/sales.js') }}"></script>
    <script src="{{ asset('pos_assets/js/sale_returns.js') }}"></script>
    <script src="{{ asset('pos_assets/js/hold_sale.js') }}"></script>
    <script src="{{ asset('pos_assets/js/pos_utilities.js') }}"></script>
    <script src="{{ asset('backend_assets/js/pages_js/add_sale_return.js') }}"></script>
    <!-- @include('backend.components.pwa_scripts') -->
  </body>
</html>
