@extends('backend.backend_layout')
@section('page-title', isset($promotion) ? __('Update') . ' ' . __('Promotion') : __('Add') . ' ' . __('Promotion') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($promotion) ? __('Update') . ' ' . __('Promotion') : __('Add') . ' ' . __('Promotion') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Sale'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($promotion) ? __('Update') . ' ' . __('Promotion') : __('Add') . ' ' . __('Promotion'),
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

    <div class="row">
        <div class="col-12">
            <form action="{{ isset($promotion) ? route('promotion.update', encrypt($promotion->id)) : route('promotion.store') }}" method="POST">
                @csrf
                @if(isset($promotion))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="type">{{ __('Type') }} {!! requiredField() !!}</label>
                                    @php $oldType = old('type', isset($promotion) ? $promotion->type : ''); @endphp
                                    <select class="form-select select2 form-select @error('type') is-invalid @enderror" name="type" id="type" data-placeholder="{{ __('Select') }} {{ __('Type') }}" {{ isset($promotion) ? 'disabled' : ''}}>
                                        <option value="">{{ __('Select') }} {{ __('Type') }}</option>
                                        <option value="1" {{ (string)$oldType === '1' ? 'selected' : '' }}>{{ __('Discount') }}</option>
                                        <option value="2" {{ (string)$oldType === '2' ? 'selected' : '' }}>{{ __('Coupon_Discount') }} ({{ __('on_entire_sale') }})</option>
                                        <option value="3" {{ (string)$oldType === '3' ? 'selected' : '' }}>{{ __('Free_Item') }}</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                    <!-- in edit mode pass type in hidden field -->
                                    @if(isset($promotion))
                                        <input type="hidden" name="type" id="type" value="{{ $promotion->type }}">
                                    @endif

                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="title">{{ __('Title') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                        placeholder="{{ __('Title') }}" name="title" id="title" 
                                        value="{{ old('title', isset($promotion) ? $promotion->title : '') }}" />
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="start_date">{{ __('Start_Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('start_date') is-invalid @enderror" 
                                        name="start_date" id="start_date" placeholder="{{ __('Start_Date') }}"
                                        value="{{ old('start_date', isset($promotion) ? $promotion->start_date : '') }}" />
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="end_date">{{ __('End_Date') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control datePicker @error('end_date') is-invalid @enderror" 
                                        name="end_date" id="end_date" placeholder="{{ __('End_Date') }}"
                                        value="{{ old('end_date', isset($promotion) ? $promotion->end_date : '') }}" />
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            

                            <div class="col-12 col-md-6 col-lg-4 type-1" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="item_id">{{ __('Item') }} {!! requiredField() !!}</label>
                                    @php $oldItemId = old('item_id', isset($promotion) ? $promotion->item_id : ''); @endphp
                                    <select class="form-select select2 form-select @error('item_id') is-invalid @enderror" name="item_id" id="item_id" data-placeholder="{{ __('Select') }} {{ __('Item') }}">
                                        <option value="">{{ __('Select') }} {{ __('Item') }}</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" {{ (string)$oldItemId === (string)$item->id ? 'selected' : '' }}>{{ $item->display_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('item_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-1 type-2" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="discount">{{ __('Discount') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('discount') is-invalid @enderror" 
                                        placeholder="{{ __('e.g.') }} 10 {{ __('or') }} 10%" name="discount" id="discount" 
                                        value="{{ old('discount', isset($promotion) ? $promotion->discount : '') }}" 
                                        pattern="^\d+(\.\d+)?%?$" 
                                        title="{{ __('Only amount (e.g. 10) or percentage (e.g. 10%)') }}" />
                                    @error('discount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-2" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="coupon_code">{{ __('Coupon_Code') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('coupon_code') is-invalid @enderror" 
                                        placeholder="{{ __('Coupon_Code') }}" name="coupon_code" id="coupon_code" 
                                        value="{{ old('coupon_code', isset($promotion) ? $promotion->coupon_code : '') }}" />
                                    @error('coupon_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-3" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="item_id_2">{{ __('Buy') }} {{ __('Item') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 form-select @error('item_id_2') is-invalid @enderror" name="item_id" id="item_id_2" data-placeholder="{{ __('Select') }} {{ __('Item') }}">
                                        <option value="">{{ __('Select') }} {{ __('Item') }}</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" {{ (string)$oldItemId === (string)$item->id ? 'selected' : '' }}>{{ $item->display_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('item_id_2')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-3" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="qty">{{ __('Buy_Quantity') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('qty') is-invalid @enderror" 
                                        placeholder="{{ __('Buy_Quantity') }}" name="qty" id="qty" 
                                        value="{{ old('qty', isset($promotion) ? $promotion->qty : '') }}" />
                                    @error('qty')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-3" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="get_item_id">{{ __('Get') }} {{ __('Item') }} {!! requiredField() !!}</label>
                                    @php $oldGetItemId = old('get_item_id', isset($promotion) ? $promotion->get_item_id : ''); @endphp
                                    <select class="form-select select2 form-select @error('get_item_id') is-invalid @enderror" name="get_item_id" id="get_item_id" data-placeholder="{{ __('Select') }} {{ __('Item') }}">
                                        <option value="">{{ __('Select') }} {{ __('Item') }}</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" {{ (string)$oldGetItemId === (string)$item->id ? 'selected' : '' }}>{{ $item->display_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('get_item_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-3" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="get_qty">{{ __('Get_Quantity') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control number-input @error('get_qty') is-invalid @enderror" 
                                        placeholder="{{ __('Get_Quantity') }}" name="get_qty" id="get_qty" 
                                        value="{{ old('get_qty', isset($promotion) ? $promotion->get_qty : '') }}" />
                                    @error('get_qty')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="scheme_basis">{{ __('Scheme_Basis') }}</label>
                                    @php $oldBasis = old('scheme_basis', isset($promotion) ? $promotion->scheme_basis : 'item'); @endphp
                                    <select class="form-select select2 form-select @error('scheme_basis') is-invalid @enderror" name="scheme_basis" id="scheme_basis">
                                        <option value="item" {{ $oldBasis === 'item' ? 'selected' : '' }}>{{ __('Item_Wise') }}</option>
                                        <option value="bill" {{ $oldBasis === 'bill' ? 'selected' : '' }}>{{ __('Bill_Wise') }}</option>
                                        <option value="party" {{ $oldBasis === 'party' ? 'selected' : '' }}>{{ __('Party_Wise') }}</option>
                                    </select>
                                    @error('scheme_basis')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="start_time">{{ __('Start_Time') }}</label>
                                    <input type="time" class="form-control @error('start_time') is-invalid @enderror"
                                        name="start_time" id="start_time"
                                        value="{{ old('start_time', isset($promotion) ? $promotion->start_time : '') }}" />
                                    @error('start_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="end_time">{{ __('End_Time') }}</label>
                                    <input type="time" class="form-control @error('end_time') is-invalid @enderror"
                                        name="end_time" id="end_time"
                                        value="{{ old('end_time', isset($promotion) ? $promotion->end_time : '') }}" />
                                    @error('end_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="min_purchase_amount">{{ __('Min_Purchase_Amount') }}</label>
                                    <input type="text" class="form-control number-input @error('min_purchase_amount') is-invalid @enderror"
                                        placeholder="0.00" name="min_purchase_amount" id="min_purchase_amount"
                                        value="{{ old('min_purchase_amount', isset($promotion) ? $promotion->min_purchase_amount : '0') }}" />
                                    @error('min_purchase_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="max_discount_amount">{{ __('Max_Discount_Amount') }}</label>
                                    <input type="text" class="form-control number-input @error('max_discount_amount') is-invalid @enderror"
                                        placeholder="0.00" name="max_discount_amount" id="max_discount_amount"
                                        value="{{ old('max_discount_amount', isset($promotion) ? $promotion->max_discount_amount : '0') }}" />
                                    @error('max_discount_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 type-2" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="applicable_customer_types">{{ __('Applicable_Customer_Types') }}</label>
                                    @php $oldCustTypes = old('applicable_customer_types', isset($promotion) ? (is_array($promotion->applicable_customer_types) ? $promotion->applicable_customer_types : []) : []); @endphp
                                    <select class="form-select select2 form-select @error('applicable_customer_types') is-invalid @enderror" name="applicable_customer_types[]" id="applicable_customer_types" multiple data-placeholder="{{ __('All_Types') }}">
                                        <option value="1" {{ in_array('1', $oldCustTypes) ? 'selected' : '' }}>{{ __('Retail') }}</option>
                                        <option value="2" {{ in_array('2', $oldCustTypes) ? 'selected' : '' }}>{{ __('Wholesale') }}</option>
                                    </select>
                                    @error('applicable_customer_types')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 scheme-bill" style="display: none;">
                                <div class="mb-5">
                                    <label class="form-label" for="bill_level_discount">{{ __('Bill_Level_Discount') }}</label>
                                    <input type="text" class="form-control @error('bill_level_discount') is-invalid @enderror"
                                        placeholder="{{ __('e.g.') }} 50 {{ __('or') }} 10%" name="bill_level_discount" id="bill_level_discount"
                                        value="{{ old('bill_level_discount', isset($promotion) ? $promotion->bill_level_discount : '') }}" />
                                    @error('bill_level_discount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="status">{{ __('Status') }} {!! requiredField() !!}</label>
                                    @php $oldStatus = old('status', isset($promotion) ? $promotion->status : ''); @endphp
                                    <select class="form-select select2 form-select @error('status') is-invalid @enderror" name="status" id="status" data-placeholder="{{ __('Select') }} {{ __('Status') }}">
                                        <option value="1" {{ (string)$oldStatus === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                        <option value="2" {{ (string)$oldStatus === '2' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($promotion) ? $promotion : '') !!}
                            </button>
                            <a href="{{ route('promotion.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/js/pages_js/add_promotion.js')}}"></script>
@endpush

