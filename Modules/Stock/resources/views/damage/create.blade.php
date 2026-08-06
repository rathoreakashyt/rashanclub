@extends('backend.backend_layout')
@section('page-title', isset($damage) ? __('Edit') . ' ' . __('Damage') : __('Add') . ' ' . __('Damage'))
@push('page-css')
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<style>
    #damageItems tr td {
        vertical-align: top;
    }
</style>
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Add Damage -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($damage) ? __('Update') . ' ' . __('Damage') : __('Add') . ' ' . __('Damage') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Damage'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($damage) ? __('Update') . ' ' . __('Damage') : __('Add') . ' ' . __('Damage'),
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

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <form action="{{ isset($damage) ? route('damage.update', $damage->encrypted_id) : route('damage.store') }}" method="POST" id="damageForm" enctype="multipart/form-data">
                    @csrf
                    @if(isset($damage))
                        @method('PUT')
                    @endif
                
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="reference_no">{{ __('Reference No') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control" placeholder="{{ __('Reference No') }}" name="reference_no" id="reference_no" value="{{ isset($damage) ? $damage->reference_no : ($reference_no ?? '') }}" readonly />
                                    <div class="invalid-feedback d-block field-error" id="reference_no_error" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="date">{{ __('Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker" placeholder="{{ __('Date') }}" name="date" id="date" value="{{ isset($damage) ? $damage->date->format('Y-m-d') : date('Y-m-d') }}" />
                                    <div class="invalid-feedback d-block field-error" id="date_error" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label" for="employee_id">
                                        <span>{{ __('Employee') }} {!! requiredField() !!}</span>
                                    </label>
                                    <select id="employee_id" name="employee_id" class="select2 form-select" data-placeholder="{{ __('Select Employee') }}">
                                        <option value="">{{ __('Select Employee') }}</option>
                                        @foreach($employees ?? $users ?? [] as $user)
                                            <option value="{{ $user->id }}" {{ (isset($damage) && $damage->employee_id == $user->id) ? 'selected' : '' }}>{{ $user->name }} {{ $user->phone ? '(' . $user->phone . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback d-block field-error" id="employee_id_error" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="clear-fix"></div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5 validate_wrapper">
                                    <label class="form-label">{{ __('Items') }}</label>
                                    <select id="quickItemSelect" class="form-select select2">
                                        <option value="">{{ __('Select Item') }}</option>
                                        @foreach($items ?? [] as $item)
                                            <option value="{{ $item->id }}" 
                                                data-sale-price="{{ $item->sale_price }}"
                                                data-mrp-price="{{ $item->mrp_price }}"
                                                data-purchase-price="{{ $item->purchase_price }}"
                                                data-purchase-unit="{{ $item->purchase_unit_id }}"
                                                data-sale-unit="{{ $item->sale_unit_id }}"
                                                data-item-type="{{ $item->type }}"
                                                data-expiry-date-maintain="{{ $item->expiry_date_maintain ?? 'Yes' }}"
                                                data-purchase-unit-name="{{ $item->purchaseUnit->unit_name ?? '' }}"
                                                data-sale-unit-name="{{ $item->saleUnit->unit_name ?? '' }}"
                                                data-item-name="{{ $item->name }} ({{ $item->code }})"
                                                data-item-parent-name="{{ $item->name }}">
                                                {{ $item->name }} ({{ $item->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>{{ __('SN') }}</th>
                                                <th>{{ __('Item Name') }}</th>
                                                <th>{{ __('IMEI/Serial/Medicine') }}</th>
                                                <th>{{ __('Damage Qty') }}</th>
                                                <th>{{ __('Damage Amount') }}</th>
                                                <th>{{ __('Total') }}</th>
                                                <th>{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="damageItems">
                                            @if(isset($damage) && $damage->damageDetails)
                                                @foreach($damage->damageDetails as $detail)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        <input type="hidden" class="item-id" name="items[]" value="{{ $detail->item->id }}">
                                                        <input type="hidden" class="parent-id" name="parent_ids[]" value="{{ $detail->item->parent_id ?? '' }}">
                                                        <input type="hidden" class="item-type" name="item_types[]" value="{{ $detail->item_type ?? $detail->item->type }}">
                                                        <span class="item-name">
                                                            @if($detail->item->parent_id && $detail->item->parent)
                                                                {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                                            @else
                                                                {{ $detail->item->name }}
                                                            @endif
                                                            ({{ $detail->item->code }})
                                                        </span>
                                                    </td>
                                                    <td class="imei-serial-cell">
                                                        <div class="imei-serial-container">
                                                            <input type="text" class="form-control imei-serial" name="expiry_imei_serial[]" placeholder="{{ __('IMEI/Serial/Medicine') }}" readonly value="{{ $detail->expiry_imei_serial ?? '' }}" style="{{ ($detail->item_type ?? $detail->item->type) && in_array($detail->item_type ?? $detail->item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product']) ? '' : 'display:none;' }}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="input-group ">
                                                            <input type="text" class="form-control number-input damage-qty" name="damage_quantity[]" value="{{ $detail->damage_quantity ?? 0 }}">
                                                            <button type="button" class="btn btn-outline-secondary" type="button">
                                                                {{ $detail->item->saleUnit->unit_name ?? '' }}
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control number-input damage-amount" name="last_purchase_price[]" value="{{ $detail->last_purchase_price ?? 0 }}">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control number-input total" name="total_amount[]" readonly value="{{ $detail->total_amount ?? 0 }}">
                                                        <input type="hidden" class="loss-amount" name="loss_amount[]" value="{{ $detail->loss_amount ?? ($detail->total_amount ?? 0) }}">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn text-danger remove-row">
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
                    </div>

                    <!-- Summary Section -->
                    <div class="card-body">
                        <div class="row justify-content-end">
                            <div class="col-12 col-md-6 col-lg-4">
                                <h5 class="mb-4">{{ __('Total Item') }} <span id="totalItemCount">0</span></h5>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Total Loss Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="totalLoss">{{ __('Total Loss') }}</label>
                                    <input type="text" class="form-control" id="totalLoss" name="total_loss" readonly>
                                </div>
                            </div>
                            <div class="clear-fix"></div>
                            
                            <!-- Note Field -->
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="note">{{ __('Note') }}</label>
                                    <textarea class="form-control" id="note" name="note" rows="3" placeholder="{{ __('Enter Note') }}">{{ isset($damage) ? $damage->note : '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($damage) ? $damage : '') !!}
                            </button>
                            <a href="{{ route('damage.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- IMEI/Serial/Medicine Modal -->
<div class="modal fade" id="modal_imei_serial" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header pb-2">
                <h5 class="modal-title" id="modal_imei_serial_title">{{ __('Add Item Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Damage Amount') }} {!! requiredField() !!}</label>
                    <input type="text" class="form-control input-number" id="modal_damage_amount" required>
                </div>
                
                <!-- IMEI/Serial Product Section -->
                <div id="imei_serial_section" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label" id="imei_serial_label">{{ __('IMEI/Serial/Medicine') }}</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control damage-imei-serial-input" id="damage_imei_serial_input" placeholder="{{ __('IMEI/Serial/Medicine') }}" autocomplete="off">
                            <button type="button" class="btn btn-outline-primary add-damage-imei-serial-field">
                                <i class="ti tabler-plus"></i>
                            </button>
                        </div>
                        <div class="damage-imei-serial-list" id="damage_imei_serial_list"></div>
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
                                        class="form-control number-input damage-medicine-quantity-input" 
                                        id="damage_medicine_quantity_input"
                                        placeholder="{{ __('Quantity') }}"
                                        min="1"
                                        autocomplete="off" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">MM/YY</label>
                                    <input type="text" 
                                        class="form-control damage-medicine-mmyy-input" 
                                        id="damage_medicine_mmyy_input"
                                        placeholder="MM/YY"
                                        maxlength="5"
                                        autocomplete="off" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Expiry Date') }}</label>
                                    <input type="text" 
                                        class="form-control damage-medicine-expiry-input datePicker" 
                                        id="damage_medicine_expiry_input"
                                        placeholder="{{ __('Expiry Date') }}"
                                        autocomplete="off" />
                                </div>
                            </div>
                            <div class="mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-primary add-damage-medicine-field">
                                    <i class="ti tabler-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="damage-medicine-list" id="damage_medicine_list"></div>
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
@endsection



@push('page-js')
@routes
<script src="{{ asset('backend_assets/vendor/libs/dropzone/dropzone.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<!-- Pass data to JavaScript -->
<input type="hidden" id="variation_child_items_url" value="{{ route('damage.variation-child-items') }}">
<input type="hidden" id="item_current_stock_url" value="{{ route('damage.item-current-stock') }}">
<input type="hidden" id="damage_check_imei_in_outlet_url" value="{{ route('damage.check-imei-in-outlet') }}">
<input type="hidden" id="outlet_id" value="{{ session('outlet.outlet_id', '') }}">
<script src="{{ asset('backend_assets/js/pages_js/add_damage.js') }}"></script>
@endpush

