@extends('backend.backend_layout')
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-4"><i class="ti tabler-wallet me-2"></i>Customer Wallets</h4>

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

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Members</h5>
            <a href="{{ route('businessclub.register') }}" class="btn btn-primary btn-sm">
                <i class="ti tabler-user-plus me-1"></i>Register Member
            </a>
        </div>
        <div class="card-body">
            @if($members->isEmpty())
            <p class="text-muted mb-0">No members registered yet.</p>
            @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>SN</th>
                            <th>Member ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th class="text-end">Total Earned</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Total Redeemed</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $index => $member)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><span class="badge bg-label-primary">{{ $member->member_id }}</span></td>
                            <td>{{ $member->customer->name ?? 'N/A' }}</td>
                            <td>{{ $member->customer->phone ?? '-' }}</td>
                            <td class="text-end">{{ number_format($member->total_earned, 2) }}</td>
                            <td class="text-end">{{ number_format($member->earned_balance, 2) }}</td>
                            <td class="text-end">{{ number_format($member->total_redeemed, 2) }}</td>
                            <td>
                                <span class="badge {{ $member->status === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('businessclub.members.idcard', $member->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="ti tabler-id me-1"></i>Print ID Card
                                </a>
                                <button type="button" class="btn btn-outline-warning btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#redeemModal"
                                        data-customer-id="{{ $member->customer_id }}"
                                        data-customer-name="{{ $member->customer->name ?? '' }}"
                                        data-balance="{{ number_format($member->earned_balance, 2) }}">
                                    <i class="ti tabler-arrow-up-circle me-1"></i>Redeem
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="redeemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('businessclub.members.redeem') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Redeem Profit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="redeemCustomerId">
                    <p class="mb-3">
                        Member: <strong id="redeemCustomerName"></strong><br>
                        Available Balance: <strong id="redeemBalance"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="redeemAmount" class="form-control" required
                                   onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="ti tabler-arrow-up-circle me-1"></i>Redeem
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const redeemModal = document.getElementById('redeemModal');
        if (!redeemModal) return;

        redeemModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            document.getElementById('redeemCustomerId').value = button.getAttribute('data-customer-id');
            document.getElementById('redeemCustomerName').textContent = button.getAttribute('data-customer-name');
            document.getElementById('redeemBalance').textContent = button.getAttribute('data-balance');
            document.getElementById('redeemAmount').value = '';
            document.getElementById('redeemAmount').max = button.getAttribute('data-balance').replace(/,/g, '');
        });
    });
</script>
@endpush