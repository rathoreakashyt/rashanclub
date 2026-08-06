@extends('backend.backend_layout')
@section('page-title', isset($printer) ? __('Update') . ' ' . __('Printer') : __('Add') . ' ' . __('Printer') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($printer) ? __('Update') . ' ' . __('Printer') : __('Add') . ' ' . __('Printer') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => '#'
                ],
                [
                    'label' => __('Item') . ' ' . __('Configuration'), 
                    'link' => '#'
                ],
                [
                    'label' => isset($printer) ? __('Update') . ' ' . __('Printer') : __('Add') . ' ' . __('Printer'),
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
            <form action="{{ isset($printer) ? route('printer.update', encrypt($printer->id)) : route('printer.store') }}" method="POST">
                @csrf
                @if(isset($printer))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="title">{{ __('Title') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                        placeholder="{{ __('Title') }}" name="title" id="title" 
                                        value="{{ old('title', isset($printer) ? $printer->title : '') }}" />
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="clear-fix"></div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="invoice_print">{{ __('Printing Choice') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('invoice_print') is-invalid @enderror" name="invoice_print" id="invoice_print" data-placeholder="{{ __('Select') }} {{ __('Printing Choice') }}">
                                        <option value="">{{ __('Select') }} {{ __('Printing Choice') }}</option>
                                        <option value="web_browser" {{ old('invoice_print', isset($printer) ? $printer->invoice_print : '') == 'web_browser' ? 'selected' : '' }}>{{ __('Web Browser') }}</option>
                                        <option value="live_server_print" {{ old('invoice_print', isset($printer) ? $printer->invoice_print : '') == 'live_server_print' ? 'selected' : '' }}>{{ __('Direct Print') }}</option>
                                    </select>
                                    @error('invoice_print')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 live-server-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="type">{{ __('Printer') }} {{ __('Type') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('type') is-invalid @enderror" name="type" id="type" data-placeholder="{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}">
                                        <option value="">{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}</option>
                                        <option value="windows" {{ old('type', isset($printer) ? $printer->type : '') == 'windows' ? 'selected' : '' }}>{{ __('USB Printer') }}</option>
                                        <option value="network" {{ old('type', isset($printer) ? $printer->type : '') == 'network' ? 'selected' : '' }}>{{ __('Network') }}</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="clear-fix"></div>

                            <div class="col-12 col-md-6 col-lg-4 network-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="printer_ip_address">{{ __('Printer IP Address') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('printer_ip_address') is-invalid @enderror" 
                                        placeholder="{{ __('Printer IP Address') }}" name="printer_ip_address" id="printer_ip_address" 
                                        value="{{ old('printer_ip_address', isset($printer) ? $printer->printer_ip_address : '') }}" />
                                    @error('printer_ip_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 network-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="printer_port">{{ __('Printer Port Address') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('printer_port') is-invalid @enderror" 
                                        placeholder="{{ __('Printer Port Address') }}" name="printer_port" id="printer_port" 
                                        value="{{ old('printer_port', isset($printer) ? $printer->printer_port : '') }}" />
                                    @error('printer_port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 live-server-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="characters_per_line">{{ __('Characters Per Line') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('characters_per_line') is-invalid @enderror" 
                                        placeholder="{{ __('Characters Per Line') }}" name="characters_per_line" id="characters_per_line" 
                                        value="{{ old('characters_per_line', isset($printer) ? $printer->characters_per_line : '') }}" />
                                    @error('characters_per_line')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 live-server-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="path">{{ __('Share Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('path') is-invalid @enderror" 
                                        placeholder="{{ __('Share Name') }}" name="path" id="path" 
                                        value="{{ old('path', isset($printer) ? $printer->path : '') }}" />
                                    @error('path')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 live-server-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="print_server_url_invoice">{{ __('IPV4 Address') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('print_server_url_invoice') is-invalid @enderror" 
                                        placeholder="{{ __('IPV4 Address') }}" name="print_server_url_invoice" id="print_server_url_invoice" 
                                        value="{{ old('print_server_url_invoice', isset($printer) ? $printer->print_server_url_invoice : '') }}" />
                                    @error('print_server_url_invoice')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 network-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="fiscal_printer_status">{{ __('Fiscal Printer Status') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('fiscal_printer_status') is-invalid @enderror" name="fiscal_printer_status" id="fiscal_printer_status" data-placeholder="{{ __('Select') }} {{ __('Fiscal Printer Status') }}">
                                        <option value="">{{ __('Select') }} {{ __('Fiscal Printer Status') }}</option>
                                        <option value="ON" {{ (old('fiscal_printer_status') == 'ON' || (isset($printer) && $printer->fiscal_printer_status == 'ON')) ? 'selected' : '' }}>{{ __('ON') }}</option>
                                        <option value="OFF" {{ (old('fiscal_printer_status') == 'OFF' || (isset($printer) && $printer->fiscal_printer_status == 'OFF')) ? 'selected' : '' }}>{{ __('OFF') }}</option>
                                    </select>
                                    @error('fiscal_printer_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4 network-fields d-none">
                                <div class="mb-5">
                                    <label class="form-label" for="open_cash_drawer_when_printing_invoice">{{ __('Open Cash Drawer') }} {!! requiredField() !!}</label>
                                    <select class="form-select select2 @error('open_cash_drawer_when_printing_invoice') is-invalid @enderror" name="open_cash_drawer_when_printing_invoice" id="open_cash_drawer_when_printing_invoice" data-placeholder="{{ __('Select') }} {{ __('Open Cash Drawer') }}">
                                        <option value="">{{ __('Select') }} {{ __('Open Cash Drawer') }}</option>
                                        <option value="ON" {{ (old('open_cash_drawer_when_printing_invoice') == 'ON' || (isset($printer) && $printer->open_cash_drawer_when_printing_invoice == 'ON')) ? 'selected' : '' }}>{{ __('ON') }}</option>
                                        <option value="OFF" {{ (old('open_cash_drawer_when_printing_invoice') == 'OFF' || (isset($printer) && $printer->open_cash_drawer_when_printing_invoice == 'OFF')) ? 'selected' : '' }}>{{ __('OFF') }}</option>
                                    </select>
                                    @error('open_cash_drawer_when_printing_invoice')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($printer) ? $printer : '') !!}
                            </button>
                            <a href="{{ route('printer.index') }}" class="btn btn-primary waves-effect waves-light">
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
<script src="{{ asset('backend_assets/js/pages_js/add_printer.js') }}"></script>
@endpush

