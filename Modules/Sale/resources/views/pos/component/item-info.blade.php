<div class="modal fade" id="modal_item_info" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" id="modal_item_info_dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pos_item_info_modal_title">{{ __('Product') }} {{ __('Information') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pos_item_info_product_id" value="">
                <input type="hidden" id="pos_item_info_product_type" value="">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <img id="pos_item_info_product_image" src="" alt="Product Image" 
                             class="img-fluid rounded item-info-image">

                        <div class="item-info-name-code">
                           <h5 id="pos_item_info_product_name" class="mb-0"></h5>
                            <small class="text-muted" id="pos_item_info_product_code"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stock-wrap d-flex align-items-center gap-2 text-success">
                            <span class="fw-bold">{{ __('Current Stock') }}:</span>
                            <span id="pos_item_info_stock"></span>
                        </div>

                        <!-- Promotion Info Section -->
                        <div class="promotion-info-wrap mt-3 mb-3" id="pos_item_info_promotion_wrap" style="display: none;">
                            <div class="alert alert-info mb-0">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="icon-base ti tabler-tag text-primary"></i>
                                    <div class="flex-grow-1">
                                        <strong id="pos_item_info_promotion_title" class="d-block mb-1"></strong>
                                        <small id="pos_item_info_promotion_details" class="text-muted"></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Type Selection (Radio Buttons) - Hidden for Combo_Product -->
                        <div class="my-3" id="pos_item_info_price_type_wrap">
                            <label class="form-label">{{ __('Select Price Type') }}</label>
                            <div class="form-check">
                                <input class="form-check-input price-type-radio" type="radio" name="pos_item_info_price_type" id="pos_item_info_price_type_purchase" value="purchase">
                                <label class="form-check-label" for="pos_item_info_price_type_purchase">
                                    {{ __('Purchase Price') }}: <span id="pos_item_info_purchase_price_display">0.00</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input price-type-radio" type="radio" name="pos_item_info_price_type" id="pos_item_info_price_type_mrp" value="mrp">
                                <label class="form-check-label" for="pos_item_info_price_type_mrp">
                                    {{ __('MRP Price') }}: <span id="pos_item_info_mrp_price_display">0.00</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input price-type-radio" type="radio" name="pos_item_info_price_type" id="pos_item_info_price_type_sale" value="sale" checked>
                                <label class="form-check-label" for="pos_item_info_price_type_sale">
                                    {{ __('Sale Price') }}: <span id="pos_item_info_sale_price_display">0.00</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input price-type-radio" type="radio" name="pos_item_info_price_type" id="pos_item_info_price_type_whole_sale" value="whole_sale">
                                <label class="form-check-label" for="pos_item_info_price_type_whole_sale">
                                    {{ __('Wholesale Price') }}: <span id="pos_item_info_whole_sale_price_display">0.00</span>
                                </label>
                            </div>
                        </div>

                        <!-- Employee Selection (for Service_Product only) -->
                        <div id="pos_item_info_employee_wrap" style="display: none;">
                            <label class="form-label">{{ __('Assign Service Employee') }}</label>
                            <select class="form-select select2" id="pos_item_info_employee_select">
                                <option value="">{{ __('Select') }} {{ __('Employee') }}</option>
                                @foreach($employees ?? [] as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>
                
                <div class="row">
                    
                    
                    <!-- Variation Selection (for Variation_Product only) -->
                    <div class="col-12 mb-3" id="pos_item_info_variation_wrap" style="display: none;">
                        <label class="form-label">{{ __('Select') }} {{ __('Variation') }} {!! requiredField() !!}</label>
                        <select class="form-select select2" id="pos_item_info_variation_select">
                            <option value="">{{ __('Select') }} {{ __('Variation') }}</option>
                        </select>
                    </div>
                    
                    <!-- IMEI/Serial Selection (for IMEI_Product and Serial_Product) -->
                    <div class="col-12 mb-3" id="pos_item_info_imei_wrap" style="display: none;">
                        <label class="form-label" id="pos_item_info_imei_label">{{ __('Select') }} {{ __('IMEI_Serial') }} {!! requiredField() !!}</label>
                        <select class="form-select" id="pos_item_info_imei_select" multiple="multiple">
                            <option value="">{{ __('Select') }} {{ __('IMEI_Serial') }}</option>
                        </select>
                        <small class="text-muted">{{ __('You can select multiple IMEI/Serial numbers') }}</small>
                    </div>

                    <!-- Medicine Expiry Date Selection (for Medicine_Product with expiry_date_maintain = Yes) -->
                    <div class="col-12 mb-3" id="pos_item_info_medicine_wrap" style="display: none;">
                        <label class="form-label">{{ __('Select') }} {{ __('Expiry Date') }} {!! requiredField() !!}</label>
                        <select class="form-select select2" id="pos_item_info_medicine_expiry_select">
                            <option value="">{{ __('Select') }} {{ __('Expiry Date') }}</option>
                        </select>
                        <small class="text-muted">{{ __('Select an expiry date to add to the list') }}</small>
                        
                        <!-- Table to display selected expiry dates -->
                        <div class="table-responsive mt-3" id="pos_item_info_medicine_table_wrap" style="display: none;">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Expiry Date') }}</th>
                                        <th>{{ __('Current Stock') }}</th>
                                        <th>{{ __('Quantity') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="pos_item_info_medicine_table_body">
                                    <!-- Selected expiry dates will be displayed here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Combo Items Table (for Combo_Product only) -->
                    <div class="col-12 mb-3" id="pos_item_info_combo_wrap" style="display: none;">
                        <label class="form-label mb-3">{{ __('Combo Items') }}</label>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="pos_item_info_combo_table">
                                <thead>
                                    <tr>
                                        <th class="w-10">
                                            <div class="form-check d-flex align-items-center gap-1 mb-0">
                                                <input type="checkbox" 
                                                class="form-check-input"
                                                id="pos_item_info_combo_select_all" title="{{ __('Select All') }}">
                                                <i class="icon-base ti tabler-info-circle" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-placement="top" 
                                                   data-bs-original-title="{{ __('Checked to show the item in invoice') }}"
                                                   style="cursor: help;"></i>
                                            </div>
                                        </th>
                                        <th class="w-25">{{ __('Item Name') }}</th>
                                        <th class="w-10">{{ __('Quantity') }}</th>
                                        <th class="w-15">{{ __('Unit Price') }}</th>
                                        <th class="w-15">{{ __('Total') }}</th>
                                        <th class="w-25">{{ __('Select') }} {{ __('Employee') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="pos_item_info_combo_tbody">
                                    <!-- Combo items will be populated here -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">{{ __('Total Price') }}:</td>
                                        <td class="fw-bold" id="pos_item_info_combo_total_price">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Hidden fields to store actual price values -->
                    <input type="hidden" id="pos_item_info_purchase_price" value="0">
                    <input type="hidden" id="pos_item_info_mrp_price" value="0">
                    <input type="hidden" id="pos_item_info_sale_price" value="0">
                    <input type="hidden" id="pos_item_info_whole_sale_price" value="0">
                    
                    <!-- Price Input Field - Hidden for Combo_Product -->
                    <div class="col-12 col-md-6 mb-3" id="pos_item_info_price_wrap">
                        <label class="form-label">{{ __('Price') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control number-input" id="pos_item_info_price" 
                               class="form-control" placeholder="{{ __('Price') }}" min="0">
                    </div>
                    
                    <div class="col-12 col-md-6 mb-3">
                        <label class="form-label">{{ __('Quantity') }} {!! requiredField() !!}</label>
                        <div class="input-group">
                            <input type="text" class="form-control number-input" id="pos_item_info_quantity" 
                                   class="form-control" placeholder="{{ __('Quantity') }}" min="0.001" step="0.001" value="1">
                            <span class="input-group-text" id="pos_item_info_sale_unit_display">PCS</span>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 mb-3">
                        <label class="form-label">{{ __('Discount') }}</label>
                        <div class="input-group">
                            <select class="form-select" id="pos_item_info_discount_type" style="max-width: 100px;">
                                <option value="fixed">$</option>
                                <option value="percentage">%</option>
                            </select>
                            <input type="text" id="pos_item_info_discount" 
                                   class="form-control" placeholder="0" value="0" min="0">
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 mb-3">
                        <label class="form-label">{{ __('Total') }}</label>
                        <input type="text" id="pos_item_info_total" 
                               class="form-control" placeholder="0.00" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="pos_item_info_add_to_cart_btn">
                    <i class="icon-base ti tabler-shopping-cart me-1"></i> {{ __('Add to Cart') }}
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    {!! closeIconWithText() !!}
                </button>
            </div>
        </div>
    </div>
</div>