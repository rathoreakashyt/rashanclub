@extends('backend.backend_layout')
@section('page-title', __('Booking Calendar'))
@push('page-css')
<!-- Page CSS -->
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/libs/fullcalendar/fullcalendar.css') }}" />
<link rel="stylesheet" href="{{ asset('backend_assets/vendor/css/pages/app-calendar.css') }}" />
  <style>
    .fc-event {
      color: white !important;
    }
    /* Week & Day view: readable text and correct event block (no full-day background) */
    .app-calendar-wrapper .fc-timegrid .fc-timegrid-event {
      border-radius: 0.25rem;
      overflow: hidden;
      min-height: 2.5em;
    }
    .app-calendar-wrapper .fc-timegrid .fc-timegrid-event .fc-event-main,
    .app-calendar-wrapper .fc-timegrid .fc-timegrid-event .fc-event-time {
      color: #fff !important;
      text-shadow: 0 1px 2px rgba(0,0,0,0.35);
    }
    .app-calendar-wrapper .fc-timegrid .fc-timegrid-event .fc-event-title {
      color: #fff !important;
      text-shadow: 0 1px 2px rgba(0,0,0,0.35);
    }
    /* Ensure only the event segment has background, not the whole day column */
    .app-calendar-wrapper .fc-timegrid-col.fc-day-today {
      background-color: var(--fc-today-bg-color, rgba(0,0,0,0.02));
    }
    .form-control-validation .is-invalid {
      border-color: #ea5455;
    }
    .form-control-validation .invalid-feedback {
      display: block;
      color: #ea5455;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }
    /* .fc .fc-day-disabled {
      background-color: #f8f9fa;
    } */
    div.dt-container div.dt-paging ul.pagination {
      justify-content: end;
    }
    .datatable-action i {
        font-size: 18px !important;
    }
  </style>
@endpush
@section('page-content')

<div class="content-wrapper">
  <!-- Content -->
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="card app-calendar-wrapper">
      <div class="row g-0">
        <!-- Calendar Sidebar -->
        <div class="col app-calendar-sidebar border-end" id="app-calendar-sidebar">
          <div class="border-bottom p-6 my-sm-0 mb-4">
            <button
              class="btn btn-primary btn-toggle-sidebar w-100"
              data-bs-toggle="offcanvas"
              data-bs-target="#addEventSidebar"
              aria-controls="addEventSidebar">
              <i class="icon-base ti tabler-plus icon-16px me-2"></i>
              <span class="align-middle">{{ __('Add Booking') }}</span>
            </button>
          </div>
          <div class="px-3 pt-2">
            <!-- inline calendar (flatpicker) -->
            <div class="inline-calendar"></div>
          </div>
          <hr class="mb-6 mx-n4 mt-3" />
          <div class="px-6 pb-2">
            <!-- Filter -->
            <div>
              <h5>{{ __('Booking Filters') }}</h5>
            </div>

            
            <div class="form-check form-check-primary mb-5 ms-2">
              <input
                class="form-check-input"
                type="checkbox"
                id="filter-booked"
                data-value="Booked"
                checked />
              <label class="form-check-label" for="filter-booked">{{ __('Booked') }}</label>
            </div>

            <div class="app-calendar-events-filter text-heading">
              <div class="form-check form-check-warning mb-5 ms-2">
                <input
                  class="form-check-input input-filter"
                  type="checkbox"
                  id="filter-waiting"
                  data-value="Waiting"
                  checked />
                <label class="form-check-label" for="filter-waiting">{{ __('Waiting') }}</label>
              </div>
              <div class="form-check form-check-success mb-5 ms-2">
                <input
                  class="form-check-input input-filter"
                  type="checkbox"
                  id="filter-completed"
                  data-value="Completed"
                  checked />
                <label class="form-check-label" for="filter-completed">{{ __('Completed') }}</label>
              </div>
              <div class="form-check form-check-danger mb-5 ms-2">
                <input
                  class="form-check-input input-filter"
                  type="checkbox"
                  id="filter-cancelled"
                  data-value="Cancelled"
                  checked />
                <label class="form-check-label" for="filter-cancelled">{{ __('Cancelled') }}</label>
              </div>
            </div>
          </div>
        </div>
        <!-- /Calendar Sidebar -->

        <!-- Calendar & Modal -->
        <div class="col app-calendar-content">
          <div class="card shadow-none border-0">
            <div class="card-body pb-0">
              <!-- FullCalendar -->
              <div id="calendar"></div>
            </div>
          </div>
          <div class="app-overlay"></div>
          <!-- FullCalendar Offcanvas -->
          <div
            class="offcanvas offcanvas-end event-sidebar"
            tabindex="-1"
            id="addEventSidebar"
            aria-labelledby="addEventSidebarLabel">
            <div class="offcanvas-header border-bottom">
              <h5 class="offcanvas-title">{{ __('Add Booking') }}</h5>
              <button
                type="button"
                class="btn-close text-reset"
                data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
              <form class="event-form pt-0" id="eventForm" onsubmit="return false">
                <input type="hidden" id="bookingId" name="booking_id" value="">
                <div class="mb-5 form-control-validation">
                  <label class="form-label" for="outlet_id">{{ __('Outlet') }} <span class="text-danger">*</span></label>
                  <select class="form-select select2" id="outlet_id" name="outlet_id">
                    <option value="">{{ __('Select Outlet') }}</option>
                    @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}">
                      {{ $outlet->outlet_name }}
                    </option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-5 form-control-validation">
                  <label class="form-label" for="customer_id">{{ __('Customer') }} <span class="text-danger">*</span></label>
                  <select class="form-select select2" id="customer_id" name="customer_id">
                    <option value="">{{ __('Select Customer') }}</option>
                    @foreach($customers as $customer)
                      <option value="{{ $customer->id }}" data-phone="{{ $customer->phone }}">{{ $customer->name }} {{ $customer->phone ? '(' . $customer->phone . ')' : '' }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-5 form-control-validation">
                  <label class="form-label" for="service_seller_id">{{ __('Employee') }}</label>
                  <select class="form-select select2" id="service_seller_id" name="service_seller_id">
                    <option value="">{{ __('Select Employee') }}</option>
                    @foreach($employees as $employee)
                      <option value="{{ $employee->id }}">{{ $employee->name }} {{ $employee->phone ? '(' . $employee->phone . ')' : '' }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-5 form-control-validation">
                  <label class="form-label" for="status">{{ __('Status') }} <span class="text-danger">*</span></label>
                  <select class="form-select select2" id="status" name="status">
                    <option value="">{{ __('Select Status') }}</option>
                    <option value="Booked" selected>{{ __('Booked') }}</option>
                    <option value="Waiting">{{ __('Waiting') }}</option>
                    <option value="Completed">{{ __('Completed') }}</option>
                    <option value="Cancelled">{{ __('Cancelled') }}</option>
                  </select>
                </div>

                <div class="mb-5 form-control-validation">
                  <label class="form-label" for="start_date">{{ __('Start Date') }} <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control datePickerTime"
                    id="start_date"
                    name="start_date"
                    placeholder="{{ __('Start Date & Time') }}"
                    />
                </div>

                <div class="mb-5 form-control-validation">
                  <label class="form-label" for="end_date">{{ __('End Date') }} <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    class="form-control datePickerTime"
                    id="end_date"
                    name="end_date"
                    placeholder="{{ __('End Date & Time') }}"
                    />
                </div>

                <div class="mb-5">
                  <label class="form-label" for="note">{{ __('Note') }}</label>
                  <textarea class="form-control" name="note" id="note" rows="3" placeholder="{{ __('Enter Note') }} ..."></textarea>
                </div>

                <div class="mb-5">
                  <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="send_email" name="send_email" value="1">
                    <label class="form-check-label" for="send_email">{{ __('Send Email') }}</label>
                  </div>
                </div>

                <div class="d-flex justify-content-sm-between justify-content-start mt-6 gap-2">
                  <div class="d-flex">
                    <button type="submit" id="addEventBtn" class="btn btn-primary btn-add-event me-4">
                      {{ __('Submit') }}
                    </button>
                    <button
                      type="reset"
                      class="btn btn-label-secondary btn-cancel me-sm-0 me-1"
                      data-bs-dismiss="offcanvas">
                      {{ __('Cancel') }}
                    </button>
                  </div>
                  <button type="button" class="btn btn-label-danger btn-delete-event d-none">{{ __('Delete') }}</button>
                </div>

                <div class="mt-4">
                  <button type="button" class="btn btn-outline-primary w-100" id="viewAllBookingsBtn">
                    <i class="icon-base ti tabler-list me-2"></i>
                    {{ __('View All Bookings') }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <!-- /Calendar & Modal -->
      </div>
    </div>
  </div>
  <!-- / Content -->

  <div class="content-backdrop fade"></div>
</div>

<!-- Booking List Modal -->
<div class="modal fade" id="bookingListModal" tabindex="-1" aria-labelledby="bookingListModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bookingListModalLabel">{{ __('All Bookings') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Status Filter') }}</label>
            <select class="form-select select2" id="statusFilter">
              <option value="">{{ __('Select Status') }}</option>
              <option value="Booked">{{ __('Booked') }}</option>
              <option value="Waiting">{{ __('Waiting') }}</option>
              <option value="Completed">{{ __('Completed') }}</option>
              <option value="Cancelled">{{ __('Cancelled') }}</option>
            </select>
          </div>
        </div>
        <div class="card p-5">
          <div class="table-responsive">
            <table class="table table-striped datatables-basic" id="bookingListTable">
              <thead>
                <tr>
                  <th>{{ __('SN') }}</th>
                  <th>{{ __('Customer') }}</th>
                  <th>{{ __('Phone') }}</th>
                  <th>{{ __('Employee') }}</th>
                  <th>{{ __('Outlet') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th>{{ __('Start Date') }}</th>
                  <th>{{ __('End Date') }}</th>
                  <th>{{ __('Note') }}</th>
                  <th>{{ __('Action') }}</th>
                </tr>
              </thead>
              <tbody>
                <!-- Data will be loaded via AJAX -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
          {!! closeIconWithText() !!}
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
@push('page-js')
<!-- Page JS -->
<script src="{{ asset('backend_assets/vendor/libs/fullcalendar/fullcalendar.js') }}"></script>
<script src="{{ asset('backend_assets/vendor/libs/moment/moment.js') }}"></script>
<script>
  // Pass data to JavaScript
  const bookingRoutes = {
    getBookings: '{{ route("booking.get-bookings") }}',
    store: '{{ route("booking.store") }}',
    update: '{{ route("booking.update", ":id") }}',
    destroy: '{{ route("booking.destroy", ":id") }}',
    getBookingList: '{{ route("booking.get-booking-list") }}'
  };
</script>
<script src="{{ asset('backend_assets/js/sale/booking-calendar.js') }}"></script>
@endpush
