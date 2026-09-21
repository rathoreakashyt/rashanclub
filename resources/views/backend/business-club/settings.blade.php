@extends('backend.backend_layout')
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-4"><i class="ti tabler-settings me-2"></i>Business Club Settings</h4>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('businessclub.settings.save') }}" method="POST">
        @csrf

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Club Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $settings->company_name ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Business Partner Name</label>
                        <input type="text" name="business_partner_name" class="form-control" value="{{ old('business_partner_name', $settings->business_partner_name ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $settings->phone ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $settings->email ?? '') }}">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $settings->address ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Profit &amp; Redemption</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Profit Percentage (%)</label>
                        <input type="number" step="0.01" name="profit_percentage" class="form-control" value="{{ old('profit_percentage', $settings->profit_percentage ?? 50) }}"
                                       onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Profit Share Percentage (%)</label>
                        <input type="number" step="0.01" name="profit_share_percentage" class="form-control" value="{{ old('profit_share_percentage', $settings->profit_share_percentage ?? 50) }}"
                                       onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Redemption Day (of month)</label>
                        <input type="number" min="1" max="31" name="redemption_day" class="form-control" value="{{ old('redemption_day', $settings->redemption_day ?? $settings->redemption_date ?? 1) }}"
                                       onkeydown="return !['-','+','e','E'].includes(event.key)">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Membership &amp; Purchase Limits</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Membership Amount</label>
                        <input type="number" step="0.01" name="membership_amount" class="form-control" value="{{ old('membership_amount', $settings->membership_amount ?? 10000) }}"
                                       onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Min Purchase Amount</label>
                        <input type="number" step="0.01" name="min_purchase_amount" class="form-control" value="{{ old('min_purchase_amount', $settings->min_purchase_amount ?? 10000) }}"
                                       onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Minimum Bill Amount</label>
                        <input type="number" step="0.01" name="minimum_bill_amount" class="form-control" value="{{ old('minimum_bill_amount', $settings->minimum_bill_amount ?? 0) }}"
                                       onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ ($settings->is_active ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="ti tabler-check me-1"></i>Save Settings
            </button>
        </div>
    </form>
</div>
@endsection