@php
$companyId = session('company.company_id', 1);
$activeFeatures = \App\Models\FeatureActivation::where('company_id', $companyId)->pluck('is_active', 'feature_key')->toArray();
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical menu">
  <div class="app-brand demo">
    <a href="{{ url('/') }}" class="app-brand-link">
      <span class="demo menu-text fw-bold site-logo-sidebar">
        <img src="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (session('white_label.site_logo') ?? 'initial-logo.png'))) !!}" alt="Site-Logo">
      </span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
      <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
      <i class="icon-base ti tabler-x d-block d-xl-none"></i>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <!-- Dashboards -->
    <li class="menu-item {{ request()->is('user-home') ? 'active' : '' }}">
      <a href="{{ url('/user-home') }}" class="menu-link">
        <i class="menu-icon icon-base ti tabler-home"></i>
        <div>{{ __('Home') }}</div>
      </a>
    </li>
    @can('dashboard-dashboard')
    <li class="menu-item {{ request()->is('dashboard') ? 'active' : '' }}">
      <a href="{{ url('/dashboard') }}" class="menu-link">
        <i class="menu-icon icon-base ti tabler-gauge"></i>
        <div>{{ __('Dashboard') }}</div>
      </a>
    </li>
    @endcan
    @if($activeFeatures['booking'] ?? true)
    @if(Auth::user()->can('booking-list'))
    <li class="menu-item {{ request()->is('booking') ? 'active' : '' }}">
      <a href="{{ url('/booking') }}" class="menu-link">
        <i class="menu-icon icon-base ti tabler-calendar"></i>
        <div>{{ __('Booking') }}</div>
      </a>
    </li>
    @endif
    @endif
    @if(Auth::user()->can('outlet-list') || Auth::user()->can('outlet-create') || Auth::user()->can('outlet-edit') || Auth::user()->can('outlet-destroy'))
    @if($activeFeatures['outlet'] ?? true)
    <li class="menu-item {{ request()->is('outlet*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-layout-grid"></i>
        <div>{{ __('Outlet') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['outlet_add'] ?? true)
        @can('outlet-create')
        <li class="menu-item {{ request()->is('outlet/create') || request()->is('outlet/*/edit') ? 'active' : '' }}">
          <a href="{{ route('outlet.create') }}" class="menu-link">
            <div>{{ __('Add Outlet') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['outlet_list'] ?? true)
        @can('outlet-list')
        <li class="menu-item {{ request()->is('outlet') ? 'active' : '' }}">
          <a href="{{ route('outlet.index') }}" class="menu-link">
            <div>{{ __('List Outlet') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    @if(Auth::user()->can('item-list') || Auth::user()->can('item-create') || Auth::user()->can('item-import') || Auth::user()->can('category-list') || Auth::user()->can('category-create') || Auth::user()->can('brand-list') || Auth::user()->can('brand-create') || Auth::user()->can('unit-list') || Auth::user()->can('unit-create') || Auth::user()->can('rack-list') || Auth::user()->can('rack-create') || Auth::user()->can('variation_attribute-list') || Auth::user()->can('variation_attribute-create') || Auth::user()->can('stock-stock') || Auth::user()->can('stock-low_stock') || Auth::user()->can('opening-stock-import'))
    <!-- Item & Stock: header only when at least one menu in group is visible -->
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Item & Stock') }}</span>
    </li>
    @endif
    @if($activeFeatures['item'] ?? true)
    @if(Auth::user()->can('item-list') || Auth::user()->can('item-create') || Auth::user()->can('item-import'))
    <li class="menu-item {{ request()->is('item/create') || request()->is('item/*/edit') || request()->is('bulk-item-import') || request()->is('item') || request()->is('item/*/show') || request()->is('bulk-item-update') || request()->is('opening-stock-import') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-heart-pin"></i>
        <div>{{ __('Item') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['item_add'] ?? true)
        @can('item-create')
        <li class="menu-item {{ request()->is('item/create') || request()->is('item/*/edit') ? 'active' : '' }}">
          <a href="{{ route('item.create') }}" class="menu-link">
            <div>{{ __('Add Item') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['item_list'] ?? true)
        @can('item-list')
        <li class="menu-item {{ request()->is('item') || request()->is('item/*/show') ? 'active' : '' }}">
          <a href="{{ route('item.index') }}" class="menu-link">
            <div>{{ __('List Item') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['item_bulk_update'] ?? true)
        @can('item-list')
        <li class="menu-item {{ request()->is('bulk-item-update') ? 'active' : '' }}">
          <a href="{{ route('bulk-item-update') }}" class="menu-link">
            <div>{{ __('Bulk Item Update') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['item_bulk_import'] ?? true)
        @can('item-import')
        <li class="menu-item {{ request()->is('bulk-item-import') ? 'active' : '' }}">
          <a href="{{ route('bulk-item-import') }}" class="menu-link">
            <div>{{ __('Bulk Item Import') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['item_opening_import'] ?? true)
        @can('item-import')
        <li class="menu-item {{ request()->is('opening-stock-import') ? 'active' : '' }}">
          <a href="{{ route('opening-stock-import') }}" class="menu-link">
            <div>{{ __('Opening Stock Import') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['item_configuration'] ?? true)
    @if(Auth::user()->can('category-list') || Auth::user()->can('category-create') || Auth::user()->can('brand-list') || Auth::user()->can('unit-list') || Auth::user()->can('rack-list') || Auth::user()->can('variation_attribute-list'))
    <li class="menu-item {{ (request()->is('item-category*') || request()->is('brand*') || request()->is('unit*') || request()->is('rack*') || request()->is('variation-attribute*') || request()->is('item-category/sort-category')) ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-settings-heart"></i>
        <div>{{ __('Item Configuration') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['ic_category_add'] ?? true)
        @can('category-create')
        <li class="menu-item {{ request()->is('item-category/create') || request()->is('item-category/*/edit') ? 'active' : '' }}">
          <a href="{{ route('item-category.create') }}" class="menu-link">
            <div>{{ __('Add Item Category') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_category_list'] ?? true)
        @can('category-list')
        <li class="menu-item {{ request()->is('item-category') || request()->is('item-category/sort-category') ? 'active' : '' }}">
          <a href="{{ route('item-category.index') }}" class="menu-link">
            <div>{{ __('List Item Category') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_brand_add'] ?? true)
        @can('brand-create')
        <li class="menu-item {{ request()->is('brand/create') || request()->is('brand/*/edit') ? 'active' : '' }}">
          <a href="{{ route('brand.create') }}" class="menu-link">
            <div>{{ __('Add Brand') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_brand_list'] ?? true)
        @can('brand-list')
        <li class="menu-item {{ request()->is('brand') ? 'active' : '' }}">
          <a href="{{ route('brand.index') }}" class="menu-link">
            <div>{{ __('List Brand') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_unit_add'] ?? true)
        @can('unit-create')
        <li class="menu-item {{ request()->is('unit/create') || request()->is('unit/*/edit') ? 'active' : '' }}">
          <a href="{{ route('unit.create') }}" class="menu-link">
            <div>{{ __('Add Unit') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_unit_list'] ?? true)
        @can('unit-list')
        <li class="menu-item {{ request()->is('unit') ? 'active' : '' }}">
          <a href="{{ route('unit.index') }}" class="menu-link">
            <div>{{ __('List Unit') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_rack_add'] ?? true)
        @can('rack-create')
        <li class="menu-item {{ request()->is('rack/create') || request()->is('rack/*/edit') ? 'active' : '' }}">
          <a href="{{ route('rack.create') }}" class="menu-link">
            <div>{{ __('Add Rack') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_rack_list'] ?? true)
        @can('rack-list')
        <li class="menu-item {{ request()->is('rack') ? 'active' : '' }}">
          <a href="{{ route('rack.index') }}" class="menu-link">
            <div>{{ __('List Rack') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_variation_add'] ?? true)
        @can('variation_attribute-create')
        <li class="menu-item {{ request()->is('variation-attribute/create') || request()->is('variation-attribute/*/edit') ? 'active' : '' }}">
          <a href="{{ route('variation-attribute.create') }}" class="menu-link">
            <div>{{ __('Add Variation Attribute') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['ic_variation_list'] ?? true)
        @can('variation_attribute-list')
        <li class="menu-item {{ request()->is('variation-attribute') ? 'active' : '' }}">
          <a href="{{ route('variation-attribute.index') }}" class="menu-link">
            <div>{{ __('List Variation Attribute') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['stock'] ?? true)
    @if(Auth::user()->can('stock-stock') || Auth::user()->can('stock-low_stock'))
    <li class="menu-item {{ (request()->is('stock') && !request()->is('stock/low-stock')) || request()->is('stock/low-stock') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-replace"></i>
        <div>{{ __('Stock') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['stock_view'] ?? true)
        @can('stock-stock')
        <li class="menu-item {{ request()->is('stock') ? 'active' : '' }}">
          <a href="{{ route('stock.index') }}" class="menu-link">
            <div>{{ __('Stock') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['stock_low'] ?? true)
        @can('stock-low_stock')
        <li class="menu-item {{ request()->is('stock/low-stock') ? 'active' : '' }}">
          <a href="{{ route('stock.low-stock') }}" class="menu-link">
            <div>{{ __('Low') }} {{ __('Stock') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    <!-- Sale Income -->
    @if(Auth::user()->can('sale-list') || Auth::user()->can('promotion-list') || Auth::user()->can('delivery_partner-list') || Auth::user()->can('customer-list') || Auth::user()->can('customer_receive-list') || Auth::user()->can('installment_sale-list') || Auth::user()->can('quotation-list') || Auth::user()->can('income-list'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Sale & Customer') }}</span>
    </li>
    @endif
    @if($activeFeatures['sale'] ?? true)
    @if(Auth::user()->can('sale-list') || Auth::user()->can('promotion-list') || Auth::user()->can('delivery_partner-list'))
    <li class="menu-item {{ request()->is('promotion*') || request()->is('delivery-partner*') || request()->is('pos*') || request()->is('sale') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-shopping-cart-heart"></i>
        <div>{{ __('Sale') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['pos'] ?? true)
        <li class="menu-item">
          <a href="{{ route('pos.index') }}" class="menu-link">
            <div>{{ __('POS') }}</div>
          </a>
        </li>
        @endif
        @if($activeFeatures['sale_list'] ?? true)
        @can('sale-list')
        <li class="menu-item {{ request()->is('sale') ? 'active' : '' }}">
          <a href="{{ route('sale.index') }}" class="menu-link">
            <div>{{ __('List') }} {{ __('Sale') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['sale_promotion_add'] ?? true)
        @can('promotion-create')
        <li class="menu-item {{ request()->is('promotion/create') || request()->is('promotion/*/edit') ? 'active' : '' }}">
          <a href="{{ route('promotion.create') }}" class="menu-link">
            <div>{{ __('Add') }} {{ __('Promotion') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['sale_promotion_list'] ?? true)
        @can('promotion-list')
        <li class="menu-item {{ request()->is('promotion') ? 'active' : '' }}">
          <a href="{{ route('promotion.index') }}" class="menu-link">
            <div>{{ __('List') }} {{ __('Promotion') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['sale_delivery_add'] ?? true)
        @can('delivery_partner-create')
        <li class="menu-item {{ request()->is('delivery-partner/create') || request()->is('delivery-partner/*/edit') ? 'active' : '' }}">
          <a href="{{ route('delivery-partner.create') }}" class="menu-link">
            <div>{{ __('Add') }} {{ __('Delivery') }} {{ __('Partner') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['sale_delivery_list'] ?? true)
        @can('delivery_partner-list')
        <li class="menu-item {{ request()->is('delivery-partner') ? 'active' : '' }}">
          <a href="{{ route('delivery-partner.index') }}" class="menu-link">
            <div>{{ __('List') }} {{ __('Delivery') }} {{ __('Partner') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['sale_return'] ?? true)
    @if(Auth::user()->can('sale-list') || Auth::user()->can('sale-create'))
    <li class="menu-item {{ request()->is('sale-return*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-u-turn-right"></i>
        <div>{{ __('Sale Return') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['sale_return_add'] ?? true)
        @can('sale-create')
        <li class="menu-item {{ request()->is('sale-return/create') || request()->is('sale-return/*/edit') ? 'active' : '' }}">
          <a href="{{ route('sale-return.create') }}" class="menu-link">
            <div>{{ __('Add Sale Return') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['sale_return_list'] ?? true)
        @can('sale-list')
        <li class="menu-item {{ request()->is('sale-return') ? 'active' : '' }}">
          <a href="{{ route('sale-return.index') }}" class="menu-link">
            <div>{{ __('List Sale Return') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['installment_sale'] ?? true)
    @if(Auth::user()->can('installment_sale-list') || Auth::user()->can('installment_sale-create'))
    <li class="menu-item {{ (request()->is('installment-sale*') || request()->is('installment-collection*') || request()->is('due-installment*') || request()->is('installment-customer*')) ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-stack-middle"></i>
        <div>{{ __('Installment Sale') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['installment_add'] ?? true)
        @can('installment_sale-create')
        <li class="menu-item {{ request()->is('installment-sale/create') || request()->is('installment-sale/*/edit') || request()->is('installment-sale/*') ? 'active' : '' }}">
          <a href="{{ route('installment-sale.create') }}" class="menu-link">
            <div>{{ __('Add Installment Sale') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['installment_list'] ?? true)
        @can('installment_sale-list')
        <li class="menu-item {{ request()->is('installment-sale') && !request()->is('installment-sale/*') ? 'active' : '' }}">
          <a href="{{ route('installment-sale.index') }}" class="menu-link">
            <div>{{ __('List Installment Sale') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['installment_collection'] ?? true)
        @can('installment_sale-list')
        <li class="menu-item {{ request()->is('installment-collection*') ? 'active' : '' }}">
          <a href="{{ route('installment-collection.index') }}" class="menu-link">
            <div>{{ __('Installment Collection') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['customer'] ?? true)
    @if(Auth::user()->can('customer-list') || Auth::user()->can('customer-create') || Auth::user()->can('customer_receive-list') || Auth::user()->can('customer_receive-create'))
    <li class="menu-item {{ (request()->is('customer*') || request()->is('customer-receive*')) || request()->is('installment-customer*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-user-plus"></i>
        <div>{{ __('Customer') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['customer_add'] ?? true)
        @can('customer-create')
        <li class="menu-item {{ request()->is('customer/create') || request()->is('customer/*/edit') ? 'active' : '' }}">
          <a href="{{ route('customer.create') }}" class="menu-link">
            <div>{{ __('Add Customer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['customer_list'] ?? true)
        @can('customer-list')
        <li class="menu-item {{ request()->is('customer') ? 'active' : '' }}">
          <a href="{{ route('customer.index') }}" class="menu-link">
            <div>{{ __('List Customer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['customer_installment_add'] ?? true)
        @can('customer-list')
        <li class="menu-item {{ request()->is('installment-customer/create') || request()->is('installment-customer/*/edit') ? 'active' : '' }}">
          <a href="{{ route('installment-customer.create') }}" class="menu-link">
            <div>{{ __('Add Installment Customer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['customer_installment_list'] ?? true)
        @can('customer-list')
        <li class="menu-item {{ request()->is('installment-customer') ? 'active' : '' }}">
          <a href="{{ route('installment-customer.index') }}" class="menu-link">
            <div>{{ __('List Installment Customer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['customer_receive_add'] ?? true)
        @can('customer_receive-create')
        <li class="menu-item {{ request()->is('customer-receive/create') || request()->is('customer-receive/*/edit') ? 'active' : '' }}">
          <a href="{{ route('customer-receive.create') }}" class="menu-link">
            <div>{{ __('Add Customer Receive') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['customer_receive_list'] ?? true)
        @can('customer_receive-list')
        <li class="menu-item {{ request()->is('customer-receive') ? 'active' : '' }}">
          <a href="{{ route('customer-receive.index') }}" class="menu-link">
            <div>{{ __('List Customer Receive') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['income'] ?? true)
    @if(Auth::user()->can('income-list') || Auth::user()->can('income-create') || Auth::user()->can('income_category-list') || Auth::user()->can('income_category-create'))
    <li class="menu-item {{ request()->is('income*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-circle-plus"></i>
        <div>{{ __('Income') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['income_add'] ?? true)
        @can('income-create')
        <li class="menu-item {{ request()->is('income/create') || request()->is('income/*/edit') ? 'active' : '' }}">
          <a href="{{ route('income.create') }}" class="menu-link">
            <div>{{ __('Add Income') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['income_list'] ?? true)
        @can('income-list')
        <li class="menu-item {{ request()->is('income') ? 'active' : '' }}">
          <a href="{{ route('income.index') }}" class="menu-link">
            <div>{{ __('List Income') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['income_category_add'] ?? true)
        @can('income_category-create')
        <li class="menu-item {{ request()->is('income-category/create') || request()->is('income-category/*/edit') ? 'active' : '' }}">
          <a href="{{ route('income-category.create') }}" class="menu-link">
            <div>{{ __('Add Income Category') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['income_category_list'] ?? true)
        @can('income_category-list')
        <li class="menu-item {{ request()->is('income-category') ? 'active' : '' }}">
          <a href="{{ route('income-category.index') }}" class="menu-link">
            <div>{{ __('List Income Category') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    
    <!-- Purchase Supplier -->
    @if(Auth::user()->can('purchase-list') || Auth::user()->can('purchase_return-list') || Auth::user()->can('supplier-list') || Auth::user()->can('supplier_payment-list') || Auth::user()->can('expense-list') || Auth::user()->can('transfer-list') || Auth::user()->can('damage-list') || Auth::user()->can('quotation-list'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Purchase & Supplier') }}</span>
    </li>
    @endif
    @if($activeFeatures['purchase'] ?? true)
    @if(Auth::user()->can('purchase-list') || Auth::user()->can('purchase-create'))
    <li class="menu-item {{ request()->is('purchase/create') || request()->is('purchase/*/edit') || request()->is('purchase') || request()->is('purchase/*/show')  ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-basket-down"></i>
        <div>{{ __('Purchase') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['purchase_add'] ?? true)
        @can('purchase-create')
        <li class="menu-item {{ request()->is('purchase/create') || request()->is('purchase/*/edit') ? 'active' : '' }}">
          <a href="{{ route('purchase.create') }}" class="menu-link">
            <div>{{ __('Add Purchase') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['purchase_list'] ?? true)
        @can('purchase-list')
        <li class="menu-item {{ request()->is('purchase') || request()->is('purchase/*/show') ? 'active' : '' }}">
          <a href="{{ route('purchase.index') }}" class="menu-link">  
            <div>{{ __('List Purchase') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['purchase_return'] ?? true)
    @if(Auth::user()->can('purchase_return-list') || Auth::user()->can('purchase_return-create'))
    <li class="menu-item {{ request()->is('purchase-return*') || request()->is('purchase-return/*/show') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-u-turn-left"></i>
        <div>{{ __('Purchase Return') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['purchase_return_add'] ?? true)
        @can('purchase_return-create')
        <li class="menu-item {{ request()->is('purchase-return/create') || request()->is('purchase-return/*/edit') ? 'active' : '' }}">
          <a href="{{ route('purchase-return.create') }}" class="menu-link">
            <div>{{ __('Add Purchase Return') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['purchase_return_list'] ?? true)
        @can('purchase_return-list')
        <li class="menu-item {{ request()->is('purchase-return') || request()->is('purchase-return/*/show') ? 'active' : '' }}">
          <a href="{{ route('purchase-return.index') }}" class="menu-link">
            <div>{{ __('List Purchase Return') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['supplier'] ?? true)
    @if(Auth::user()->can('supplier-list') || Auth::user()->can('supplier-create') || Auth::user()->can('supplier_payment-list') || Auth::user()->can('supplier_payment-create'))
    <li class="menu-item {{ request()->is('supplier*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-user-minus"></i>
        <div>{{ __('Supplier') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['supplier_add'] ?? true)
        @can('supplier-create')
        <li class="menu-item {{ request()->is('supplier/create') || request()->is('supplier/*/edit') ? 'active' : '' }}">
          <a href="{{ route('supplier.create') }}" class="menu-link">
            <div>{{ __('Add Supplier') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['supplier_list'] ?? true)
        @can('supplier-list')
        <li class="menu-item {{ request()->is('supplier') ? 'active' : '' }}">
          <a href="{{ route('supplier.index') }}" class="menu-link">
            <div>{{ __('List Supplier') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['supplier_payment_add'] ?? true)
        @can('supplier_payment-create')
        <li class="menu-item {{ request()->is('supplier-payment/create') || request()->is('supplier-payment/*/edit') ? 'active' : '' }}">
          <a href="{{ route('supplier-payment.create') }}" class="menu-link">
            <div>{{ __('Add Supplier Payment') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['supplier_payment_list'] ?? true)
        @can('supplier_payment-list')
        <li class="menu-item {{ request()->is('supplier-payment') ? 'active' : '' }}">
          <a href="{{ route('supplier-payment.index') }}" class="menu-link">
            <div>{{ __('List Supplier Payment') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['expense'] ?? true)
    @if(Auth::user()->can('expense-list') || Auth::user()->can('expense-create') || Auth::user()->can('expense_category-list') || Auth::user()->can('expense_category-create'))
    <li class="menu-item {{ request()->is('expense*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-circle-minus"></i>
        <div>{{ __('Expense') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['expense_add'] ?? true)
        @can('expense-create')
        <li class="menu-item {{ request()->is('expense/create') || request()->is('expense/*/edit') ? 'active' : '' }}">
          <a href="{{ route('expense.create') }}" class="menu-link">
            <div>{{ __('Add Expense') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['expense_list'] ?? true)
        @can('expense-list')
        <li class="menu-item {{ request()->is('expense') ? 'active' : '' }}">
          <a href="{{ route('expense.index') }}" class="menu-link">
            <div>{{ __('List Expense') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['expense_category_add'] ?? true)
        @can('expense_category-create')
        <li class="menu-item {{ request()->is('expense-category/create') || request()->is('expense-category/*/edit') ? 'active' : '' }}">
          <a href="{{ route('expense-category.create') }}" class="menu-link">
            <div>{{ __('Add Expense Category') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['expense_category_list'] ?? true)
        @can('expense_category-list')
        <li class="menu-item {{ request()->is('expense-category') ? 'active' : '' }}">
          <a href="{{ route('expense-category.index') }}" class="menu-link">
            <div>{{ __('List Expense Category') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    <!-- Transfer & Damage -->
    @if(Auth::user()->can('transfer-list') || Auth::user()->can('damage-list') || Auth::user()->can('quotation-list'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Transfer & Damage') }}</span>
    </li>
    @endif
    @if($activeFeatures['transfer'] ?? true)
    @if(Auth::user()->can('transfer-list') || Auth::user()->can('transfer-create'))
    <li class="menu-item {{ request()->is('transfer*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-truck-delivery"></i>
        <div>{{ __('Transfer') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['transfer_add'] ?? true)
        @can('transfer-create')
        <li class="menu-item {{ request()->is('transfer/create') || request()->is('transfer/*/edit') ? 'active' : '' }}">
          <a href="{{ route('transfer.create') }}" class="menu-link">
            <div>{{ __('Add Transfer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['transfer_list'] ?? true)
        @can('transfer-list')
        <li class="menu-item {{ request()->is('transfer') ? 'active' : '' }}">
          <a href="{{ route('transfer.index') }}" class="menu-link">
            <div>{{ __('List Transfer') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['damage'] ?? true)
    @if(Auth::user()->can('damage-list') || Auth::user()->can('damage-create'))
    <li class="menu-item {{ request()->is('damage*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-trash"></i>
        <div>{{ __('Damage') }}</div>
      </a>
      <ul class="menu-sub ">
        @if($activeFeatures['damage_add'] ?? true)
        @can('damage-create')
        <li class="menu-item {{ request()->is('damage/create') || request()->is('damage/*/edit') ? 'active' : '' }}">
          <a href="{{ route('damage.create') }}" class="menu-link">
            <div>{{ __('Add Damage') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['damage_list'] ?? true)
        @can('damage-list')
        <li class="menu-item {{ request()->is('damage') ? 'active' : '' }}">
          <a href="{{ route('damage.index') }}" class="menu-link">
            <div>{{ __('List Damage') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if($activeFeatures['quotation'] ?? true)
    @if(Auth::user()->can('quotation-list') || Auth::user()->can('quotation-create'))
    <li class="menu-item {{ request()->is('quotation*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-pencil-star"></i>
        <div>{{ __('Quotation') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['quotation_add'] ?? true)
        @can('quotation-create')
        <li class="menu-item {{ request()->is('quotation/create') || request()->is('quotation/*/edit') ? 'active' : '' }}">
          <a href="{{ route('quotation.create') }}" class="menu-link ">
            <div>{{ __('Add Quotation') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['quotation_list'] ?? true)
        @can('quotation-list')
        <li class="menu-item {{ request()->is('quotation') ? 'active' : '' }}">
          <a href="{{ route('quotation.index') }}" class="menu-link">
            <div>{{ __('List Quotation') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    <!-- Fixed Assets -->
    @if($activeFeatures['fixed_asset'] ?? true)
    @if(Auth::user()->can('fixed_asset_item-list') || Auth::user()->can('fixed_asset_item-create') || Auth::user()->can('fixed_asset_stock_in-list') || Auth::user()->can('fixed_asset_stock_in-create') || Auth::user()->can('fixed_asset_stock_out-list') || Auth::user()->can('fixed_asset_stock_out-create'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Fixed Asset') }}</span>
    </li>
    <li class="menu-item {{ request()->is('fixed-asset*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-tournament"></i>
        <div>{{ __('Fixed Asset') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['fixed_asset_add'] ?? true)
        @can('fixed_asset_item-create')
        <li class="menu-item {{ request()->is('fixed-asset-item/create') || request()->is('fixed-asset-item/*/edit') ? 'active' : '' }}">
          <a href="{{ route('fixed-asset-item.create') }}" class="menu-link">
            <div>{{ __('Add Fixed Asset Item') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['fixed_asset_list'] ?? true)
        @can('fixed_asset_item-list')
        <li class="menu-item {{ request()->is('fixed-asset-item') ? 'active' : '' }}">
          <a href="{{ route('fixed-asset-item.index') }}" class="menu-link">
            <div>{{ __('List Fixed Asset Item') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['fixed_asset_stock_in_add'] ?? true)
        @can('fixed_asset_stock_in-create')
        <li class="menu-item {{ request()->is('fixed-asset-stock-in/create') || request()->is('fixed-asset-stock-in/*/edit') ? 'active' : '' }}">
          <a href="{{ route('fixed-asset-stock-in.create') }}" class="menu-link">
            <div>{{ __('Add Fixed Asset Stock In') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['fixed_asset_stock_in_list'] ?? true)
        @can('fixed_asset_stock_in-list')
        <li class="menu-item {{ request()->is('fixed-asset-stock-in')  ? 'active' : '' }}">
          <a href="{{ route('fixed-asset-stock-in.index') }}" class="menu-link">
            <div>{{ __('List Fixed Asset Stock In') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['fixed_asset_stock_out_add'] ?? true)
        @can('fixed_asset_stock_out-create')
        <li class="menu-item {{ request()->is('fixed-asset-stock-out/create') || request()->is('fixed-asset-stock-out/*/edit') ? 'active' : '' }}">
          <a href="{{ route('fixed-asset-stock-out.create') }}" class="menu-link ">
            <div>{{ __('Add Fixed Asset Stock Out') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['fixed_asset_stock_out_list'] ?? true)
        @can('fixed_asset_stock_out-list')
        <li class="menu-item {{ request()->is('fixed-asset-stock-out') ? 'active' : '' }}">
          <a href="{{ route('fixed-asset-stock-out.index') }}" class="menu-link">
            <div>{{ __('List Fixed Asset Stock Out') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    <!-- BusinessClub -->
    @if($activeFeatures['business_club'] ?? true)
    @if(Auth::user()->can('businessclub-list') || Auth::user()->can('businessclub-settings') || Auth::user()->can('businessclub-wallets'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Business Club') }}</span>
    </li>
    <li class="menu-item {{ request()->is('business-club*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-wallet"></i>
        <div>{{ __('Business Club') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['business_club_dashboard'] ?? true)
        @can('businessclub-list')
        <li class="menu-item {{ request()->is('business-club') && !request()->is('business-club/settings') && !request()->is('business-club/wallets') ? 'active' : '' }}">
          <a href="{{ route('businessclub.dashboard') }}" class="menu-link">
            <div>{{ __('Dashboard') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['business_club_settings'] ?? true)
        @can('businessclub-settings')
        <li class="menu-item {{ request()->is('business-club/settings') ? 'active' : '' }}">
          <a href="{{ route('businessclub.settings') }}" class="menu-link">
            <div>{{ __('Settings') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['business_club_wallets'] ?? true)
        @can('businessclub-wallets')
        <li class="menu-item {{ request()->is('business-club/wallets') ? 'active' : '' }}">
          <a href="{{ route('businessclub.wallets') }}" class="menu-link">
            <div>{{ __('Customer') }} {{ __('Wallets') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['business_club_register'] ?? true)
        @can('businessclub-list')
        <li class="menu-item {{ request()->is('business-club/register') ? 'active' : '' }}">
          <a href="{{ route('businessclub.register') }}" class="menu-link"><div>{{ __('Register') }} {{ __('Member') }}</div></a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    <!-- Price Lists -->
    @if($activeFeatures['price_list'] ?? true)
    @if(Auth::user()->can('price_list-list') || Auth::user()->can('price_list-create'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Price Lists') }}</span>
    </li>
    <li class="menu-item {{ request()->is('price-list*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-currency-dollar"></i>
        <div>{{ __('Price Lists') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['price_list_add'] ?? true)
        @can('price_list-create')
        <li class="menu-item {{ request()->is('price-list/create') || request()->is('price-list/*/edit') ? 'active' : '' }}">
          <a href="{{ route('price-list.create') }}" class="menu-link">
            <div>{{ __('Add') }} {{ __('Price List') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['price_list_list'] ?? true)
        @can('price_list-list')
        <li class="menu-item {{ request()->is('price-list') && !request()->is('price-list/create') ? 'active' : '' }}">
          <a href="{{ route('price-list.index') }}" class="menu-link">
            <div>{{ __('List') }} {{ __('Price Lists') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    <!-- Warranty Servicing -->
    @if($activeFeatures['warranty_servicing'] ?? true)
    @if(Auth::user()->can('warranty-list') || Auth::user()->can('warranty-create') || Auth::user()->can('warranty-checking') || Auth::user()->can('servicing-list') || Auth::user()->can('servicing-create'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Warranty & Servicing') }}</span>
    </li>
    <li class="menu-item {{ request()->is('warranty*') || request()->is('servicing*') || request()->is('warranty/checking')  ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-device-desktop-cog"></i>
        <div>{{ __('Warranty') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['servicing_add'] ?? true)
        @can('servicing-create')
        <li class="menu-item {{ request()->is('servicing/create') || request()->is('servicing/*/edit') ? 'active' : '' }}">
          <a href="{{ route('servicing.create') }}" class="menu-link">
            <div>{{ __('Add Servicing') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['servicing_list'] ?? true)
        @can('servicing-list')
        <li class="menu-item {{ request()->is('servicing') ? 'active' : '' }}">
          <a href="{{ route('servicing.index') }}" class="menu-link">
            <div>{{ __('List Servicing') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['warranty_add'] ?? true)
        @can('warranty-create')
        <li class="menu-item {{ request()->is('warranty/create') || request()->is('warranty/*/edit') ? 'active' : '' }}">
          <a href="{{ route('warranty.create') }}" class="menu-link">
            <div>{{ __('Add Warranty') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['warranty_list'] ?? true)
        @can('warranty-list')
        <li class="menu-item {{ request()->is('warranty') ? 'active' : '' }}">
          <a href="{{ route('warranty.index') }}" class="menu-link">
            <div>{{ __('List Warranty') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['warranty_checking'] ?? true)
        @can('warranty-checking')
        <li class="menu-item {{ request()->is('warranty/checking') ? 'active' : '' }}">
          <a href="{{ route('warranty.checking') }}" class="menu-link">
            <div>{{ __('Warranty Checking') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif


    <!-- Accounting Payroll -->
    @if($activeFeatures['accounting'] ?? true)
    @if(Auth::user()->can('payment_method-list') || Auth::user()->can('payment_method-create') || Auth::user()->can('deposit_withdraw-list') || Auth::user()->can('deposit_withdraw-create'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Accounting') }}</span>
    </li>
    @endif
    @if(($activeFeatures['payment_account'] ?? true) && (Auth::user()->can('payment_method-list') || Auth::user()->can('payment_method-create')))
    <li class="menu-item {{ (request()->is('payment-method*') || request()->is('payment-method/sort-payment-method')) ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-building-bank"></i>
        <div>{{ __('Payment Account') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['payment_account_add'] ?? true)
        @can('payment_method-create')
        <li class="menu-item {{ request()->is('payment-method/create') ? 'active' : '' }}">
          <a href="{{ route('payment-method.create') }}" class="menu-link">
            <div>{{ __('Add Payment Account') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['payment_account_list'] ?? true)
        @can('payment_method-list')
        <li class="menu-item {{ request()->is('payment-method') ? 'active' : '' }}">
          <a href="{{ route('payment-method.index') }}" class="menu-link">
            <div>{{ __('List Payment Account') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['payment_account_sort'] ?? true)
        @can('payment_method-list')
        <li class="menu-item {{ request()->is('payment-method/sort-payment-method') ? 'active' : '' }}">
          <a href="{{ route('payment-method.sort-payment-method') }}" class="menu-link">
            <div>{{ __('Sort Account') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @if(($activeFeatures['deposit_withdraw'] ?? true) && (Auth::user()->can('deposit_withdraw-list') || Auth::user()->can('deposit_withdraw-create')))
    <li class="menu-item {{ (request()->is('deposit-withdraw*') || request()->is('account-reports/account-balance/view') || request()->is('account-reports/balance-sheet/view') || request()->is('account-reports/account-statement/view') || request()->is('account-reports/transaction-history/view') || request()->is('account-reports/trial-balance/view')) ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-receipt-dollar"></i>
        <div>{{ __('Accounting') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['accounting_deposit_add'] ?? true)
        @can('deposit_withdraw-create')
        <li class="menu-item {{ request()->is('deposit-withdraw/create') || request()->is('deposit-withdraw/*/edit') ? 'active' : '' }}">
          <a href="{{ route('deposit-withdraw.create') }}" class="menu-link">
            <div>{{ __('Add Deposit/Withdraw') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['accounting_deposit_list'] ?? true)
        @can('deposit_withdraw-list')
        <li class="menu-item {{ request()->is('deposit-withdraw') && !request()->is('deposit-withdraw/create') ? 'active' : '' }}">
          <a href="{{ route('deposit-withdraw.index') }}" class="menu-link">
            <div>{{ __('List Deposit/Withdraw') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['accounting_balance'] ?? true)
        @can('accounting-account_balance')
        <li class="menu-item {{ request()->is('account-reports/account-balance/view') ? 'active' : '' }}">
          <a href="{{ route('accounting.reports.account-balance.view') }}" class="menu-link">
            <div>{{ __('Account Balance') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['accounting_statement'] ?? true)
        @can('accounting-account_statement')
        <li class="menu-item {{ request()->is('account-reports/account-statement/view') ? 'active' : '' }}">
          <a href="{{ route('accounting.reports.account-statement.view') }}" class="menu-link">
            <div>{{ __('Account Statement') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['accounting_balance_sheet'] ?? true)
        @can('accounting-balancesheet')
        <li class="menu-item {{ request()->is('account-reports/balance-sheet/view') ? 'active' : '' }}">
          <a href="{{ route('accounting.reports.balance-sheet.view') }}" class="menu-link">
            <div>{{ __('Balance Sheet') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['accounting_trial_balance'] ?? true)
        @can('accounting-trial_balance')
        <li class="menu-item {{ request()->is('account-reports/trial-balance/view') ? 'active' : '' }}">
          <a href="{{ route('accounting.reports.trial-balance.view') }}" class="menu-link">
            <div>{{ __('Trial Balance') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['accounting_transaction_history'] ?? true)
        @can('accounting-transaction_history')
        <li class="menu-item {{ request()->is('account-reports/transaction-history/view') ? 'active' : '' }}">
          <a href="{{ route('accounting.reports.transaction-history.view') }}" class="menu-link">
            <div>{{ __('Transaction History') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif

    <!-- Marketing -->
    @if($activeFeatures['marketing'] ?? true)
    @if(Auth::user()->can('marketing-email') || Auth::user()->can('marketing-sms') || Auth::user()->can('marketing-whatsapp'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Marketing') }}</span>
    </li>
    <li class="menu-item {{ request()->is('email-marketing') || request()->is('sms-marketing') || request()->is('whatsapp-marketing') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-ad-2"></i>
        <div>{{ __('Marketing') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['marketing_email'] ?? true)
        @can('marketing-email')
        <li class="menu-item {{ request()->is('email-marketing') ? 'active' : '' }}">
          <a href="{{ route('email.marketing') }}" class="menu-link">
            <div>{{ __('Email Marketing') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['marketing_sms'] ?? true)
        @can('marketing-sms')
        <li class="menu-item {{ request()->is('sms-marketing') ? 'active' : '' }}">
          <a href="{{ route('sms.marketing') }}" class="menu-link">
            <div>{{ __('SMS Marketing') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['marketing_whatsapp'] ?? true)
        @can('marketing-whatsapp')
        <li class="menu-item {{ request()->is('whatsapp-marketing') ? 'active' : '' }}">
          <a href="{{ route('whatsapp.marketing') }}" class="menu-link">
            <div>{{ __('WhatsApp Marketing') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    
    
    <!-- Account Attendance -->
    @if($activeFeatures['hrm'] ?? true)
    @if(Auth::user()->can('role-list') || Auth::user()->can('role-create') || Auth::user()->can('user-list') || Auth::user()->can('user-create') || Auth::user()->can('user-edit') || Auth::user()->can('attendance-list') || Auth::user()->can('attendance-create') || Auth::user()->can('salary-list') || Auth::user()->can('salary-create') || Auth::user()->can('employee_advance_payment-list') || Auth::user()->can('employee_advance_payment-create'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Human Resource Management') }}</span>
    </li>
    @if(($activeFeatures['role_permission'] ?? true) && (Auth::user()->can('role-list') || Auth::user()->can('role-create')))
    <li class="menu-item {{ request()->is('role*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-fingerprint"></i>
        <div>{{ __('Role Permission') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['role_add'] ?? true)
        @can('role-create')
        <li class="menu-item {{ request()->is('role/create') || request()->is('role/*/edit') ? 'active' : '' }}">
          <a href="{{ route('role.create') }}" class="menu-link">
            <div>{{ __('Add Role Permission') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['role_list'] ?? true)
        @can('role-list')
        <li class="menu-item {{ request()->is('role') ? 'active' : '' }}">
          <a href="{{ route('role.index') }}" class="menu-link">
            <div>{{ __('List Role Permission') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @if(($activeFeatures['employee'] ?? true) && (Auth::user()->can('user-create') || Auth::user()->can('user-list') || Auth::user()->can('user-edit')))
    <li class="menu-item {{ request()->is('user*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-password-user"></i>
        <div>{{ __('Employee Account') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['employee_add'] ?? true)
        @can('user-create')
        <li class="menu-item {{ request()->is('user/create') || request()->is('user/*/edit') ? 'active' : '' }}">
          <a href="{{ route('user.create') }}" class="menu-link">
            <div>{{ __('Add Employee') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['employee_list'] ?? true)
        @can('user-list')
        <li class="menu-item {{ request()->is('user') ? 'active' : '' }}">
          <a href="{{ route('user.index') }}" class="menu-link">
            <div>{{ __('List Employee') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['employee_update_profile'] ?? true)
        @can('user-edit')
        <li class="menu-item {{ request()->is('user/profile/update') ? 'active' : '' }}">
          <a href="{{ route('user.update-profile') }}" class="menu-link">
            <div>{{ __('Update Profile') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @if(($activeFeatures['attendance'] ?? true) && (($activeFeatures['attendance_add'] ?? true) || ($activeFeatures['attendance_list'] ?? true)) && (Auth::user()->can('attendance-list') || Auth::user()->can('attendance-create')))
    <li class="menu-item {{ request()->is('attendance*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-clock-24"></i>
        <div>{{ __('Attendance') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['attendance_add'] ?? true)
        @can('attendance-create')
        <li class="menu-item {{ request()->is('attendance/create') || request()->is('attendance/*/edit') ? 'active' : '' }}">
          <a href="{{ route('attendance.create') }}" class="menu-link">
            <div>{{ __('Add Attendance') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['attendance_list'] ?? true)
        @can('attendance-list')
        <li class="menu-item {{ request()->is('attendance') ? 'active' : '' }}">
          <a href="{{ route('attendance.index') }}" class="menu-link">
            <div>{{ __('List Attendance') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @if(($activeFeatures['salary'] ?? true) && (Auth::user()->can('salary-list') || Auth::user()->can('salary-create')))
    <li class="menu-item {{ request()->is('salary*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-wallet"></i>
        <div>{{ __('Salary/Payroll') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['salary_add'] ?? true)
        @can('salary-create')
        <li class="menu-item {{ request()->is('salary/create') || request()->is('salary/*/edit') ? 'active' : '' }}">
          <a href="{{ route('salary.create') }}" class="menu-link">
            <div>{{ __('Add Salary') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['salary_list'] ?? true)
        @can('salary-list')
        <li class="menu-item {{ request()->is('salary') ? 'active' : '' }}">
          <a href="{{ route('salary.index') }}" class="menu-link">
            <div>{{ __('List Salary') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @if(($activeFeatures['employee_advance'] ?? true) && (Auth::user()->can('employee_advance_payment-list') || Auth::user()->can('employee_advance_payment-create')))
    <li class="menu-item {{ request()->is('employee-advance-payment*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-cash"></i>
        <div>{{ __('Employee Advance Payment') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['employee_advance_add'] ?? true)
        @can('employee_advance_payment-create')
        <li class="menu-item {{ request()->is('employee-advance-payment/create') || request()->is('employee-advance-payment/*/edit') ? 'active' : '' }}">
          <a href="{{ route('employee-advance-payment.create') }}" class="menu-link">
            <div>{{ __('Add Employee Advance Payment') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['employee_advance_list'] ?? true)
        @can('employee_advance_payment-list')
        <li class="menu-item {{ request()->is('employee-advance-payment') ? 'active' : '' }}">
          <a href="{{ route('employee-advance-payment.index') }}" class="menu-link">
            <div>{{ __('List Employee Advance Payment') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @endif

    <!-- Report -->
    @php
      $reportPerms = ['report-register_report','report-z_report','report-daily_summary_report','report-sale_report','report-due_sale_report','report-final_invoice_due_report','report-service_sale_report','report-combo_service_report','report-stock_report','report-low_stock_report','report-expire_soon_report','report-employee_sale_report','report-customer_receive_report','report-attendance_report','report-product_profit_report','report-supplier_ledger_report','report-supplier_balance_report','report-customer_ledger_report','report-customer_balance_report','report-servicing_report','report-product_sale_report','report-tax_report','report-detailed_sale_report','report-profit_loss_report','report-purchase_report','report-expense_report','report-income_report','report-salary_report','report-purchase_return_report','report-sale_return_report','report-damage_report','report-installment_report','report-installment_due_report','report-item_tracking_report','report-price_history_report','report-cash_flow_report','report-available_loyalty_point_report','report-usage_loyalty_point_report'];
      $hasReportPermission = collect($reportPerms)->contains(fn($p) => Auth::user()->can($p));
    @endphp
    @if($hasReportPermission || Auth::user()->can('denomination-list') || Auth::user()->can('multiple_currency-list') || Auth::user()->can('printer-list') || Auth::user()->can('counter-list'))
    <li class="menu-header small">
      <span class="menu-header-text">{{ __('Report & Setting') }}</span>
    </li>
    @endif
    @if($activeFeatures['report'] ?? true)
    @if($hasReportPermission)
    <li class="menu-item {{ request()->is('reports*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-book-2"></i>
        <div>{{ __('Report') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['report_all'] ?? true)
        @can('report-register_report')
        <li class="menu-item {{ request()->is('reports/register-report') ? 'active' : '' }}"><a href="{{ route('report.register-report') }}" class="menu-link"><div>{{ __('Register Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-z_report')
        <li class="menu-item {{ request()->is('reports/z-report') ? 'active' : '' }}"><a href="{{ route('report.z-report') }}" class="menu-link"><div>{{ __('Z Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-daily_summary_report')
        <li class="menu-item {{ request()->is('reports/daily-summary-report') ? 'active' : '' }}"><a href="{{ route('report.daily-summary-report') }}" class="menu-link"><div>{{ __('Daily Summary Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-sale_report')
        <li class="menu-item {{ request()->is('reports/sale-report') ? 'active' : '' }}"><a href="{{ route('report.sale-report') }}" class="menu-link"><div>{{ __('Sale Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-due_sale_report')
        <li class="menu-item {{ request()->is('reports/due-sale-report') ? 'active' : '' }}"><a href="{{ route('report.due-sale-report') }}" class="menu-link"><div>{{ __('Due Sale Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-final_invoice_due_report')
        <li class="menu-item {{ request()->is('reports/final-invoice-due-report') ? 'active' : '' }}"><a href="{{ route('report.final-invoice-due-report') }}" class="menu-link"><div>{{ __('Final Invoice Due Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-service_sale_report')
        <li class="menu-item {{ request()->is('reports/service-sale-report') ? 'active' : '' }}"><a href="{{ route('report.service-sale-report') }}" class="menu-link"><div>{{ __('Service Sale Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-combo_service_report')
        <li class="menu-item {{ request()->is('reports/combo-service-report') ? 'active' : '' }}"><a href="{{ route('report.combo-service-report') }}" class="menu-link"><div>{{ __('Combo Service Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-stock_report')
        <li class="menu-item {{ request()->is('reports/stock-report') ? 'active' : '' }}"><a href="{{ route('report.stock-report') }}" class="menu-link"><div>{{ __('Stock Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-low_stock_report')
        <li class="menu-item {{ request()->is('reports/low-stock-report') ? 'active' : '' }}"><a href="{{ route('report.low-stock-report') }}" class="menu-link"><div>{{ __('Low Stock Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-expire_soon_report')
        <li class="menu-item {{ request()->is('reports/expire-soon-report') ? 'active' : '' }}"><a href="{{ route('report.expire-soon-report') }}" class="menu-link"><div>{{ __('Expire Soon Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-employee_sale_report')
        <li class="menu-item {{ request()->is('reports/employee-sale-report') ? 'active' : '' }}"><a href="{{ route('report.employee-sale-report') }}" class="menu-link"><div>{{ __('Employee Sale Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-customer_receive_report')
        <li class="menu-item {{ request()->is('reports/customer-receive-report') ? 'active' : '' }}"><a href="{{ route('report.customer-receive-report') }}" class="menu-link"><div>{{ __('Customer Receive Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-attendance_report')
        <li class="menu-item {{ request()->is('reports/attendance-report') ? 'active' : '' }}"><a href="{{ route('report.attendance-report') }}" class="menu-link"><div>{{ __('Attendance Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-product_profit_report')
        <li class="menu-item {{ request()->is('reports/product-profit-report') ? 'active' : '' }}"><a href="{{ route('report.product-profit-report') }}" class="menu-link"><div>{{ __('Product Profit Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-supplier_ledger_report')
        <li class="menu-item {{ request()->is('reports/supplier-ledger-report') ? 'active' : '' }}"><a href="{{ route('report.supplier-ledger-report') }}" class="menu-link"><div>{{ __('Supplier Ledger Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-supplier_balance_report')
        <li class="menu-item {{ request()->is('reports/supplier-balance-report') ? 'active' : '' }}"><a href="{{ route('report.supplier-balance-report') }}" class="menu-link"><div>{{ __('Supplier Balance Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-customer_ledger_report')
        <li class="menu-item {{ request()->is('reports/customer-ledger-report') ? 'active' : '' }}"><a href="{{ route('report.customer-ledger-report') }}" class="menu-link"><div>{{ __('Customer Ledger Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-customer_balance_report')
        <li class="menu-item {{ request()->is('reports/customer-balance-report') ? 'active' : '' }}"><a href="{{ route('report.customer-balance-report') }}" class="menu-link"><div>{{ __('Customer Balance Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-servicing_report')
        <li class="menu-item {{ request()->is('reports/servicing-report') ? 'active' : '' }}"><a href="{{ route('report.servicing-report') }}" class="menu-link"><div>{{ __('Servicing Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-product_sale_report')
        <li class="menu-item {{ request()->is('reports/product-sale-report') ? 'active' : '' }}"><a href="{{ route('report.product-sale-report') }}" class="menu-link"><div>{{ __('Product Sale Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-tax_report')
        <li class="menu-item {{ request()->is('reports/tax-report') ? 'active' : '' }}"><a href="{{ route('report.tax-report') }}" class="menu-link"><div>{{ __('Tax Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-tax_report')
        <li class="menu-item {{ request()->is('reports/gst-report') ? 'active' : '' }}"><a href="{{ route('report.gst-report') }}" class="menu-link"><div>{{ __('GST Reports') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-detailed_sale_report')
        <li class="menu-item {{ request()->is('reports/detailed-sale-report') ? 'active' : '' }}"><a href="{{ route('report.detailed-sale-report') }}" class="menu-link"><div>{{ __('Detailed Sale Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-profit_loss_report')
        <li class="menu-item {{ request()->is('reports/profit-loss-report') ? 'active' : '' }}"><a href="{{ route('report.profit-loss-report') }}" class="menu-link"><div>{{ __('Profit Loss Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-purchase_report')
        <li class="menu-item {{ request()->is('reports/purchase-report') ? 'active' : '' }}"><a href="{{ route('report.purchase-report') }}" class="menu-link"><div>{{ __('Purchase Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-expense_report')
        <li class="menu-item {{ request()->is('reports/expense-report') ? 'active' : '' }}"><a href="{{ route('report.expense-report') }}" class="menu-link"><div>{{ __('Expense Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-income_report')
        <li class="menu-item {{ request()->is('reports/income-report') ? 'active' : '' }}"><a href="{{ route('report.income-report') }}" class="menu-link"><div>{{ __('Income Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-salary_report')
        <li class="menu-item {{ request()->is('reports/salary-report') ? 'active' : '' }}"><a href="{{ route('report.salary-report') }}" class="menu-link"><div>{{ __('Salary Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-purchase_return_report')
        <li class="menu-item {{ request()->is('reports/purchase-return-report') ? 'active' : '' }}"><a href="{{ route('report.purchase-return-report') }}" class="menu-link"><div>{{ __('Purchase Return Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-sale_return_report')
        <li class="menu-item {{ request()->is('reports/sale-return-report') ? 'active' : '' }}"><a href="{{ route('report.sale-return-report') }}" class="menu-link"><div>{{ __('Sale Return Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-damage_report')
        <li class="menu-item {{ request()->is('reports/damage-report') ? 'active' : '' }}"><a href="{{ route('report.damage-report') }}" class="menu-link"><div>{{ __('Damage Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-installment_report')
        <li class="menu-item {{ request()->is('reports/installment-report') ? 'active' : '' }}"><a href="{{ route('report.installment-report') }}" class="menu-link"><div>{{ __('Installment Collection Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-installment_due_report')
        <li class="menu-item {{ request()->is('reports/installment-due-report') ? 'active' : '' }}"><a href="{{ route('report.installment-due-report') }}" class="menu-link"><div>{{ __('Installment Due Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-item_tracking_report')
        <li class="menu-item {{ request()->is('reports/item-tracking-report') ? 'active' : '' }}"><a href="{{ route('report.item-tracking-report') }}" class="menu-link"><div>{{ __('Item Tracking Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-price_history_report')
        <li class="menu-item {{ request()->is('reports/price-history-report') ? 'active' : '' }}"><a href="{{ route('report.price-history-report') }}" class="menu-link"><div>{{ __('Price History Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-cash_flow_report')
        <li class="menu-item {{ request()->is('reports/cash-flow-report') ? 'active' : '' }}"><a href="{{ route('report.cash-flow-report') }}" class="menu-link"><div>{{ __('Cash Flow Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-available_loyalty_point_report')
        <li class="menu-item {{ request()->is('reports/available-loyalty-point-report') ? 'active' : '' }}"><a href="{{ route('report.available-loyalty-point-report') }}" class="menu-link"><div>{{ __('Available Loyalty Point Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-usage_loyalty_point_report')
        <li class="menu-item {{ request()->is('reports/usage-loyalty-point-report') ? 'active' : '' }}"><a href="{{ route('report.usage-loyalty-point-report') }}" class="menu-link"><div>{{ __('Usage Loyalty Point Report') }}</div></a></li>
        @endcan
        @endif
        @if($activeFeatures['report_all'] ?? true)
        @can('report-scheme_report')
        <li class="menu-item {{ request()->is('reports/scheme-report') ? 'active' : '' }}"><a href="{{ route('report.scheme-report') }}" class="menu-link"><div>{{ __('Scheme_Report') }}</div></a></li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    @endif
    @if(Auth::user()->can('denomination-list') || Auth::user()->can('denomination-create') || Auth::user()->can('multiple_currency-list') || Auth::user()->can('multiple_currency-create') || Auth::user()->can('printer-list') || Auth::user()->can('printer-create') || Auth::user()->can('counter-list') || Auth::user()->can('counter-create') || Auth::user()->hasRole('Super Admin'))
    <li class="menu-item {{ (request()->is('setting*') || request()->is('feature-activation*') || request()->is('denomination*') || request()->is('multiple-currency*') || request()->is('printer*') || request()->is('counter*')) ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-settings"></i>
        <div>{{ __('Settings') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['settings_all'] ?? true)
        <li class="menu-item {{ request()->is('setting*') ? 'active' : '' }}">
          <a href="{{ route('setting', 'business_setting') }}" class="menu-link">
            <div>{{ __('All Settings') }}</div>
          </a>
        </li>
        @endif
        @if($activeFeatures['feature_activation'] ?? true)
        <li class="menu-item {{ request()->is('feature-activation') ? 'active' : '' }}">
          <a href="{{ route('feature-activation.index') }}" class="menu-link">
            <div>{{ __('Feature Activation') }}</div>
          </a>
        </li>
        @endif
        @if($activeFeatures['denomination_add'] ?? true)
        @can('denomination-create')
        <li class="menu-item {{ request()->is('denomination/create') || request()->is('denomination/*/edit')  ? 'active' : '' }}">
          <a href="{{ route('denomination.create') }}" class="menu-link">
            <div>{{ __('Add Denomination') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['denomination_list'] ?? true)
        @can('denomination-list')
        <li class="menu-item {{ request()->is('denomination') ? 'active' : '' }}">
          <a href="{{ route('denomination.index') }}" class="menu-link">
            <div>{{ __('List Denomination') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['currency_add'] ?? true)
        @can('multiple_currency-create')
        <li class="menu-item {{ request()->is('multiple-currency/create') || request()->is('multiple-currency/*/edit') ? 'active' : '' }}">
          <a href="{{ route('multiple-currency.create') }}" class="menu-link">
            <div>{{ __('Add Multiple Currency') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['currency_list'] ?? true)
        @can('multiple_currency-list')
        <li class="menu-item {{ request()->is('multiple-currency') ? 'active' : '' }}">
          <a href="{{ route('multiple-currency.index') }}" class="menu-link">
            <div>{{ __('List Multiple Currency') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['printer_add'] ?? true)
        @can('printer-create')
        <li class="menu-item {{ request()->is('printer/create') || request()->is('printer/*/edit') ? 'active' : '' }}">
          <a href="{{ route('printer.create') }}" class="menu-link">
            <div>{{ __('Add Printer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['printer_list'] ?? true)
        @can('printer-list')
        <li class="menu-item {{ request()->is('printer') ? 'active' : '' }}">
          <a href="{{ route('printer.index') }}" class="menu-link">
            <div>{{ __('List Printer') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['counter_add'] ?? true)
        @can('counter-create')
        <li class="menu-item {{ request()->is('counter/create') || request()->is('counter/*/edit') ? 'active' : '' }}">
          <a href="{{ route('counter.create') }}" class="menu-link">
            <div>{{ __('Add Counter') }}</div>
          </a>
        </li>
        @endcan
        @endif
        @if($activeFeatures['counter_list'] ?? true)
        @can('counter-list')
        <li class="menu-item {{ request()->is('counter') ? 'active' : '' }}">
          <a href="{{ route('counter.index') }}" class="menu-link">
            <div>{{ __('List Counter') }}</div>
          </a>
        </li>
        @endcan
        @endif
      </ul>
    </li>
    @endif
    
    {{-- Modules Management --}}
    <li class="menu-item {{ request()->is('modules*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon icon-base ti tabler-puzzle"></i>
        <div>{{ __('Modules') }}</div>
      </a>
      <ul class="menu-sub">
        @if($activeFeatures['module_add'] ?? true)
        <li class="menu-item {{ request()->is('modules/create') ? 'active' : '' }}">
          <a href="{{ route('modules.create') }}" class="menu-link">
            <div>{{ __('Add Module') }}</div>
          </a>
        </li>
        @endif
        @if($activeFeatures['module_list'] ?? true)
        <li class="menu-item {{ request()->is('modules') && !request()->is('modules/create') ? 'active' : '' }}">
          <a href="{{ route('modules.index') }}" class="menu-link">
            <div>{{ __('List Modules') }}</div>
          </a>
        </li>
        @endif
      </ul>
    </li>
    
    {{-- BusyNotify Import --}}
    <li class="menu-item {{ request()->is('busy-import*') ? 'active' : '' }}">
      <a href="{{ route('busy-import.index') }}" class="menu-link">
        <i class="menu-icon icon-base ti tabler-api"></i>
        <div>{{ __('Busy Import') }}</div>
      </a>
    </li>
    
    {{-- Update and Uninstall menu items removed --}}
    <li class="menu-item">
      <a href="app-chat.html" class="menu-link">
        <i class="menu-icon icon-base ti tabler-logout"></i>
        <div>{{ __('Logout') }}</div>
      </a>
    </li>
  </ul>
</aside>
<div class="menu-mobile-toggler d-xl-none rounded-1">
  <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
    <i class="ti tabler-menu icon-base"></i>
    <i class="ti tabler-chevron-right icon-base"></i>
  </a>
</div>