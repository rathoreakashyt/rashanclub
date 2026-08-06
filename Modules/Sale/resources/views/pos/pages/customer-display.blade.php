@extends('sale::pos.pos-layout')
@section('page-title', __('Customer Display'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('pos_assets/css/customer-display.css') }}">
@endpush
@section('page-content')

<div class="customer-display-container">
    <!-- Header -->
    <div class="customer-display-header bg-label-primary">
        <div class="display-header-content">
            <h2 class="display-store-name">{{ __('Customer Display') }}</h2>
        </div>
        <div class="display-logo">
            @if(session('white_label.site_logo'))
                <img src="{!! lpFaviconLogoUrl(asset('uploads/whitelabel/' . (session('white_label.site_logo') ?? 'initial-logo.png'))) !!}" alt="Logo" class="logo-img">
            @endif
        </div>
    </div>

    <!-- Cart Items Section -->
    <div class="customer-display-content">
        <div class="display-cart-items" id="customer-display-cart-items">
            <!-- Empty Cart State -->
            <div class="display-empty-cart" id="customer-display-empty-cart">
                <div class="empty-cart-icon">
                    <i class="ti tabler-shopping-cart"></i>
                </div>
                <h4>{{ __('Cart is empty') }}</h4>
                <p>{{ __('Items will appear here when added to cart') }}</p>
            </div>

            <!-- Cart Items List -->
            <div class="display-cart-list" id="customer-display-cart-list" style="display: none;">
                <div class="display-cart-table-wrapper">
                    <table class="display-cart-table">
                        <thead>
                            <tr>
                                <th class="item-name-col">{{ __('Item') }}</th>
                                <th class="qty-col">{{ __('Qty') }}</th>
                                <th class="price-col">{{ __('Price') }}</th>
                                <th class="total-col">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody id="customer-display-cart-tbody">
                            <!-- Cart items will be added dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="customer-display-footer">
        <!-- Summary Section -->
        <div class="display-summary-section" id="customer-display-summary" style="display: none;">
            <div class="summary-row">
                <span class="summary-label">{{ __('Subtotal') }}:</span>
                <span class="summary-value" id="customer-display-subtotal">0.00</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">{{ __('Tax') }}:</span>
                <span class="summary-value" id="customer-display-tax">0.00</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">{{ __('Discount') }}:</span>
                <span class="summary-value" id="customer-display-discount">0.00</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">{{ __('Shipping') }}:</span>
                <span class="summary-value" id="customer-display-shipping">0.00</span>
            </div>
        </div>
        <div class="summary-row summary-total bg-label-primary">
            <span class="summary-label">{{ __('Total Payable') }}:</span>
            <span class="summary-value" id="customer-display-total">0.00</span>
        </div>
    </div>
</div>

@endsection
@push('page-js')
<script src="{{ asset('pos_assets/js/customer-display.js') }}"></script>
@endpush
