 @extends('backend.backend_layout')
@section('page-title', 'Admin-Home Page | LaraStarter')
@push('page-css')
<style>
  [dir="rtl"] .user-profile .icon {
    margin-right: 0px;
    margin-left: 15px;
  }
  [dir="rtl"] div.dt-container div.dt-search {
    text-align: left;
  }
</style>
@endpush
@section('page-content')

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-6">
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card mb-5">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start">
                        <img width="50" height="50"  src="{{ asset('uploads/business_setting/default-admin.png') }}" alt="user-avater" class="rounded">
                        <div class="ps-5">
                            <h5 class="mb-0">{{ Auth::user()->name }}</h5>
                            <span>{{ Auth::user()->roles->first()->name ?? 'No Role Assigned' }}</span>
                        </div>
                    </div>
                    <hr>
                    <a href="{{ route('user.update-profile') }}" class="d-flex align-items-center user-profile mb-3 purple-wrap">
                        <span class="icon purple">
                            <i class="ti tabler-user"></i>
                        </span>
                        <span class="title">{{ __('Change Profile') }}</span>
                    </a>
                    <a href="{{ route('user.update-profile') }}#passwordPart" class="d-flex align-items-center user-profile mb-3 success-wrap">
                        <span class="icon success">
                            <i class="ti tabler-key"></i>
                        </span>
                        <span class="title">{{ __('Change Password') }}</span>
                    </a>
                    <a href="{{ route('user.update-profile') }}#securityPart" class="d-flex align-items-center user-profile mb-3 blue-wrap">
                        <span class="icon blue">
                            <i class="ti tabler-help"></i>
                        </span>
                        <span class="title">{{ __('Security Question') }}</span>
                    </a>
                    <div class="d-flex align-items-center user-profile mb-3 danger-wrap logout-btn cursor-pointer">
                        <span class="icon danger">
                            <i class="ti tabler-logout"></i>
                        </span>
                        <span class="title">{{ __('Logout') }}</span>
                    </div>
                </div>
            </div>
            <div class="row g-6">
                <!-- Card Border Shadow - This Week Sales -->
                <div class="col-lg-6 col-md-12">
                  <div class="card card-border-shadow-primary h-100">
                    <div class="card-body">
                      <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                          <span class="avatar-initial rounded bg-label-primary"
                            ><i class="icon-base ti tabler-shopping-cart icon-28px"></i
                          ></span>
                        </div>
                        <h4 class="mb-0" id="this-week-sales-count">0</h4>
                      </div>
                      <p class="mb-1">{{ __('Sales') }}</p>
                      <p class="mb-0">
                        <span class="text-heading fw-medium me-2" id="this-week-sales-change">0%</span>
                        <small class="text-body-secondary">{{ __('than last week') }}</small>
                      </p>
                    </div>
                  </div>
                </div>
                <!-- Card Border Shadow - This Week Purchase -->
                <div class="col-lg-6 col-md-12">
                  <div class="card card-border-shadow-warning h-100">
                    <div class="card-body">
                      <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                          <span class="avatar-initial rounded bg-label-warning"
                            ><i class="icon-base ti tabler-basket icon-28px"></i
                          ></span>
                        </div>
                        <h4 class="mb-0" id="this-week-purchases-count">0</h4>
                      </div>
                      <p class="mb-1">{{ __('Purchase') }}</p>
                      <p class="mb-0">
                        <span class="text-heading fw-medium me-2" id="this-week-purchases-change">0%</span>
                        <small class="text-body-secondary">{{ __('than last week') }}</small>
                      </p>
                    </div>
                  </div>
                </div>
                <!-- Card Border Shadow - This Week Customer Receive -->
                <div class="col-lg-6 col-md-12">
                  <div class="card card-border-shadow-danger h-100">
                    <div class="card-body">
                      <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                          <span class="avatar-initial rounded bg-label-danger"
                            ><i class="icon-base ti tabler-cash icon-28px"></i
                          ></span>
                        </div>
                        <h4 class="mb-0" id="this-week-customer-receive-count">0</h4>
                      </div>
                      <p class="mb-1">{{ __('Customer Receive') }}</p>
                      <p class="mb-0">
                        <span class="text-heading fw-medium me-2" id="this-week-customer-receive-change">0%</span>
                        <small class="text-body-secondary">{{ __('than last week') }}</small>
                      </p>
                    </div>
                  </div>
                </div>
                <!-- Card Border Shadow - This Week Supplier Payment -->
                <div class="col-lg-6 col-md-12">
                  <div class="card card-border-shadow-info h-100">
                    <div class="card-body">
                      <div class="d-flex align-items-center mb-2">
                        <div class="avatar me-4">
                          <span class="avatar-initial rounded bg-label-info"
                            ><i class="icon-base ti tabler-credit-card icon-28px"></i
                          ></span>
                        </div>
                        <h4 class="mb-0" id="this-week-supplier-payment-count">0</h4>
                      </div>
                      <p class="mb-1">{{ __('Supplier Payment') }}</p>
                      <p class="mb-0">
                        <span class="text-heading fw-medium me-2" id="this-week-supplier-payment-change">0%</span>
                        <small class="text-body-secondary">{{ __('than last week') }}</small>
                      </p>
                    </div>
                  </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-md-6 col-sm-12">
            <div class="card">
                <div class="card-datatable table-responsive pt-0">
                    <table class="datatables-basic table">
                        <thead>
                            <tr>
                                <th>{{ __('SN') }}</th>
                                <th>{{ __('Sale_No') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Items') }}</th>
                                <th>{{ __('Total_Payable') }}</th>
                                <th>{{ __('Paid_Amount') }}</th>
                                <th>{{ __('Sale_Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
@routes
<script src="{{ asset('backend_assets/js/pages_list_js/list_user_sales.js')}}"></script>
@endpush