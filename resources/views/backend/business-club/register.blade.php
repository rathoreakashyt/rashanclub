@extends('backend.backend_layout')
@section('page-content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="mb-4"><i class="ti tabler-user-plus me-2"></i>Register Business Club Member</h4>

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

    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">New Member</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Mobile number daalein — agar customer pehle se registered hai to wahi member banega,
                        warna naya customer + member dono ban jayega.
                    </p>
                    <form action="{{ route('businessclub.members.register') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone') }}" placeholder="e.g. 9876543210" required
                                   maxlength="10" inputmode="numeric" pattern="[0-9]{10}"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                            @error('phone')
                            <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name') }}" placeholder="Customer ka naam" required>
                            @error('name')
                            <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email (optional)</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email') }}" placeholder="customer@example.com">
                            @error('email')
                            <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Membership Amount</label>
                            <input type="number" step="0.01" min="0" name="membership_amount" class="form-control"
                                   value="{{ old('membership_amount', '') }}" placeholder="e.g. 10000" required
                                   onkeydown="return !['-','+','e','E'].includes(event.key)" oninput="if(this.value<0)this.value=''">
                            @error('membership_amount')
                            <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-check me-1"></i>Register Member
                        </button>
                        <a href="{{ route('businessclub.wallets') }}" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection