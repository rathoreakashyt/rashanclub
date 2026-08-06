<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sale\Models\Booking;
use Modules\Sale\Models\Customer;
use Modules\Configuration\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Configuration\Services\EmailService;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display the booking calendar.
     */
    public function index()
    {
        $companyId = session('company.company_id');
        
        // Get dropdown options
        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'outlet_name')
            ->orderBy('outlet_name')
            ->get();
        
        $customers = Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get();
        
        $employees = User::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->select('id', 'name', 'phone', 'email')
            ->orderBy('name')
            ->get();
        
        return view('sale::booking.calendar', compact('outlets', 'customers', 'employees'));
    }

    /**
     * Get bookings for calendar (AJAX)
     */
    public function getBookings(Request $request)
    {
        $companyId = session('company.company_id');
        $start = $request->get('start');
        $end = $request->get('end');
        
        // Parse dates to ensure proper format
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();
        
        $bookings = Booking::with(['customer', 'serviceSeller', 'outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->where(function($query) use ($startDate, $endDate) {
                // Check if booking overlaps with the requested date range
                $query->where(function($q) use ($startDate, $endDate) {
                    // Booking starts within range
                    $q->where('start_date', '>=', $startDate)
                      ->where('start_date', '<=', $endDate);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // Booking ends within range
                    $q->where('end_date', '>=', $startDate)
                      ->where('end_date', '<=', $endDate);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    // Booking spans the entire range
                    $q->where('start_date', '<=', $startDate)
                      ->where('end_date', '>=', $endDate);
                });
            })
            ->get();
        
        $events = [];
        foreach ($bookings as $booking) {
            $color = $this->getStatusColor($booking->status) ?? '#7367f0';
            $customerName = $booking->customer ? $booking->customer->name : 'N/A';
            $customerPhone = $booking->customer ? $booking->customer->phone : '';
            
            // Format: Customer Name Mobile, Start time - End time
            $title = $customerName . ($customerPhone ? ' ' . $customerPhone : '');
            if ($booking->start_date && $booking->end_date) {
                $startTime = $booking->start_date->format('H:i');
                $endTime = $booking->end_date->format('H:i');
                $title .= ', ' . $startTime . ' - ' . $endTime;
            }
            
            // Format dates for FullCalendar
            // FullCalendar accepts ISO8601 strings or Date objects
            $startDate = null;
            $endDate = null;
            
            if ($booking->start_date) {
                $startDate = $booking->start_date->format('Y-m-d\TH:i:s');
            }
            
            if ($booking->end_date) {
                $endDate = $booking->end_date->format('Y-m-d\TH:i:s');
            } else if ($booking->start_date) {
                // If no end date, set it to 1 hour after start date
                $endDate = $booking->start_date->copy()->addHour()->format('Y-m-d\TH:i:s');
            }
            
            $events[] = [
                'id' => (string)$booking->id,
                'title' => $title,
                'start' => $startDate,
                'end' => $endDate,
                'allDay' => false,
                'extendedProps' => [
                    'status' => $booking->status,
                    'customer_id' => $booking->customer_id,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'service_seller_id' => $booking->service_seller_id,
                    'service_seller_name' => $booking->serviceSeller ? $booking->serviceSeller->name : '',
                    'outlet_id' => $booking->outlet_id,
                    'outlet_name' => $booking->outlet ? $booking->outlet->outlet_name : '',
                    'note' => $booking->note ?? '',
                ],
                'backgroundColor' => $this->getStatusColor($booking->status),
                'borderColor' => $this->getStatusColor($booking->status),
                'textColor' => $this->getTextStatusColor($booking->status),
            ];
        }
        
        return response()->json($events);
    }

    /**
     * Store a newly created booking.
     */
    public function store(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'customer_id' => 'required|exists:customers,id',
            'service_seller_id' => 'nullable|exists:users,id',
            'status' => 'required|in:Booked,Waiting,Completed,Cancelled',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string',
            'send_email' => 'nullable|boolean',
        ]);
        
        $companyId = session('company.company_id');
        $userId = auth()->id();
        
        $booking = Booking::create([
            'outlet_id' => $request->outlet_id,
            'customer_id' => $request->customer_id,
            'service_seller_id' => $request->service_seller_id,
            'status' => $request->status,
            'start_date' => Carbon::parse($request->start_date),
            'end_date' => Carbon::parse($request->end_date),
            'note' => $request->note,
            'company_id' => $companyId,
            'user_id' => $userId,
            'del_status' => 'Live',
        ]);
        
        // Send booking confirmation email if requested
        if ($request->send_email && $booking->customer && $booking->customer->email) {
            try {
                app(EmailService::class)->sendEmail(
                    $booking->customer->email,
                    'Booking Confirmation - Ref #' . $booking->id,
                    "Dear {$booking->customer->name},\n\n" .
                    "Your booking has been confirmed.\n\n" .
                    "Booking #: {$booking->id}\n" .
                    "Status: {$booking->status}\n" .
                    "Start: {$booking->start_date}\n" .
                    "End: {$booking->end_date}\n" .
                    "Note: " . ($booking->note ?: '-'),
                    [],
                    'Sale'
                );
            } catch (\Throwable $e) {
                Log::warning('Booking confirmation email failed: ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking' => $booking->load(['customer', 'serviceSeller', 'outlet'])
        ]);
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request, $id)
    {
        $companyId = session('company.company_id');
        
        $booking = Booking::where('id', $id)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->firstOrFail();
        
        // Determine minimum allowed date
        // If booking is in the past, allow editing but don't allow moving to earlier past
        // If booking is today or future, require today or future
        $originalStartDate = Carbon::parse($booking->start_date);
        $today = Carbon::today();
        $minAllowedDate = $originalStartDate->isBefore($today) ? $originalStartDate : $today;
        
        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'customer_id' => 'required|exists:customers,id',
            'service_seller_id' => 'nullable|exists:users,id',
            'status' => 'required|in:Booked,Waiting,Completed,Cancelled',
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($minAllowedDate) {
                    $startDate = Carbon::parse($value);
                    if ($startDate->isBefore($minAllowedDate)) {
                        $fail('The start date cannot be before ' . $minAllowedDate->format('Y-m-d H:i'));
                    }
                }
            ],
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string',
            'send_email' => 'nullable|boolean',
        ]);
        
        $booking->update([
            'outlet_id' => $request->outlet_id,
            'customer_id' => $request->customer_id,
            'service_seller_id' => $request->service_seller_id,
            'status' => $request->status,
            'start_date' => Carbon::parse($request->start_date),
            'end_date' => Carbon::parse($request->end_date),
            'note' => $request->note,
        ]);
        
        // Send booking update email if requested
        if ($request->send_email && $booking->customer && $booking->customer->email) {
            try {
                app(EmailService::class)->sendEmail(
                    $booking->customer->email,
                    'Booking Update - Ref #' . $booking->id,
                    "Dear {$booking->customer->name},\n\n" .
                    "Your booking has been updated.\n\n" .
                    "Booking #: {$booking->id}\n" .
                    "Status: {$booking->status}\n" .
                    "Start: {$booking->start_date}\n" .
                    "End: {$booking->end_date}\n" .
                    "Note: " . ($booking->note ?: '-'),
                    [],
                    'Sale'
                );
            } catch (\Throwable $e) {
                Log::warning('Booking update email failed: ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'booking' => $booking->load(['customer', 'serviceSeller', 'outlet'])
        ]);
    }

    /**
     * Remove the specified booking.
     */
    public function destroy($id)
    {
        $companyId = session('company.company_id');
        
        $booking = Booking::where('id', $id)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->firstOrFail();
        
        $booking->update(['del_status' => 'Deleted']);
        
        return response()->json([
            'success' => true,
            'message' => 'Booking deleted successfully'
        ]);
    }

    /**
     * Get booking list with pagination (AJAX)
     */
    public function getBookingList(Request $request)
    {
        $companyId = session('company.company_id');
        $length = $request->get('length', 10);
        $start = $request->get('start', 0);
        $search = $request->get('search')['value'] ?? '';
        $statusFilter = $request->get('status_filter');
        
        $query = Booking::with(['customer', 'serviceSeller', 'outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        // Get total count before filters
        $recordsTotal = Booking::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();
        
        // Apply status filter
        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        
        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('customer', function($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orWhereHas('serviceSeller', function($sellerQuery) use ($search) {
                    $sellerQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('outlet', function($outletQuery) use ($search) {
                    $outletQuery->where('outlet_name', 'like', "%{$search}%");
                })
                ->orWhere('note', 'like', "%{$search}%");
            });
        }
        
        // Get filtered count
        $recordsFiltered = $query->count();
        
        // Get paginated results
        $bookings = $query->orderBy('start_date', 'desc')
            ->skip($start)
            ->take($length)
            ->get();
        
        $data = [];
        foreach ($bookings as $booking) {
            $data[] = [
                'id' => $booking->id,
                'customer_name' => $booking->customer ? $booking->customer->name : 'N/A',
                'customer_phone' => $booking->customer ? $booking->customer->phone : '',
                'service_seller_name' => $booking->serviceSeller ? $booking->serviceSeller->name : 'N/A',
                'outlet_name' => $booking->outlet ? $booking->outlet->outlet_name : 'N/A',
                'status' => $booking->status,
                'start_date' => $booking->start_date ? $booking->start_date->format('Y-m-d H:i') : '',
                'end_date' => $booking->end_date ? $booking->end_date->format('Y-m-d H:i') : '',
                'note' => $booking->note ?? '',
            ];
        }
        
        return response()->json([
            'draw' => intval($request->get('draw', 1)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }

    /**
     * Get status color for calendar
     */
    private function getStatusColor($status)
    {
        $colors = [
            'Booked' => '#e9e7fd',
            'Waiting' => '#fff0e1',
            'Completed' => '#ddf6e8',
            'Cancelled' => '#ffe2e3'
        ];
        
        return $colors[$status] ?? '#e9e7fd';
    }

    /**
     * Get status color for calendar
     */
    private function getTextStatusColor($status)
    {
        $colors = [
            'Booked' => '#7367f0',
            'Waiting' => '#ff9f43',
            'Completed' => '#28c76f',
            'Cancelled' => '#ff4c51'
        ];
        
        return $colors[$status] ?? '#7367f0';
    }
}
