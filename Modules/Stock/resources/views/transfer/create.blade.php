@extends('backend.backend_layout')
@section('page-title', isset($transfer) ? __('Edit') . ' ' . __('Transfer') : __('Add') . ' ' . __('Transfer'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/css/transfer_modern.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Hero Header -->
    <div class="tf-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="tf-hero-icon"><i class="ti tabler-transfer-out"></i></div>
                <div>
                    <h4 class="tf-hero-title">{{ isset($transfer) ? __('Edit') . ' ' . __('Transfer') : __('Add') . ' ' . __('Transfer') }}</h4>
                    <span class="tf-hero-sub">{{ __('Transfer stock between your outlets') }}</span>
                </div>
            </div>
            @include('backend.components.breadcrumb', [
                'breadcrumbs' => [
                    [
                        'label' => '<i class="ti tabler-home"></i>',
                        'link' => '#'
                    ],
                    [
                        'label' => __('Transfer'),
                        'link' => '#'
                    ],
                    [
                        'label' => isset($transfer) ? __('Edit') . ' ' . __('Transfer') : __('Add') . ' ' . __('Transfer'),
                        'active' => true
                    ]
                ]
            ])
        </div>
    </div>

    <form action="{{ isset($transfer) ? route('transfer.update', $transfer->encrypted_id) : route('transfer.store') }}" method="POST" id="transferForm" enctype="multipart/form-data">
        @csrf
        @if(isset($transfer))
            @method('PUT')
        @endif

        <!-- ═══════════ Section 01: Route & Details ═══════════ -->
        <div class="card tf-card mb-6">
            <div class="card-header">
                <h5 class="tf-section-title">
                    <span class="tf-section-num">1</span>
                    {{ __('Route & Details') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="tf-banner mb-5">
                    <i class="ti tabler-alert-circle"></i>
                    <p class="mb-0">{{ __('Please select from outlet and to outlet first then add items in the cart') }}.</p>
                </div>

                <!-- Outlet Route -->
                <div class="tf-route mb-5">
                    <div class="row g-4 align-items-stretch">
                        <div class="col-12 col-md-5">
                            <div class="tf-route-box">
                                <div class="tf-route-label tf-route-from">
                                    <i class="ti tabler-building-store"></i>
                                    {{ __('From Outlet') }} {!! requiredField() !!}
                                </div>
                                <select id="from_outlet_id" name="from_outlet_id" class="select2 form-select" data-placeholder="{{ __('Select From Outlet') }}">
                                    <option value="">{{ __('Select From Outlet') }}</option>
                                    @foreach($outlets ?? [] as $outlet)
                                        <option value="{{ $outlet->id }}" {{ (isset($transfer) && $transfer->from_outlet_id == $outlet->id) ? 'selected' : '' }}>{{ $outlet->outlet_name }} ({{ $outlet->outlet_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-2 d-none d-md-block">
                            <div class="tf-route-arrow">
                                <i class="ti tabler-arrow-right"></i>
                            </div>
                        </div>
                        <div class="col-12 col-md-5">
                            <div class="tf-route-box">
                                <div class="tf-route-label tf-route-to">
                                    <i class="ti tabler-building-store"></i>
                                    {{ __('To Outlet') }} {!! requiredField() !!}
                                </div>
                                <select id="to_outlet_id" name="to_outlet_id" class="select2 form-select" data-placeholder="{{ __('Select To Outlet') }}">
                                    <option value="">{{ __('Select To Outlet') }}</option>
                                    @foreach($outlets ?? [] as $outlet)
                                        <option value="{{ $outlet->id }}" {{ (isset($transfer) && $transfer->to_outlet_id == $outlet->id) ? 'selected' : '' }}>{{ $outlet->outlet_name }} ({{ $outlet->outlet_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ref / Date / Status -->
                <div class="row g-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="validate_wrapper">
                            <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                            <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ isset($transfer) ? $transfer->reference_no : ($reference_no ?? '') }}" readonly />
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="validate_wrapper">
                            <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                            <input type="text" class="form-control datePicker" placeholder="{{ __('Date') }}" name="date" id="date" value="{{ isset($transfer) ? $transfer->date->format('Y-m-d') : date('Y-m-d') }}" />
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="validate_wrapper">
                            <label class="form-label" for="status">
                                <span>{{ __('Status') }} {!! requiredField() !!}</span>
                            </label>
                            <select id="status" name="status" class="form-select select2">
                                <option value="Draft" {{ (isset($transfer) && $transfer->status == 'Draft') ? 'selected' : '' }}>{{ __('Draft') }}</option>
                                <option value="Sent" {{ (isset($transfer) && $transfer->status == 'Sent') ? 'selected' : '' }}>{{ __('Sent') }}</option>
                                <option value="Received" {{ (isset($transfer) && $transfer->status == 'Received') ? 'selected' : '' }}>{{ __('Received') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════ Section 02: Add Items ═══════════ -->
        <div class="card tf-card mb-6">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="tf-section-title">
                    <span class="tf-section-num">2</span>
                    {{ __('Add Items') }}
                    <span class="tf-item-count-badge" id="totalItemCount">0</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="col-12 col-md-6 col-lg-4 mb-5">
                    <label class="form-label">{{ __('Items') }}</label>
                    <select id="quickItemSelect" class="form-select select2">
                        <option value="">{{ __('Select Item') }}</option>
                        @foreach($items ?? [] as $item)
                            <option value="{{ $item->id }}"
                                data-item-name="{{ $item->name }} ({{ $item->code }})"
                                data-item-parent-name="{{ $item->name }}"
                                data-item-type="{{ $item->type }}"
                                data-expiry-date-maintain="{{ $item->expiry_date_maintain ?? 'Yes' }}"
                                data-brand-name="{{ $item->brand ? $item->brand->name : '' }}"
                                data-sale-unit-name="{{ $item->saleUnit ? $item->saleUnit->unit_name : '' }}"
                                data-purchase-unit-name="{{ $item->purchaseUnit ? $item->purchaseUnit->unit_name : '' }}">
                                {{ $item->name }} ({{ $item->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table tf-items-table">
                        <thead>
                            <tr>
                                <th width="60">{{ __('SN') }}</th>
                                <th>{{ __('Item') }}-{{ __('Code') }}-{{ __('Brand') }}</th>
                                <th>{{ __('IMEI/Serial/Medicine') }}</th>
                                <th width="160">{{ __('Quantity') }}</th>
                                <th width="60">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="transferItems">
                            @if(isset($transfer) && $transfer->transferDetails)
                                @foreach($transfer->transferDetails as $detail)
                                <tr>
                                    <td><span class="tf-sn">{{ $loop->iteration }}</span></td>
                                    <td>
                                        <input type="hidden" class="item-id" name="items[]" value="{{ $detail->item->id }}">
                                        <input type="hidden" class="parent-id" name="parent_ids[]" value="{{ $detail->item->parent_id ?? '' }}">
                                        <input type="hidden" class="item-type" name="item_types[]" value="{{ $detail->item->type }}">
                                        <span class="tf-item-name item-name">
                                            @if($detail->item->parent_id && $detail->item->parent)
                                                {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                            @else
                                                {{ $detail->item->name }}
                                            @endif
                                            @if($detail->item->code)
                                                ({{ $detail->item->code }})
                                            @endif
                                            @if($detail->item->brand_id && $detail->item->brand)
                                                - {{ $detail->item->brand->name }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="imei-serial-cell">
                                        <div class="imei-serial-container">
                                            @if($detail->item->type == 'IMEI_Product' || $detail->item->type == 'Serial_Product' || $detail->item->type == 'Medicine_Product')
                                                <input type="text" class="form-control imei-serial" name="expiry_imei_serial[]" placeholder="{{ __('IMEI/Serial/Medicine') }}" readonly value="{{ $detail->expiry_imei_serial ?? '' }}">
                                            @else
                                                <input type="text" class="form-control imei-serial" name="expiry_imei_serial[]" placeholder="{{ __('IMEI/Serial/Medicine') }}" readonly style="display:none;">
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <input type="text" class="form-control number-input quantity" name="quantity_amount[]" min="1" value="{{ $detail->quantity_amount ?? $detail->quantity ?? 1 }}" {{ ($detail->item->type == 'IMEI_Product' || $detail->item->type == 'Serial_Product') ? 'readonly' : '' }}>
                                            <button type="button" class="btn btn-outline-secondary" type="button">
                                                {{ $detail->item->saleUnit ? $detail->item->saleUnit->unit_name : '' }}
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn tf-row-remove remove-row">
                                            <i class="icon-base ti tabler-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══════════ Section 03: Notes ═══════════ -->
        <div class="card tf-card mb-6">
            <div class="card-header">
                <h5 class="tf-section-title">
                    <span class="tf-section-num">3</span>
                    {{ __('Notes') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <div class="tf-note-box">
                            <label class="form-label" for="note_for_sender"><i class="ti tabler-send"></i>{{ __('Note for Sender') }}</label>
                            <textarea class="form-control" id="note_for_sender" name="note_for_sender" rows="3" placeholder="{{ __('Enter Note') }}">{{ isset($transfer) ? $transfer->note_for_sender : '' }}</textarea>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="tf-note-box">
                            <label class="form-label" for="note_for_receiver"><i class="ti tabler-receipt"></i>{{ __('Note for Receiver') }}</label>
                            <textarea class="form-control" id="note_for_receiver" name="note_for_receiver" rows="3" placeholder="{{ __('Enter Note') }}">{{ isset($transfer) ? $transfer->note_for_receiver : '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Footer -->
        <div class="tf-sticky-footer">
            <a href="{{ route('transfer.index') }}" class="tf-btn-back">
                {!! backIconWithText() !!}
            </a>
            <button type="submit" class="tf-btn-submit">
                {!! submitIconWithText(isset($transfer) ? $transfer : '') !!}
            </button>
        </div>
    </form>

    <!-- IMEI/Serial/Medicine Modal -->
    <div class="modal fade" id="modal_imei_serial" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header tf-modal-header pb-2">
                    <h5 class="modal-title" id="modal_imei_serial_title"><i class="ti tabler-package"></i> {{ __('Add Item Details') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- IMEI/Serial Product Section -->
                    <div id="imei_serial_section" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label" id="imei_serial_label">{{ __('IMEI/Serial/Medicine') }}</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control transfer-imei-serial-input" id="transfer_imei_serial_input" placeholder="{{ __('IMEI/Serial/Medicine') }}" autocomplete="off">
                                <button type="button" class="btn btn-outline-primary add-transfer-imei-serial-field">
                                    <i class="ti tabler-plus"></i>
                                </button>
                            </div>
                            <div class="transfer-imei-serial-list" id="transfer_imei_serial_list"></div>
                        </div>
                    </div>

                    <!-- Medicine Product Section -->
                    <div id="medicine_section" style="display: none;">
                        <div class="mb-3">
                            <div class="d-flex gap-2">
                                <div class="d-flex justify-content-between gap-2 w-100">
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('Quantity') }}</label>
                                        <input type="text"
                                            class="form-control number-input transfer-medicine-quantity-input"
                                            id="transfer_medicine_quantity_input"
                                            placeholder="{{ __('Quantity') }}"
                                            min="1"
                                            autocomplete="off" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">MM/YY</label>
                                        <input type="text"
                                            class="form-control transfer-medicine-mmyy-input"
                                            id="transfer_medicine_mmyy_input"
                                            placeholder="MM/YY"
                                            maxlength="5"
                                            autocomplete="off" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('Expiry Date') }}</label>
                                        <input type="text"
                                            class="form-control transfer-medicine-expiry-input datePicker"
                                            id="transfer_medicine_expiry_input"
                                            placeholder="{{ __('Expiry Date') }}"
                                            autocomplete="off" />
                                    </div>
                                </div>
                                <div class="mb-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-primary add-transfer-medicine-field">
                                        <i class="ti tabler-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="transfer-medicine-list" id="transfer_medicine_list"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="save_imei_serial">{{ __('Submit') }}</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
    <!-- IMEI/Serial/Medicine Modal End -->
</div>
@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('backend_assets/js/extended-ui-sweetalert2.js') }}"></script>
<!-- Pass data to JavaScript -->
<input type="hidden" id="variation_child_items_url" value="{{ route('transfer.variation-child-items') }}">
<input type="hidden" id="transfer_item_stock_at_outlet_url" value="{{ route('transfer.item-stock-at-outlet') }}">
<input type="hidden" id="transfer_check_imei_in_outlet_url" value="{{ route('transfer.check-imei-in-outlet') }}">
<script src="{{ asset('backend_assets/js/pages_js/add_transfer.js') }}"></script>
@endpush
