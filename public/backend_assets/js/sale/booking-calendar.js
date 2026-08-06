/**
 * Booking Calendar
 */

'use strict';

(function() {
    let calendar = null;
    let selectedDate = null;
    let currentBookingId = null;
    let bookingTable = null;
    let currentPage = 1;
    let pageSize = 10;

    document.addEventListener('DOMContentLoaded', function() {
        initializeCalendar();
        initializeForm();
        initializeFilters();
        initializeBookingList();
    });

    /**
     * Initialize FullCalendar
     */
    function initializeCalendar() {
        const calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        // Get today's date in YYYY-MM-DD format for validRange
        const today = moment().format('YYYY-MM-DD');

        // FullCalendar classes are available as globals
        calendar = new Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            plugins: [dayGridPlugin, interactionPlugin, listPlugin, timegridPlugin],
            headerToolbar: {
                start: 'prev,next today',
                center: 'title',
                end: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            editable: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            timeZone: 'local',
            eventMinHeight: 36,
            validRange: {
                start: today
            },
            events: function(info, successCallback, failureCallback) {
                fetchBookings(info.startStr, info.endStr, successCallback, failureCallback);
            },
            eventDidMount: function(info) {
                // Ensure events are properly styled (Month, Week, Day, List)
                var el = info.el;
                if (info.event.extendedProps.status) {
                    var status = info.event.extendedProps.status;
                    var statusColors = {
                        'Booked': '#7367f0',
                        'Waiting': '#ff9f43',
                        'Completed': '#28c76f',
                        'Cancelled': '#ff4c51'
                    };
                    if (statusColors[status]) {
                        el.style.backgroundColor = statusColors[status];
                        el.style.borderColor = statusColors[status];
                    }
                }
                // Force white text for readability on colored background (all views)
                el.style.color = '#fff';
                // Week/Day (timeGrid): ensure inner content gets white text and stays within segment
                if (el.classList.contains('fc-timegrid-event')) {
                    el.style.overflow = 'hidden';
                    var main = el.querySelector('.fc-event-main');
                    if (main) {
                        main.style.color = '#fff';
                        main.style.textShadow = '0 1px 2px rgba(0,0,0,0.3)';
                    }
                    var timeEl = el.querySelector('.fc-event-time');
                    if (timeEl) {
                        timeEl.style.color = 'rgba(255,255,255,0.95)';
                        timeEl.style.textShadow = '0 1px 2px rgba(0,0,0,0.3)';
                    }
                }
            },
            dateClick: function(info) {
                // Prevent clicking on past dates
                const clickedDate = moment(info.dateStr);
                const today = moment().startOf('day');
                if (clickedDate.isBefore(today)) {
                    if (typeof showErrorNotification === 'function') {
                        showErrorNotification('Cannot create booking for past dates');
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Invalid Date',
                            text: 'Cannot create booking for past dates',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    return;
                }
                
                selectedDate = info.dateStr;
                resetForm();
                // Set start date if date is clicked
                if (selectedDate) {
                    const startDate = moment(selectedDate).format('YYYY-MM-DD HH:mm');
                    $('#start_date').val(startDate);
                    const endDate = moment(selectedDate).add(1, 'hours').format('YYYY-MM-DD HH:mm');
                    $('#end_date').val(endDate);
                }
                openBookingForm();
                
                // Reinitialize date pickers when form opens
                setTimeout(function() {
                    reinitializeDatePickers();
                }, 200);
            },
            eventClick: function(info) {
                editBooking(info.event);
            },
            eventDrop: function(info) {
                updateBookingDates(info.event);
            },
            eventResize: function(info) {
                updateBookingDates(info.event);
            }
        });

        calendar.render();
    }

    /**
     * Fetch bookings from server
     */
    function fetchBookings(start, end, successCallback, failureCallback) {
        $.ajax({
            url: bookingRoutes.getBookings,
            type: 'GET',
            data: {
                start: start,
                end: end
            },
            success: function(events) {
                // Normalize event times so Week/Day view renders correctly (no full-day or zero-height)
                var normalized = (events || []).map(function(ev) {
                    if (!ev || !ev.start) return ev;
                    var start = moment(ev.start);
                    var end = ev.end ? moment(ev.end) : start.clone().add(1, 'hour');
                    if (!end.isAfter(start)) {
                        end = start.clone().add(1, 'hour');
                    }
                    return Object.assign({}, ev, {
                        start: start.toISOString(),
                        end: end.toISOString(),
                        allDay: false
                    });
                });
                var filteredEvents = filterEventsByStatus(normalized);
                successCallback(filteredEvents);
            },
            error: function(xhr) {
                console.error('Error fetching bookings:', xhr);
                if (failureCallback) failureCallback(xhr);
            }
        });
    }

    /**
     * Filter events by status
     */
    function filterEventsByStatus(events) {
        if (!events || events.length === 0) {
            return [];
        }

        const activeFilters = [];
        
        // Get all checked filter checkboxes including filter-booked
        $('.input-filter:checked, #filter-booked:checked').each(function() {
            const value = $(this).data('value');
            if (value) {
                activeFilters.push(value);
            }
        });

        // If no filters are checked, return all events
        if (activeFilters.length === 0) {
            return events;
        }

        return events.filter(function(event) {
            if (!event || !event.extendedProps) {
                return false;
            }
            
            const status = event.extendedProps.status;
            return activeFilters.includes(status);
        });
    }

    /**
     * Reinitialize date pickers with minDate
     */
    function reinitializeDatePickers() {
        reinitializeDatePickersForEdit('today');
    }

    /**
     * Reinitialize date pickers with custom minDate (for editing existing bookings)
     */
    function reinitializeDatePickersForEdit(minDate) {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        
        // Destroy existing flatpickr instances if they exist
        if (startDateInput && startDateInput._flatpickr) {
            startDateInput._flatpickr.destroy();
        }
        if (endDateInput && endDateInput._flatpickr) {
            endDateInput._flatpickr.destroy();
        }
        
        // Initialize with minDate
        if (startDateInput) {
            flatpickr(startDateInput, {
                enableTime: true,
                altInput: true,
                dateFormat: 'Y-m-d H:i',
                static: true,
                allowInput: true,
                time_24hr: true,
                minDate: minDate,
                onChange: function(selectedDates, dateStr) {
                    // Update end date minDate to be same as start date
                    if (endDateInput && endDateInput._flatpickr && selectedDates.length > 0) {
                        endDateInput._flatpickr.set('minDate', dateStr);
                    }
                }
            });
        }
        
        if (endDateInput) {
            const currentStartDate = $('#start_date').val();
            const endMinDate = currentStartDate ? currentStartDate : minDate;
            flatpickr(endDateInput, {
                enableTime: true,
                altInput: true,
                dateFormat: 'Y-m-d H:i',
                static: true,
                allowInput: true,
                time_24hr: true,
                minDate: endMinDate
            });
        }
    }

    /**
     * Initialize form
     */
    function initializeForm() {
        // Initialize inline calendar with minDate to disable past dates
        if ($('.inline-calendar').length) {
            const inlineCalEl = document.querySelector('.inline-calendar');
            if (inlineCalEl) {
                window.inlineCalInstance = flatpickr(inlineCalEl, {
                    inline: true,
                    minDate: 'today',
                    onChange: function(selectedDates) {
                        if (selectedDates.length > 0) {
                            selectedDate = moment(selectedDates[0]).format('YYYY-MM-DD');
                        }
                    }
                });
            }
        }

        // Initialize date pickers with minDate
        reinitializeDatePickers();

        // Form submission
        $('#eventForm').on('submit', function(e) {
            e.preventDefault();
            saveBooking();
        });

        // Delete button
        $('.btn-delete-event').on('click', function() {
            deleteBooking();
        });

        // View all bookings button
        $('#viewAllBookingsBtn').on('click', function() {
            $('#bookingListModal').modal('show');
            if (!bookingTable) {
                initializeBookingDataTable();
            } else {
                bookingTable.ajax.reload();
            }
        });

        // Add Booking button - populate date from inline calendar
        $('.btn-toggle-sidebar').on('click', function() {
            resetForm();
            let dateToUse = null;
            
            // Get selected date from inline calendar
            if (window.inlineCalInstance && window.inlineCalInstance.selectedDates && window.inlineCalInstance.selectedDates.length > 0) {
                const selectedDateMoment = moment(window.inlineCalInstance.selectedDates[0]);
                const today = moment().startOf('day');
                // Only use if not a past date
                if (selectedDateMoment.isSameOrAfter(today)) {
                    dateToUse = selectedDateMoment.format('YYYY-MM-DD');
                }
            } else if (selectedDate) {
                // Use previously selected date from calendar click
                const selectedDateMoment = moment(selectedDate);
                const today = moment().startOf('day');
                // Only use if not a past date
                if (selectedDateMoment.isSameOrAfter(today)) {
                    dateToUse = selectedDate;
                }
            }
            
            // Default to today if no valid date
            if (!dateToUse) {
                dateToUse = moment().format('YYYY-MM-DD');
            }
            
            if (dateToUse) {
                const startDate = dateToUse + ' 09:00';
                const endDate = dateToUse + ' 10:00';
                // Use setTimeout to ensure flatpickr is initialized
                setTimeout(function() {
                    $('#start_date').val(startDate).trigger('change');
                    $('#end_date').val(endDate).trigger('change');
                }, 100);
            }
            
            // Reinitialize date pickers when form opens to ensure minDate is set
            setTimeout(function() {
                reinitializeDatePickers();
            }, 200);
        });
    }

    /**
     * Initialize filters
     */
    function initializeFilters() {
        // Handle all filter checkboxes (including filter-booked)
        $('.input-filter, #filter-booked').on('change', function() {
            refreshCalendar();
        });
    }

    /**
     * Refresh calendar
     */
    function refreshCalendar() {
        if (calendar) {
            // Refetch events to show new/updated bookings immediately
            calendar.refetchEvents();
        }
    }

    /**
     * Reset form
     */
    function resetForm() {
        $('#eventForm')[0].reset();
        $('#bookingId').val('');
        currentBookingId = null;
        $('#addEventSidebarLabel').text('Add Booking');
        $('#addEventBtn').removeClass('btn-update-event').addClass('btn-add-event');
        $('.btn-delete-event').addClass('d-none');
        $('.select2').val(null).trigger('change');
        // Clear validation errors
        clearValidationErrors();
    }

    /**
     * Clear validation errors
     */
    function clearValidationErrors() {
        $('.form-control-validation .invalid-feedback').remove();
        $('.form-control-validation .form-control, .form-control-validation .form-select').removeClass('is-invalid');
    }

    /**
     * Show validation error
     */
    function showValidationError(fieldId, message) {
        const field = $('#' + fieldId);
        const parent = field.closest('.form-control-validation');
        
        // Remove existing error
        parent.find('.invalid-feedback').remove();
        field.removeClass('is-invalid');
        
        // Add error
        field.addClass('is-invalid');
        parent.append('<div class="invalid-feedback">' + message + '</div>');
    }

    /**
     * Open booking form
     */
    function openBookingForm() {
        const offcanvas = new bootstrap.Offcanvas(document.getElementById('addEventSidebar'));
        offcanvas.show();
    }

    /**
     * Edit booking
     */
    function editBooking(event) {
        currentBookingId = event.id;
        const props = event.extendedProps;

        $('#bookingId').val(currentBookingId);
        $('#outlet_id').val(props.outlet_id).trigger('change');
        $('#customer_id').val(props.customer_id).trigger('change');
        $('#service_seller_id').val(props.service_seller_id).trigger('change');
        $('#status').val(props.status).trigger('change');
        
        const startDate = moment(event.start).format('YYYY-MM-DD HH:mm');
        const endDate = moment(event.end || event.start).format('YYYY-MM-DD HH:mm');
        $('#start_date').val(startDate);
        $('#end_date').val(endDate);
        $('#note').val(props.note || '');
        
        $('#addEventSidebarLabel').text('Edit Booking');
        $('#addEventBtn').removeClass('btn-add-event').addClass('btn-update-event');
        $('.btn-delete-event').removeClass('d-none');
        
        openBookingForm();
        
        // Reinitialize date pickers - if booking is in the past, allow editing but prevent moving to past
        setTimeout(function() {
            const bookingStartDate = moment(event.start);
            const today = moment().startOf('day');
            
            // If booking is today or future, set minDate to today
            // If booking is in the past, set minDate to the booking's start date (allow editing but not moving to earlier past)
            const minDate = bookingStartDate.isBefore(today) 
                ? bookingStartDate.format('YYYY-MM-DD HH:mm')
                : 'today';
            
            reinitializeDatePickersForEdit(minDate);
        }, 200);
    }

    /**
     * Validate booking form
     */
    function validateBookingForm() {
        let isValid = true;
        clearValidationErrors();

        // Validate outlet
        if (!$('#outlet_id').val()) {
            showValidationError('outlet_id', 'Please select an outlet');
            isValid = false;
        }

        // Validate customer
        if (!$('#customer_id').val()) {
            showValidationError('customer_id', 'Please select a customer');
            isValid = false;
        }

        // Validate status
        if (!$('#status').val()) {
            showValidationError('status', 'Please select a status');
            isValid = false;
        }

        // Validate start date
        if (!$('#start_date').val()) {
            showValidationError('start_date', 'Please select a start date and time');
            isValid = false;
        }

        // Validate end date
        if (!$('#end_date').val()) {
            showValidationError('end_date', 'Please select an end date and time');
            isValid = false;
        }

        // Validate end date is not before start date (same date/time is allowed)
        if ($('#start_date').val() && $('#end_date').val()) {
            const startDate = moment($('#start_date').val(), 'YYYY-MM-DD HH:mm');
            const endDate = moment($('#end_date').val(), 'YYYY-MM-DD HH:mm');
            if (endDate.isBefore(startDate)) {
                showValidationError('end_date', 'End date must be after or equal to start date');
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * Save booking
     */
    function saveBooking() {
        // Clear previous validation errors
        clearValidationErrors();

        // Validate form
        if (!validateBookingForm()) {
            // Scroll to first error
            const firstError = $('.is-invalid').first();
            if (firstError.length) {
                $('.offcanvas-body').animate({
                    scrollTop: firstError.offset().top - $('.offcanvas-body').offset().top + $('.offcanvas-body').scrollTop() - 20
                }, 500);
            }
            return;
        }

        const formData = {
            outlet_id: $('#outlet_id').val(),
            customer_id: $('#customer_id').val(),
            service_seller_id: $('#service_seller_id').val(),
            status: $('#status').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            note: $('#note').val(),
            send_email: $('#send_email').is(':checked') ? 1 : 0
        };

        const url = currentBookingId 
            ? bookingRoutes.update.replace(':id', currentBookingId)
            : bookingRoutes.store;
        const method = currentBookingId ? 'PUT' : 'POST';

        // Show loading state
        const submitBtn = $('#addEventBtn');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="ti tabler-loader-2 me-1"></i>Processing...');

        $.ajax({
            url: url,
            type: method,
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Show success toast
                    if (typeof showSuccessNotification === 'function') {
                        showSuccessNotification(response.message || (currentBookingId ? 'Booking updated successfully' : 'Booking created successfully'));
                        $('.offcanvas-backdrop.fade').removeClass('show');
                    } else {
                        // Fallback to Swal if toast not available
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                    
                    // Close form
                    bootstrap.Offcanvas.getInstance(document.getElementById('addEventSidebar')).hide();
                    resetForm();
                    
                    // Refresh calendar immediately to show new/updated booking
                    if (calendar) {
                        calendar.refetchEvents();
                    }
                    
                    // Reload booking list if modal is open
                    if ($('#bookingListModal').hasClass('show') && bookingTable) {
                        bookingTable.ajax.reload();
                    }
                } else {
                    // Show error toast
                    if (typeof showErrorNotification === 'function') {
                        showErrorNotification(response.message || 'Failed to save booking');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to save booking'
                        });
                    }
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred';
                const errors = {};
                
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.errors) {
                        // Show field-specific validation errors
                        Object.keys(xhr.responseJSON.errors).forEach(function(field) {
                            const fieldErrors = xhr.responseJSON.errors[field];
                            if (fieldErrors && fieldErrors.length > 0) {
                                showValidationError(field, fieldErrors[0]);
                                errors[field] = fieldErrors[0];
                            }
                        });
                        
                        // If there are field errors, show first one as general message
                        if (Object.keys(errors).length > 0) {
                            errorMessage = Object.values(errors)[0];
                        }
                    }
                }
                
                // Show error toast
                if (typeof showErrorNotification === 'function') {
                    showErrorNotification(errorMessage);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage
                    });
                }
                
                // Scroll to first error if any
                const firstError = $('.is-invalid').first();
                if (firstError.length) {
                    $('.offcanvas-body').animate({
                        scrollTop: firstError.offset().top - $('.offcanvas-body').offset().top + $('.offcanvas-body').scrollTop() - 20
                    }, 500);
                }
            },
            complete: function() {
                // Restore button state
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    /**
     * Delete booking
     */
    function deleteBooking() {
        if (!currentBookingId) return;

        Swal.fire({
            title: 'Are you sure?',
            text: 'You won\'t be able to revert this!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: bookingRoutes.destroy.replace(':id', currentBookingId),
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success toast
                            if (typeof showSuccessNotification === 'function') {
                                showSuccessNotification(response.message || 'Booking deleted successfully');
                            } else {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: response.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                            
                            bootstrap.Offcanvas.getInstance(document.getElementById('addEventSidebar')).hide();
                            resetForm();
                            refreshCalendar();
                            if ($('#bookingListModal').hasClass('show') && bookingTable) {
                                bookingTable.ajax.reload();
                            }
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON && xhr.responseJSON.message 
                            ? xhr.responseJSON.message 
                            : 'Failed to delete booking';
                        
                        // Show error toast
                        if (typeof showErrorNotification === 'function') {
                            showErrorNotification(errorMessage);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errorMessage
                            });
                        }
                    }
                });
            }
        });
    }

    /**
     * Update booking dates (drag & drop)
     */
    function updateBookingDates(event) {
        // Prevent moving to past dates
        const eventStart = moment(event.start);
        const today = moment().startOf('day');
        
        if (eventStart.isBefore(today)) {
            // Revert the event to its original position
            refreshCalendar();
            if (typeof showErrorNotification === 'function') {
                showErrorNotification('Cannot move booking to a past date');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Cannot move booking to a past date'
                });
            }
            return;
        }

        const formData = {
            outlet_id: event.extendedProps.outlet_id,
            customer_id: event.extendedProps.customer_id,
            service_seller_id: event.extendedProps.service_seller_id,
            status: event.extendedProps.status,
            start_date: moment(event.start).format('YYYY-MM-DD HH:mm'),
            end_date: moment(event.end || event.start).format('YYYY-MM-DD HH:mm'),
            note: event.extendedProps.note || ''
        };

        $.ajax({
            url: bookingRoutes.update.replace(':id', event.id),
            type: 'PUT',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (!response.success) {
                    refreshCalendar();
                }
            },
            error: function() {
                refreshCalendar();
            }
        });
    }

    /**
     * Initialize booking list DataTable
     */
    function initializeBookingList() {
        // Status filter change will trigger DataTable reload
        $('#statusFilter').on('change', function() {
            if (bookingTable) {
                bookingTable.ajax.reload();
            }
        });
    }

    /**
     * Initialize DataTable for booking list
     */
    function initializeBookingDataTable() {
        if ($.fn.DataTable.isDataTable('#bookingListTable')) {
            $('#bookingListTable').DataTable().destroy();
        }

        bookingTable = $('#bookingListTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: bookingRoutes.getBookingList,
                type: 'GET',
                data: function(d) {
                    d.status_filter = $('#statusFilter').val() || 'all';
                },
                dataSrc: function(json) {
                    return json.data || [];
                }
            },
            columns: [
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    },
                    orderable: false
                },
                { data: 'customer_name' },
                { data: 'customer_phone' },
                { data: 'service_seller_name' },
                { data: 'outlet_name' },
                {
                    data: 'status',
                    render: function(data) {
                        return `<span class="badge bg-label-${getStatusColorClass(data)}">${data}</span>`;
                    }
                },
                { data: 'start_date' },
                { data: 'end_date' },
                {
                    data: 'note',
                    render: function(data) {
                        return data || '-';
                    }
                },
                {
                    data: 'id',
                    render: function(data) {
                        return `<div class="datatable-action"><button class="btn btn-sm btn-icon edit-record edit-booking" data-id="${data}">
                            <i class="ti tabler-edit"></i>
                        </button></div>`;
                    },
                    orderable: false
                }
            ],
            order: [[6, 'desc']], // Order by start_date descending
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: {
                processing: 'Loading...',
                emptyTable: 'No bookings found'
            }
        });

        // Bind edit button click (using event delegation for dynamically added buttons)
        $('#bookingListTable').on('click', '.edit-booking', function() {
            const bookingId = $(this).data('id');
            editBookingFromList(bookingId);
        });
    }

    /**
     * Edit booking from list
     */
    function editBookingFromList(bookingId) {
        // Find the event in calendar
        const events = calendar.getEvents();
        const event = events.find(e => e.id == bookingId);
        
        if (event) {
            editBooking(event);
            $('#bookingListModal').modal('hide');
        } else {
            // If event not in current view, fetch it
            refreshCalendar();
            setTimeout(function() {
                const events = calendar.getEvents();
                const event = events.find(e => e.id == bookingId);
                if (event) {
                    editBooking(event);
                    $('#bookingListModal').modal('hide');
                }
            }, 500);
        }
    }

    /**
     * Get status color class
     */
    function getStatusColorClass(status) {
        const colors = {
            'Booked': 'primary',
            'Waiting': 'danger',
            'Completed': 'success',
            'Cancelled': 'warning'
        };
        return colors[status] || 'primary';
    }
})();
