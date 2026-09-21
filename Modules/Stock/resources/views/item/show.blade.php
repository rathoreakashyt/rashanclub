@extends('backend.backend_layout')
@section('page-title', __('Item') . ' ' . __('Details'))
@push('page-css')
<style>
    .item-show-hero {
        background: linear-gradient(135deg, var(--bs-body-bg) 0%, var(--bs-secondary-bg-subtle, #f8f9fa) 100%);
        border-radius: 0.5rem;
    }
    .item-show-photo {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 0.5rem;
        border: 2px solid var(--bs-border-color);
        background: var(--bs-body-bg);
    }
    .item-show-photo-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 0.5rem;
        background: var(--bs-secondary-bg);
        border: 2px dashed var(--bs-border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--bs-secondary-color);
    }
    .detail-row {
        display: flex;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--bs-border-color-translucent);
    }
    .detail-row:last-child { border-bottom: 0; }
    .detail-label {
        flex: 0 0 140px;
        font-size: 14px;
        color: var(--bs-secondary-color);
    }
    .detail-value {
        flex: 1;
        font-weight: 500;
    }
    .info-card {
        height: 100%;
        transition: box-shadow 0.2s ease;
    }
    .info-card:hover {
        box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.06);
    }
    .section-card .card-header {
        font-weight: 600;
        font-size: 0.9375rem;
        padding: 0.75rem 1rem;
        background: transparent;
        border-bottom: 1px solid var(--bs-border-color-translucent);
    }
    @media print {
        .no-print { display: none !important; }
    }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 row-gap-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ __('Item') }} {{ __('Details') }}</h4>
            <p class="text-muted mb-0 small">{{ $item->code }}</p>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                ['label' => '<i class="ti tabler-home"></i>', 'link' => '#'],
                ['label' => __('Items'), 'link' => route('item.index')],
                ['label' => __('Item') . ' ' . __('Details'), 'active' => true]
            ]
        ])
    </div>

    {{-- Hero: Photo + Title + Actions --}}
    <div class="card item-show-hero border-0 shadow-sm mb-4 no-print">
        <div class="card-body py-4">
            <div class="row align-items-center g-4">
                <div class="col-auto">
                    @if($item->photo)
                        <img src="{{ asset('uploads/items/' . $item->photo) }}" alt="{{ $item->name }}" class="item-show-photo">
                    @else
                        <div class="item-show-photo-placeholder">
                            <i class="ti tabler-photo-off ti-xl"></i>
                        </div>
                    @endif
                </div>
                <div class="col">
                    <h5 class="mb-1 fw-semibold">{{ $item->name }}</h5>
                    <span class="badge bg-label-primary me-1">Type: {{ str_replace('_', ' ', $item->type) }}</span>
                    @if($item->type  == 'Medicine_Product' && $item->generic_name)
                        <span class="badge bg-label-secondary">Generic Name: {{ $item->generic_name }}</span>
                    @endif
                    @if($item->category && $item->category->name)
                        <span class="badge bg-label-secondary">Group: {{ $item->category->name }}</span>
                    @endif
                </div>
                <div class="col-12 col-md-auto ms-md-auto">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('item.edit', $item->encrypted_id) }}" class="btn btn-primary">
                            <i class="ti tabler-edit me-1"></i> {{ __('Edit') }}
                        </a>
                        <a href="{{ route('item.index') }}" class="btn btn-label-secondary">
                            <i class="ti tabler-arrow-left me-1"></i> {{ __('Back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Basic Information --}}
        <div class="col-12 col-lg-6">
            <div class="card info-card border shadow-sm section-card">
                <div class="card-header">{{ __('Item') }} {{ __('Information') }}</div>
                <div class="card-body pt-0">
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Item Name') }}</span>
                        <span class="detail-value">{{ $item->name }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Item Code') }}</span>
                        <span class="detail-value">{{ $item->code }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Item Type') }}</span>
                        <span class="detail-value">{{ str_replace('_', ' ', $item->type) }}</span>
                    </div>
                    @if($item->alternative_name)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Alternative Name') }}</span>
                        <span class="detail-value">{{ $item->alternative_name }}</span>
                    </div>
                    @endif
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Group') }}</span>
                        <span class="detail-value">{{ $item->category->name ?? '—' }}</span>
                    </div>
                    @if($item->brand_id)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Brand') }}</span>
                        <span class="detail-value">{{ $item->brand->name ?? '—' }}</span>
                    </div>
                    @endif
                    @if($item->supplier_id)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Supplier') }}</span>
                        <span class="detail-value">{{ $item->supplier->name ?? '—' }}</span>
                    </div>
                    @endif
                    @if($item->loyalty_point)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Loyalty Point') }}</span>
                        <span class="detail-value">{{ $item->loyalty_point }}</span>
                    </div>
                    @endif
                    @if($item->description)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Description') }}</span>
                        <span class="detail-value">{{ $item->description }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Price Information (hidden for Variation_Product) --}}
        @if($item->type !== 'Variation_Product')
        <div class="col-12 col-lg-6">
            <div class="card info-card border shadow-sm section-card">
                <div class="card-header">{{ __('Price Information') }}</div>
                <div class="card-body pt-0">
                    @if($item->type !== 'Variation_Product' && $item->type !== 'Combo_Product' && $item->purchase_price)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Purchase Price') }}</span>
                        <span class="detail-value">{{ formatAmount($item->purchase_price) }}</span>
                    </div>
                    @endif
                    @if($item->type !== 'Combo_Product')
                    <div class="detail-row">
                        <span class="detail-label">{{ __('MRP Price') }}</span>
                        <span class="detail-value">{{ formatAmount($item->mrp_price ?? 0) }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Sale Price') }}</span>
                        <span class="detail-value">{{ formatAmount($item->sale_price ?? 0) }}</span>
                    </div>
                    @endif
                    @if($item->type === 'Combo_Product')
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Combo Sale Price') }}</span>
                        <span class="detail-value">{{ formatAmount($item->sale_price ?? 0) }}</span>
                    </div>
                    @endif
                    @if($item->type !== 'Variation_Product' && $item->type !== 'Combo_Product')
                        @if($item->whole_sale_price)
                        <div class="detail-row">
                            <span class="detail-label">{{ __('Whole Sale Price') }}</span>
                            <span class="detail-value">{{ formatAmount($item->whole_sale_price) }}</span>
                        </div>
                        @endif
                        @if($item->profit_margin)
                        <div class="detail-row">
                            <span class="detail-label">{{ __('Profit Margin') }}</span>
                            <span class="detail-value">{{ ($item->profit_margin) }}%</span>
                        </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($item->type === 'Medicine_Product')
        <div class="col-12 col-lg-6">
            <div class="card info-card border shadow-sm section-card">
                <div class="card-header">{{ __('Pharmacy Information') }}</div>
                <div class="card-body pt-0">
                    @if($item->expiry_date_maintain)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Expiry Date Maintain') }}</span>
                        <span class="detail-value">{{ $item->expiry_date_maintain }}</span>
                    </div>
                    @endif
                    @if($item->generic_name)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Generic Name') }}</span>
                        <span class="detail-value">{{ $item->generic_name }}</span>
                    </div>
                    @endif
                    @if($item->rack_id)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Rack') }}</span>
                        <span class="detail-value">{{ $item->rack->name ?? '—' }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($item->type !== 'Service_Product' && $item->type !== 'Combo_Product')
        <div class="col-12 col-lg-6">
            <div class="card info-card border shadow-sm section-card">
                <div class="card-header">{{ __('Unit Information') }}</div>
                <div class="card-body pt-0">
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Unit Type') }}</span>
                        <span class="detail-value">{{ $item->unit_type == 1 ? __('Single Unit') : __('Double Unit') }}</span>
                    </div>
                    @if($item->purchase_unit_id)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Purchase Unit') }}</span>
                        <span class="detail-value">{{ $item->purchaseUnit->unit_name ?? '—' }}</span>
                    </div>
                    @endif
                    @if($item->sale_unit_id)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Sale Unit') }}</span>
                        <span class="detail-value">{{ $item->saleUnit->unit_name ?? '—' }}</span>
                    </div>
                    @endif
                    @if($item->conversion_rate)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Conversion Rate') }}</span>
                        <span class="detail-value">{{ $item->conversion_rate }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($item->type !== 'Service_Product' && $item->type !== 'Combo_Product' && $item->type !== 'Variation_Product')
        {{-- Stock Information (hidden for Variation_Product) --}}
        <div class="col-12 col-lg-6">
            <div class="card info-card border shadow-sm section-card">
                <div class="card-header">{{ __('Stock Information') }}</div>
                <div class="card-body pt-0">
                    @if($item->alert_quantity)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Alert Quantity') }}</span>
                        <span class="detail-value">{{ $item->alert_quantity }}</span>
                    </div>
                    @else
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Alert Quantity') }}</span>
                        <span class="detail-value text-muted">—</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($item->warranty || $item->guarantee)
        <div class="col-12 col-lg-6">
            <div class="card info-card border shadow-sm section-card">
                <div class="card-header">{{ __('Warranty Information') }}</div>
                <div class="card-body pt-0">
                    @if($item->warranty)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Warranty') }}</span>
                        <span class="detail-value">{{ $item->warranty }} {{ $item->warranty_date ?? '' }}</span>
                    </div>
                    @endif
                    @if($item->guarantee)
                    <div class="detail-row">
                        <span class="detail-label">{{ __('Guarantee') }}</span>
                        <span class="detail-value">{{ $item->guarantee }} {{ $item->guarantee_date ?? '' }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Variation Product Table --}}
    @if($item->type === 'Variation_Product' && isset($variations))
    <div class="card border shadow-sm mt-4">
        <div class="card-header">
            <h6 class="mb-0">{{ __('Variation Information') }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Variation Name') }}</th>
                            <th>{{ __('Item Code') }}</th>
                            <th>{{ __('Purchase Price') }}</th>
                            <th>{{ __('MRP Price') }}</th>
                            <th>{{ __('Sale Price') }}</th>
                            <th>{{ __('Whole Sale Price') }}</th>
                            <th>{{ __('Alert Quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($variations as $variation)
                        @php
                            $stock = (int)($variation['stock'] ?? 0);
                            $unitType = $variation['unit_type'] ?? '1';
                            $conversionRate = (int)($variation['conversion_rate'] ?? 1) ?: 1;
                            $purchaseUnitName = $variation['purchase_unit_name'] ?? '—';
                            $saleUnitName = $variation['sale_unit_name'] ?? '—';
                        @endphp
                        <tr>
                            <td>{{ $variation['variation_name'] }}</td>
                            <td><code class="small">{{ $variation['item_code'] }}</code></td>
                            <td>{{ formatAmount($variation['purchase_price'] ?? 0) }}</td>
                            <td>{{ formatAmount($variation['mrp_price'] ?? 0) }}</td>
                            <td>{{ formatAmount($variation['sale_price'] ?? 0) }}</td>
                            <td>{{ formatAmount($variation['whole_sale_price'] ?? 0) }}</td>
                            <td>{{ $variation['alert_quantity'] ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Variation Product: Outlet-wise Opening Stock --}}
    @if(isset($outlets) && $outlets->isNotEmpty() )
    <div class="card border shadow-sm mt-4">
        <div class="card-header">
            <h6 class="mb-0">{{ __('Opening Stock') }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Outlet') }}</th>
                            <th>{{ __('Variation Name') }}</th>
                            <th>{{ __('Item Code') }}</th>
                            <th>{{ __('Purchase Unit Quantity') }}</th>
                            <th>{{ __('Sale Unit Quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($outlets as $outlet)
                            @foreach($variations as $variation)
                            @php
                                $openingStock = $variation['opening_stock'] ?? [];
                                $qty = (int)($openingStock[$outlet->id] ?? 0);
                                $unitType = $variation['unit_type'] ?? '1';
                                $conversionRate = (int)($variation['conversion_rate'] ?? 1) ?: 1;
                                $purchaseUnitName = $variation['purchase_unit_name'] ?? '—';
                                $saleUnitName = $variation['sale_unit_name'] ?? '—';
                            @endphp
                            <tr>
                                <td>{{ $outlet->outlet_name }}</td>
                                <td>{{ $variation['variation_name'] }}</td>
                                <td><code class="small">{{ $variation['item_code'] }}</code></td>
                                @if(($unitType ?? '1') == '2')
                                <td>{{ $conversionRate ? (int)($qty) : $qty }} {{ $purchaseUnitName }}</td>
                                <td>{{ $qty * $conversionRate }} {{ $saleUnitName }}</td>
                                @else
                                <td>{{ $qty * $conversionRate }} {{ $purchaseUnitName }}</td>
                                <td>{{ $qty }} {{ $saleUnitName }}</td>
                                @endif
                            </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    @endif

    {{-- Combo Items Table --}}
    @if($item->type === 'Combo_Product' && isset($comboItems))
    <div class="card border shadow-sm mt-4">
        <div class="card-header">
            <h6 class="mb-0">{{ __('Combo Items') }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('SN') }}</th>
                            <th>{{ __('Item Name') }} - {{ __('Code') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Total') }}</th>
                            <th>{{ __('Show In Invoice') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($comboItems as $index => $comboItem)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $comboItem['item_name'] }}</td>
                            <td>{{ $comboItem['quantity'] }}</td>
                            <td>{{ formatAmount($comboItem['amount']) }}</td>
                            <td>{{ formatAmount($comboItem['total']) }}</td>
                            <td>
                                @if($comboItem['show_in_invoice'])
                                    <span class="badge bg-label-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge bg-label-secondary">{{ __('No') }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Opening Stock --}}
    @if($item->type !== 'Service_Product' && $item->type !== 'Combo_Product' && $item->type !== 'Variation_Product' && !empty($openingStockData))
    <div class="card border shadow-sm mt-4">
        <div class="card-header">
            <h6 class="mb-0">{{ __('Opening Stock') }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Outlet') }}</th>
                            @if($item->type === 'General_Product' || $item->type === 'Installment_Product')
                            <th>{{ __('Purchase Unit Quantity') }}</th>
                            <th>{{ __('Sale Unit Quantity') }}</th>
                            @elseif($item->type === 'IMEI_Product' || $item->type === 'Serial_Product')
                            <th>{{ $item->type === 'IMEI_Product' ? 'IMEI' : 'Serial' }} {{ __('Numbers') }}</th>
                            @elseif($item->type === 'Medicine_Product')
                            <th>{{ __('Quantity') }} & {{ __('Expiry Date') }}</th>
                            <th>{{ __('Total') }} {{ __('Quantity') }}</th>
                            <th>{{ __('Converted Quantity') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($openingStockData as $outletId => $outletData)
                        <tr>
                            <td>{{ $outlets->where('id', $outletId)->first()->outlet_name ?? 'N/A' }}</td>
                            @if($item->type === 'General_Product' || $item->type === 'Installment_Product')
                            @php
                                $convRate = (int)($item->conversion_rate ?? 1) ?: 1;
                                $qty = (float)($outletData['quantity'] ?? 0);
                                $isDoubleUnit = (int)$item->unit_type === 2;
                                // For double unit, controller already sends purchase qty (e.g. 10) — don't convert; show as-is
                                $displayQty = $isDoubleUnit ? $qty / $convRate : ($convRate > 0 ? $qty / $convRate : $qty);
                                $displayConverted = $isDoubleUnit ? $qty  : $qty;
                                $displayUnit = $item->purchaseUnit->unit_name ?? '—';
                                $displayConvertedUnit = $isDoubleUnit ? ($item->saleUnit->unit_name ?? '—') : ($item->saleUnit->unit_name ?? '—');
                            @endphp
                            <td>{{ $displayQty }} {{ $displayUnit }}</td>
                            <td>{{ $displayConverted }} {{ $displayConvertedUnit }}</td>
                            @elseif($item->type === 'IMEI_Product' || $item->type === 'Serial_Product')
                            <td>
                                @if(!empty($outletData['items']))
                                    @foreach($outletData['items'] as $itemValue)
                                        <span class="badge bg-label-primary me-1 mb-1">{{ is_array($itemValue) ? ($itemValue['value'] ?? $itemValue) : $itemValue }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">{{ __('No items') }}</span>
                                @endif
                            </td>
                            @elseif($item->type === 'Medicine_Product')
                            @php
                                $convRate = (int)($item->conversion_rate ?? 1) ?: 1;
                                $isDoubleUnit = (int)$item->unit_type === 2;
                                $purchaseUnitName = $item->purchaseUnit->unit_name ?? '—';
                                $saleUnitName = $item->saleUnit->unit_name ?? '—';
                                $medicineTotalSale = !empty($outletData['items']) ? collect($outletData['items'])->sum('quantity') : 0;
                                $medicineTotalPurchase = $isDoubleUnit && $convRate > 0 ? $medicineTotalSale / $convRate : $medicineTotalSale;
                            @endphp
                            <td>
                                @if(!empty($outletData['items']))
                                    <div class="mt-1">
                                        @foreach($outletData['items'] as $medicineItem)
                                            @php
                                                $itemQty = (float)($medicineItem['quantity'] ?? 0);
                                                $displayQty = $isDoubleUnit && $convRate > 0 ? $itemQty / $convRate : $itemQty;
                                            @endphp
                                            <span class="badge bg-label-info me-1 mb-1">{{ $displayQty }} - {{ $medicineItem['expiry_date'] ?? 'N/A' }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">{{ __('No items') }}</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($outletData['items']))
                                    {{ $medicineTotalPurchase }} {{ $purchaseUnitName }}
                                @else
                                    <span class="text-muted">{{ __('No items') }}</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($outletData['items']))
                                    {{ $medicineTotalSale }} {{ $saleUnitName }}
                                @else
                                    — —
                                @endif
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Item Image (standalone when no hero photo) - optional duplicate for print; hero already shows image --}}
    @if($item->photo)
    <div class="card border shadow-sm mt-4">
        <div class="card-header">
            <h6 class="mb-0">{{ __('Item Image') }}</h6>
        </div>
        <div class="card-body">
            <img src="{{ asset('uploads/items/' . $item->photo) }}" alt="{{ $item->name }}" class="rounded img-fluid" style="max-height: 280px; object-fit: contain;">
        </div>
    </div>
    @endif
</div>
@endsection
@push('page-js')
@endpush
