<div class="tab-pane fade {{ request()->route('tab') == 'tax_setting' ? 'active show' : '' }}" id="tax_setting" role="tabpanel">
    <form id="tax_setting_form">
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title m-0">{{ __('Tax Setting') }}</h5>
            </div>
            <div class="card-body">
                <div class="row mb-6 g-6">
                    <div class="col-12 col-md-6 validate_wrapper">
                        <label class="form-label mb-1">{{ __('Collect Tax') }} {!! requiredField() !!}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="collect_tax" id="collect_tax_yes" value="Yes" {{ $company->collect_tax == 'Yes' ? 'checked' : '' }}>
                            <label class="form-check-label" for="collect_tax_yes">{{ __('Yes') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="collect_tax" id="collect_tax_no" value="No" {{ $company->collect_tax == 'No' ? 'checked' : '' }}>
                            <label class="form-check-label" for="collect_tax_no">{{ __('No') }}</label>
                        </div>
                    </div>
                    <div class="clear-fix"></div>

                    <div class="col-12 col-md-6 tax-field validate_wrapper" style="{{ $company->collect_tax == 'No' ? 'display: none;' : '' }}">
                        <label class="form-label mb-1" for="tax_title">{{ __('Tax Title') }}</label>
                        <input type="text" class="form-control" id="tax_title" name="tax_title" placeholder="{{ __('Enter') }} {{ __('Tax Title') }}" value="{{ $company->tax_title }}">
                    </div>

                    <div class="col-12 col-md-6 tax-field validate_wrapper" style="{{ $company->collect_tax == 'No' ? 'display: none;' : '' }}">
                        <label class="form-label mb-1" for="tax_registration_no">{{ __('Tax Registration No') }}</label>
                        <input type="text" class="form-control" id="tax_registration_no" name="tax_registration_no" placeholder="{{ __('Enter') }} {{ __('Tax Registration No') }}" value="{{ $company->tax_registration_no }}">
                    </div>

                    <div class="col-12 col-md-6 tax-field validate_wrapper" style="{{ $company->collect_tax == 'No' ? 'display: none;' : '' }}">
                        <label class="form-label mb-1">{{ __('Enable GST') }} {!! requiredField() !!}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tax_is_gst" id="tax_is_gst_yes" value="Yes" {{ ($company->tax_is_gst ?? 'No') == 'Yes' ? 'checked' : '' }}>
                            <label class="form-check-label" for="tax_is_gst_yes">{{ __('Yes') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tax_is_gst" id="tax_is_gst_no" value="No" {{ ($company->tax_is_gst ?? 'No') == 'No' ? 'checked' : '' }}>
                            <label class="form-check-label" for="tax_is_gst_no">{{ __('No') }}</label>
                        </div>
                    </div>
                    <div class="clear-fix"></div>

                    {{-- Tax List Section --}}
                    <div class="col-12 tax-field" style="{{ $company->collect_tax == 'No' ? 'display: none;' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label mb-0">{{ __('Tax List') }}</label>
                            <button type="button" class="btn btn-primary btn-sm" id="add_tax_btn" data-bs-toggle="modal" data-bs-target="#modal_add_tax">
                                <i class="ti tabler-plus me-1"></i>{{ __('Add Tax') }}
                            </button>
                        </div>

                        <!-- Update Button -->
                        @if(!empty($company->tax_setting))
                        <div class="mb-3">
                            <button type="button" class="btn btn-warning btn-sm" id="tax_setting_migrate_btn" title="{{ __('Migrate legacy tax_setting to taxs table and update items, sales, sale_details references') }}">
                                <i class="ti tabler-arrow-right me-1"></i>{{ __('Migrate Tax Setting') }}
                            </button>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered" id="tax_table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Tax Name') }}</th>
                                        <th>{{ __('Tax Rate') }}</th>
                                        <th>{{ __('Sub Tax') }}</th>
                                        <th class="text-center" style="width: 120px;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tax_groups ?? [] as $group)
                                    <tr data-tax-ids="{{ implode(',', $group['tax_ids']) }}" data-tax-id="{{ $group['tax_id'] }}">
                                        <td>{{ $group['tax_name'] }}</td>
                                        <td>{{ $group['tax_rate'] !== null ? number_format($group['tax_rate'], 2) . '%' : '-' }}</td>
                                        <td>
                                            @if(!empty($group['sub_taxes']))
                                                @foreach($group['sub_taxes'] as $sub)
                                                    <span class="badge bg-label-primary me-1">{{ $sub['name'] }}:{{ number_format($sub['rate'], 2) }}%</span>
                                                @endforeach
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex datatable-action gap-1">
                                                <button type="button" class="btn btn-sm btn-icon edit-record edit-tax-btn" data-tax-id="{{ $group['tax_id'] }}" data-tax-name="{{ $group['tax_name'] }}" data-tax-rate="{{ $group['tax_rate'] ?? '' }}" data-show-in-item-profile="{{ $group['show_in_item_profile'] }}" data-tax-ids="{{ implode(',', $group['tax_ids']) }}" data-sub-tax-ids="{{ json_encode(collect($group['sub_taxes'] ?? [])->pluck('id')->toArray()) }}" title="{{ __('Edit') }}">
                                                    <i class="ti tabler-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-icon delete-record delete-tax-btn text-danger" data-tax-ids="{{ implode(',', $group['tax_ids']) }}" title="{{ __('Delete') }}">
                                                    <i class="ti tabler-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr id="tax_table_empty_row">
                                        <td colspan="4" class="text-center text-muted py-4">{{ __('No tax added yet. Click Add Tax to add.') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-4">
            <button type="submit" class="btn btn-primary waves-effect waves-light tax_setting_submit">
                <i class="ti tabler-checkbox me-1"></i>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>

{{-- Add/Edit Tax Modal --}}
<div class="modal fade" id="modal_add_tax" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="tax_form">
                <input type="hidden" id="tax_form_id" name="tax_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal_add_tax_title">{{ __('Add Tax') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <script type="text/javascript">
                        window.AVAILABLE_SUB_TAXES = @json(collect($available_sub_taxes ?? [])->map(fn($t) => ['id' => $t->id, 'name' => $t->tax_name, 'rate' => (float) $t->tax_rate])->values()->toArray());
                    </script>
                    <div class="mb-3 validate_wrapper">
                        <label class="form-label" for="tax_name">{{ __('Tax Name') }} {!! requiredField() !!}</label>
                        <input type="text" class="form-control" id="tax_name" name="tax_name" placeholder="{{ __('e.g.') }} GST, VAT" value="">
                        <small class="text-muted d-none" id="tax_name_hint">{{ __('Create IGST, CGST, SGST first, then assign to GST') }}</small>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3 validate_wrapper" id="tax_rate_wrapper">
                        <label class="form-label" for="tax_rate">{{ __('Tax Rate') }} (%) {!! requiredField() !!}</label>
                        <input type="text" class="form-control number-input" id="tax_rate" name="tax_rate" placeholder="0" value="">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3 validate_wrapper" id="sub_tax_wrapper" style="display: none;">
                        <label class="form-label">{{ __('Assign Sub Taxes') }} {!! requiredField() !!}</label>
                        <select class="form-select select2" id="sub_tax_ids" name="sub_tax_ids[]" multiple size="6">
                            @foreach($available_sub_taxes ?? [] as $st)
                                <option value="{{ $st->id }}">{{ $st->tax_name }} ({{ number_format($st->tax_rate, 2) }}%)</option>
                            @endforeach
                        </select>
                        <div class="alert alert-info mt-2 mb-0 d-flex align-items-start" id="gst_sub_tax_message" role="alert" data-guide="{{ __('GST requires exactly 3 sub taxes: IGST, CGST and SGST. Select all sub taxes.') }}" data-create-first="{{ __('Create IGST, CGST, SGST first, then assign to GST.') }}">
                            <i class="ti tabler-info-circle me-2 flex-shrink-0 mt-1"></i>
                            <span>{{ __('GST requires exactly 3 sub taxes: IGST, CGST and SGST. Select all sub taxes.') }}</span>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3 validate_wrapper">
                        <label class="form-label">{{ __('Show in Item Profile') }} {!! requiredField() !!}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="show_in_item_profile" id="show_in_item_profile_yes" value="Yes">
                            <label class="form-check-label" for="show_in_item_profile_yes">{{ __('Yes') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="show_in_item_profile" id="show_in_item_profile_no" value="No" checked>
                            <label class="form-check-label" for="show_in_item_profile_no">{{ __('No') }}</label>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="tax_form_submit_btn">
                        <i class="ti tabler-check me-1"></i>{{ __('Save Tax') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
