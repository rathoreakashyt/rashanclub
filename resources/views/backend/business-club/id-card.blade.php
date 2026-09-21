@extends('backend.backend_layout')
@section('page-title', 'Business Club ID Card')
@section('page-content')
<style>
    .idcard-body {
        background: #f5f5f5;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    .idcard-actions {
        margin-bottom: 24px;
        text-align: center;
    }
    .idcard-actions .btn {
        padding: 10px 30px;
        font-size: 14px;
    }
    .id-card {
        width: 320px;
        background: #ffffff;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18);
        border: 1px solid #e3e7ef;
    }
    .id-card-head {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5986 100%);
        color: #fff;
        padding: 18px 16px;
        text-align: center;
    }
    .id-card-head .club-name {
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .id-card-head .club-subtitle {
        font-size: 10px;
        opacity: 0.8;
        margin-top: 2px;
        letter-spacing: 2px;
    }
    .id-card-head .club-logo {
        max-height: 48px;
        max-width: 120px;
        margin-bottom: 6px;
    }
    .id-card-body {
        padding: 16px;
        text-align: center;
    }
    .id-card-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #2d5986;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .id-card-name {
        font-size: 17px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 2px;
    }
    .id-card-detail {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 2px;
    }
    .id-card-member-id {
        display: inline-block;
        margin-top: 10px;
        padding: 6px 14px;
        border: 1.5px dashed #2d5986;
        border-radius: 6px;
        color: #1e3a5f;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 1px;
    }
    .id-card-footer {
        background: #f1f4f9;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: #4b5563;
    }
    .id-card-footer .label {
        color: #9ca3af;
        text-transform: uppercase;
        font-size: 9px;
        letter-spacing: 1px;
    }
    .id-card-footer .value {
        font-weight: 700;
        color: #1e3a5f;
        margin-top: 2px;
    }
    @media print {
        .idcard-body {
            background: #ffffff;
            padding: 0;
            min-height: auto;
        }
        .idcard-actions {
            display: none !important;
        }
        .layout-wrapper, .layout-page, .content-wrapper {
            all: initial;
        }
    }
</style>

<div class="idcard-body">
    <div class="idcard-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="ti tabler-printer me-1"></i>Print ID Card
        </button>
        <a href="{{ route('businessclub.wallets') }}" class="btn btn-outline-secondary ms-2">Back</a>
    </div>

    <div class="id-card">
        <div class="id-card-head">
            @if(!empty($settings) && !empty($settings->logo))
            <img src="{{ asset($settings->logo) }}" alt="Logo" class="club-logo">
            @endif
            <div class="club-name">{{ $settings->company_name ?? (session('company.company_name') ?? 'Business Club') }}</div>
            <div class="club-subtitle">BUSINESS CLUB MEMBER</div>
        </div>
        <div class="id-card-body">
            <div class="id-card-avatar">{{ strtoupper(substr($customer->name ?? '?', 0, 1)) }}</div>
            <div class="id-card-name">{{ $customer->name ?? 'N/A' }}</div>
            <div class="id-card-detail"><i class="ti tabler-phone me-1"></i>{{ $customer->phone ?? '-' }}</div>
            <div class="id-card-detail"><i class="ti tabler-map-pin me-1"></i>{{ $customer->address ?? '-' }}</div>
            <div class="id-card-member-id">{{ $member->member_id }}</div>
        </div>
        <div class="id-card-footer">
            <div>
                <div class="label">Joined</div>
                <div class="value">{{ optional($member->joined_at)->format('d-M-Y') ?? '-' }}</div>
            </div>
            <div>
                <div class="label">Membership Amount</div>
                <div class="value">{{ number_format($member->membership_amount, 2) }}</div>
            </div>
            <div>
                <div class="label">Balance</div>
                <div class="value">{{ number_format($member->earned_balance, 2) }}</div>
            </div>
        </div>
    </div>
</div>
@endsection