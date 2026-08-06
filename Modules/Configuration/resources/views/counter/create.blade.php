@extends('backend.backend_layout')
@section('page-title', isset($counter) ? __('Update') . ' ' . __('Counter') : __('Add') . ' ' . __('Counter') )
@push('page-css')
@endpush
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-0">{{ isset($counter) ? __('Update') . ' ' . __('Counter') : __('Add') . ' ' . __('Counter') }}</h4>
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
                    'label' => isset($counter) ? __('Update') . ' ' . __('Counter') : __('Add') . ' ' . __('Counter'),
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
            <form action="{{ isset($counter) ? route('counter.update', encrypt($counter->id)) : route('counter.store') }}" method="POST">
                @csrf
                @if(isset($counter))
                    @method('PUT')
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="name">{{ __('Name') }} {!! requiredField() !!}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                        placeholder="{{ __('Name') }}" name="name" id="name" 
                                        value="{{ old('name', isset($counter) ? $counter->name : '') }}" />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="outlet_id">{{ __('Outlet') }} {!! requiredField() !!}</label>
                                    <select class="select2 form-select @error('outlet_id') is-invalid @enderror" name="outlet_id" id="outlet_id" data-placeholder="{{ __('Select') }} {{ __('Outlet') }}">
                                        <option value="">{{ __('Select') }} {{ __('Outlet') }}</option>
                                        @foreach($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" {{ old('outlet_id', isset($counter) ? $counter->outlet_id : '') == $outlet->id ? 'selected' : '' }}>
                                                {{ $outlet->outlet_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('outlet_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between">
                                    <div class="mb-5 w-100">
                                        <label class="form-label" for="printer_id">{{ __('Printer') }}</label>
                                        <select class="select2 form-select @error('printer_id') is-invalid @enderror" name="printer_id" id="printer_id" data-placeholder="{{ __('Select') }} {{ __('Printer') }}">
                                            <option value="">{{ __('Select') }} {{ __('Printer') }}</option>
                                            @foreach($printers as $printer)
                                                <option value="{{ $printer->id }}" {{ old('printer_id', isset($counter) ? $counter->printer_id : '') == $printer->id ? 'selected' : '' }}>
                                                    {{ $printer->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('printer_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <a type="button" href="javascript:void(0);" class="fw-medium btn btn-icon btn-label-primary ms-4 add-plus-btn" data-bs-toggle="modal"
                                        data-bs-target="#modal_printer">
                                        <i class="icon-base ti tabler-plus icon-md"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="mb-5">
                                    <label class="form-label" for="description">{{ __('Description') }}</label>
                                    <textarea type="text" class="form-control @error('description') is-invalid @enderror" 
                                        placeholder="{{ __('Description') }}" name="description" id="description" 
                                        >{{ old('description', isset($counter) ? $counter->description : '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary waves-effect waves-light me-4">
                                {!! submitIconWithText(isset($counter) ? $counter : '') !!}
                            </button>
                            <a href="{{ route('counter.index') }}" class="btn btn-primary waves-effect waves-light">
                                {!! backIconWithText() !!}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Printer Quick Add -->
<div class="modal fade" id="modal_printer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <form id="printerForm">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Printer') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 col-12">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_title" class="form-label">{{ __('Title') }} {!! requiredField() !!}</label>
                                <input type="text" id="printer_title" name="title" class="form-control" placeholder="{{ __('Enter') }} {{ __('Title') }}" />
                            </div>
                        </div>
                        <div class="clear-fix"></div>
                        <div class="col-lg-4 col-md-6 col-12">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_invoice_print" class="form-label">{{ __('Printing Choice') }} {!! requiredField() !!}</label>
                                <select class="form-select select2" name="invoice_print" id="printer_invoice_print" data-placeholder="{{ __('Select') }} {{ __('Printing Choice') }}">
                                    <option value="">{{ __('Select') }} {{ __('Printing Choice') }}</option>
                                    <option value="web_browser">{{ __('Web Browser') }}</option>
                                    <option value="live_server_print">{{ __('Direct Print') }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Live Server Print Fields -->
                        <div class="col-lg-4 col-md-6 col-12 live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_type" class="form-label">{{ __('Printer') }} {{ __('Type') }} {!! requiredField() !!}</label>
                                <select class="form-select select2" name="type" id="printer_type" data-placeholder="{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}">
                                    <option value="">{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}</option>
                                    <option value="windows">{{ __('USB Printer') }}</option>
                                    <option value="network">{{ __('Network') }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_characters_per_line" class="form-label">{{ __('Characters Per Line') }} {!! requiredField() !!}</label>
                                <input type="text" id="printer_characters_per_line" name="characters_per_line" class="form-control" placeholder="{{ __('Enter') }} {{ __('Characters Per Line') }}" />
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_path" class="form-label">{{ __('Share Name') }} {!! requiredField() !!}</label>
                                <input type="text" id="printer_path" name="path" class="form-control" placeholder="{{ __('Enter') }} {{ __('Share Name') }}" />
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 live-server-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_print_server_url_invoice" class="form-label">{{ __('IPV4 Address') }} {!! requiredField() !!}</label>
                                <input type="text" id="printer_print_server_url_invoice" name="print_server_url_invoice" class="form-control" placeholder="{{ __('Enter') }} {{ __('IPV4 Address') }}" />
                            </div>
                        </div>
                        
                        <!-- Network Fields -->
                        <div class="col-lg-4 col-md-6 col-12 network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_ip_address" class="form-label">{{ __('Printer IP Address') }} {!! requiredField() !!}</label>
                                <input type="text" id="printer_ip_address" name="printer_ip_address" class="form-control" placeholder="{{ __('Enter') }} {{ __('Printer IP Address') }}" />
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_port" class="form-label">{{ __('Printer Port Address') }} {!! requiredField() !!}</label>
                                <input type="text" id="printer_port" name="printer_port" class="form-control" placeholder="{{ __('Enter') }} {{ __('Printer Port Address') }}" />
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-4 network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_fiscal_printer_status" class="form-label">{{ __('Fiscal Printer Status') }} {!! requiredField() !!}</label>
                                <select class="form-select select2" name="fiscal_printer_status" id="printer_fiscal_printer_status" data-placeholder="{{ __('Select') }} {{ __('Fiscal Printer Status') }}">
                                    <option value="">{{ __('Select') }} {{ __('Fiscal Printer Status') }}</option>
                                    <option value="ON">{{ __('ON') }}</option>
                                    <option value="OFF">{{ __('OFF') }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 col-12 mb-4 network-fields d-none">
                            <div class="mb-4 validate_wrapper">
                                <label for="printer_open_cash_drawer" class="form-label">{{ __('Open Cash Drawer') }} {!! requiredField() !!}</label>
                                <select class="form-select select2" name="open_cash_drawer_when_printing_invoice" id="printer_open_cash_drawer" data-placeholder="{{ __('Select') }} {{ __('Open Cash Drawer') }}">
                                    <option value="">{{ __('Select') }} {{ __('Open Cash Drawer') }}</option>
                                    <option value="ON">{{ __('ON') }}</option>
                                    <option value="OFF">{{ __('OFF') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary add_printer">
                        {!! submitIconWithText('') !!}
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        {!! closeIconWithText() !!}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    (function ($) {
        'use strict';

        const printerForm = $('#printerForm');
        const printerModal = $('#modal_printer');
        const printerSelect = $('#printer_id');
        const invoicePrintSelect = $('#printer_invoice_print');
        const printerTypeSelect = $('#printer_type');
        const printerFiscalStatusSelect = $('#printer_fiscal_printer_status');
        const printerOpenCashDrawerSelect = $('#printer_open_cash_drawer');
        const placeholder = "{{ __('Select') }} {{ __('Printer') }}";
        const storeUrl = "{{ route('printer.store') }}";

        // on change printer_type
        printerTypeSelect.on('change', function() {
            toggleNetworkFields();
        });

        // on change printer_invoice_print
        invoicePrintSelect.on('change', function() {
            togglePrinterFields();
        });

        // Function to toggle fields based on invoice print selection
        function togglePrinterFields() {
            const invoicePrint = invoicePrintSelect.val();
            if (invoicePrint === 'live_server_print') {
                printerModal.find('.live-server-fields').removeClass('d-none');
                toggleNetworkFields(); // Check type field as well
            } else {
                printerModal.find('.live-server-fields, .network-fields').addClass('d-none');
            }
        }

        // Function to toggle network-specific fields
        function toggleNetworkFields() {
            const type = printerTypeSelect.val();
            if (type === 'network') {
                printerModal.find('.network-fields').removeClass('d-none');
            } else {
                printerModal.find('.network-fields').addClass('d-none');
            }
        }

        // Initialize select2 for all select fields when modal is shown
        printerModal.on('shown.bs.modal', function () {
            // Initialize invoice_print select2
            if (!invoicePrintSelect.hasClass('select2-hidden-accessible')) {
                invoicePrintSelect.select2({
                    dropdownParent: printerModal,
                    placeholder: "{{ __('Select') }} {{ __('Printing Choice') }}"
                }).on('change', function() {
                    togglePrinterFields();
                });
            }

            // Initialize type select2
            if (!printerTypeSelect.hasClass('select2-hidden-accessible')) {
                printerTypeSelect.select2({
                    dropdownParent: printerModal,
                    placeholder: "{{ __('Select') }} {{ __('Printer') }} {{ __('Type') }}"
                }).on('change', function() {
                    toggleNetworkFields();
                });
            }

            // Initialize fiscal printer status select2
            if (!printerFiscalStatusSelect.hasClass('select2-hidden-accessible')) {
                printerFiscalStatusSelect.select2({
                    dropdownParent: printerModal,
                    placeholder: "{{ __('Select') }} {{ __('Fiscal Printer Status') }}"
                });
            }

            // Initialize open cash drawer select2
            if (!printerOpenCashDrawerSelect.hasClass('select2-hidden-accessible')) {
                printerOpenCashDrawerSelect.select2({
                    dropdownParent: printerModal,
                    placeholder: "{{ __('Select') }} {{ __('Open Cash Drawer') }}"
                });
            }

            // Initial toggle check
            togglePrinterFields();
        });

        // Destroy select2 when modal is hidden
        printerModal.on('hidden.bs.modal', function () {
            if (invoicePrintSelect.hasClass('select2-hidden-accessible')) {
                invoicePrintSelect.off('change').select2('destroy');
            }
            if (printerTypeSelect.hasClass('select2-hidden-accessible')) {
                printerTypeSelect.off('change').select2('destroy');
            }
            if (printerFiscalStatusSelect.hasClass('select2-hidden-accessible')) {
                printerFiscalStatusSelect.select2('destroy');
            }
            if (printerOpenCashDrawerSelect.hasClass('select2-hidden-accessible')) {
                printerOpenCashDrawerSelect.select2('destroy');
            }
            printerForm[0].reset();
            clearValidation();
            // Hide all conditional fields
            printerModal.find('.live-server-fields, .network-fields').addClass('d-none');
        });

        function clearValidation() {
            printerForm.find('.is-invalid').removeClass('is-invalid');
            printerForm.find('.invalid-feedback').remove();
        }

        function refreshPrinterOptions(printers, selectedId = null) {
            printerSelect.empty();
            printerSelect.append(`<option value="">${placeholder}</option>`);

            printers.forEach(function (printer) {
                const option = $('<option></option>').val(printer.id).text(printer.title);
                if (selectedId && Number(printer.id) === Number(selectedId)) {
                    option.prop('selected', true);
                }
                printerSelect.append(option);
            });

            if (printerSelect.hasClass('select2-hidden-accessible')) {
                printerSelect.select2('destroy');
            }

            printerSelect.select2({
                dropdownParent: printerSelect.parent(),
                placeholder: placeholder
            });
        }

        printerForm.on('submit', function (e) {
            e.preventDefault();
            clearValidation();

            $.ajax({
                type: 'POST',
                url: storeUrl,
                data: printerForm.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response && response.status === 'success' && response.data && response.data.printers) {
                        refreshPrinterOptions(response.data.printers, response.data.printer ? response.data.printer.id : null);
                        printerForm[0].reset();
                        printerModal.modal('hide');
                        if (typeof showSuccessNotification === 'function') {
                            showSuccessNotification(response.message || 'Printer added successfully');
                        }
                    } else if (typeof showErrorNotification === 'function') {
                        showErrorNotification(response.message || 'Something went wrong');
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function (field) {
                            const input = printerForm.find('[name="' + field + '"]');
                            // if (input.length) {
                            //     input.addClass('is-invalid');
                            //     input.after('<div class="invalid-feedback">' + errors[field][0] + '</div>');
                            // }
                            let formGroup = input.closest('.validate_wrapper');
                            input.addClass('is-invalid');
                            formGroup.append(`<div class="invalid-feedback">${errors[field][0]}</div>`);
                            $('.invalid-feedback').show()
                        });
                    } else if (typeof showErrorNotification === 'function') {
                        showErrorNotification('An unexpected error occurred');
                    }
                }
            });
        });
    })(jQuery);
</script>
@endpush

