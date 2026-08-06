<?php

namespace Modules\Configuration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Modules\Sale\Models\Customer;
use Modules\Configuration\Services\SMSService;
use Illuminate\Support\Facades\Log;

class SmsMarketingController extends Controller
{
    protected SMSService $smsService;

    public function __construct(SMSService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Base query for customers in current company with valid phone.
     */
    protected function baseCustomerQuery()
    {
        $companyId = session('company.company_id');
        return Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');
    }

    /**
     * Parse date string (supports Y-m-d, d-m-Y, d/m/Y) and return month-day or null.
     */
    protected function parseMonthDay(?string $dateStr): ?string
    {
        if (empty($dateStr) || trim($dateStr) === '') {
            return null;
        }
        $dateStr = trim($dateStr);
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateStr);
                return $date->format('m-d');
            } catch (\Exception $e) {
                continue;
            }
        }
        try {
            $date = new Carbon($dateStr);
            return $date->format('m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * GET /marketing/sms/stats — counts for birthday today, anniversary today, eligible (with phone).
     */
    public function stats()
    {
        $todayMd = Carbon::now()->format('m-d');
        $query = $this->baseCustomerQuery();

        $birthdayCount = (clone $query)->get()->filter(function ($c) use ($todayMd) {
            return $this->parseMonthDay($c->date_of_birth) === $todayMd;
        })->count();

        $anniversaryCount = (clone $query)->get()->filter(function ($c) use ($todayMd) {
            return $this->parseMonthDay($c->date_of_anniversary) === $todayMd;
        })->count();

        $eligibleCount = (clone $query)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'sms' => [
                    'birthday' => $birthdayCount,
                    'anniversary' => $anniversaryCount,
                    'eligible' => $eligibleCount,
                ],
            ],
        ]);
    }

    /**
     * GET /marketing/sms/recipients — list of customers with phone for multi-select (searchable).
     */
    public function recipients(Request $request)
    {
        $query = $this->baseCustomerQuery()
            ->select('id', 'name', 'phone')
            ->orderBy('name');

        $search = $request->get('q');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $customers = $query->get();
        $data = $customers->map(function ($c) {
            return [
                'id' => (string) $c->id,
                'value' => (string) $c->id,
                'title' => $c->name . ' (' . ($c->phone ?? '') . ')',
                'phone' => $c->phone,
                'name' => $c->name,
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * GET /marketing/sms/recommended-message/{type} — default message for birthday or anniversary.
     */
    public function recommendedMessage(string $type)
    {
        $type = strtolower($type);
        if ($type === 'birthday') {
            $message = __("Happy Birthday! We're wishing you a wonderful day. Thank you for being a valued customer.");
        } elseif ($type === 'anniversary') {
            $message = __("Congratulations on your anniversary! We appreciate your continued support.");
        } else {
            $message = '';
        }
        return response()->json([
            'status' => 'success',
            'data' => ['message' => $message],
        ]);
    }

    /**
     * POST /marketing/sms/send/birthday — send SMS to customers with birthday today.
     */
    public function sendBirthday(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $todayMd = Carbon::now()->format('m-d');
        $recipients = $this->baseCustomerQuery()->get()->filter(function ($c) use ($todayMd) {
            return $this->parseMonthDay($c->date_of_birth) === $todayMd;
        });

        if ($recipients->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => __('No customers with birthday today.'),
            ], 422);
        }

        $message = $request->input('message');
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $customer) {
            $result = $this->smsService->sendSMS($customer->phone, $message, []);
            if (($result['status'] ?? '') === 'Success') {
                $sent++;
            } else {
                $failed++;
                Log::warning('Birthday SMS failed', ['customer_id' => $customer->id, 'result' => $result]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Birthday SMS sent: :sent. Failed: :failed.', ['sent' => $sent, 'failed' => $failed]),
            'data' => ['sent' => $sent, 'failed' => $failed],
        ]);
    }

    /**
     * POST /marketing/sms/send/anniversary — send SMS to customers with anniversary today.
     */
    public function sendAnniversary(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $todayMd = Carbon::now()->format('m-d');
        $recipients = $this->baseCustomerQuery()->get()->filter(function ($c) use ($todayMd) {
            return $this->parseMonthDay($c->date_of_anniversary) === $todayMd;
        });

        if ($recipients->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => __('No customers with anniversary today.'),
            ], 422);
        }

        $message = $request->input('message');
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $customer) {
            $result = $this->smsService->sendSMS($customer->phone, $message, []);
            if (($result['status'] ?? '') === 'Success') {
                $sent++;
            } else {
                $failed++;
                Log::warning('Anniversary SMS failed', ['customer_id' => $customer->id, 'result' => $result]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Anniversary SMS sent: :sent. Failed: :failed.', ['sent' => $sent, 'failed' => $failed]),
            'data' => ['sent' => $sent, 'failed' => $failed],
        ]);
    }

    /**
     * POST /marketing/sms/send/custom — send SMS to selected customers or all.
     */
    public function sendCustom(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'selected_customers' => 'nullable',
        ]);

        $selected = $request->input('selected_customers');
        if (is_string($selected)) {
            $selected = json_decode($selected, true) ?: [];
        }
        $selected = is_array($selected) ? $selected : [];

        $sendToAll = in_array('all_customers', $selected);
        $recipients = collect();

        if ($sendToAll) {
            $recipients = $this->baseCustomerQuery()->get();
        } else {
            $ids = array_filter(array_map('intval', $selected));
            if (empty($ids)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Please select at least one recipient.'),
                    'errors' => ['selected_customers' => [__('Please select at least one recipient.')]],
                ], 422);
            }
            $recipients = $this->baseCustomerQuery()->whereIn('id', $ids)->get();
        }

        if ($recipients->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => __('No valid recipients with phone number.'),
            ], 422);
        }

        $message = $request->input('message');
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $customer) {
            $result = $this->smsService->sendSMS($customer->phone, $message, []);
            if (($result['status'] ?? '') === 'Success') {
                $sent++;
            } else {
                $failed++;
                Log::warning('Custom marketing SMS failed', ['customer_id' => $customer->id, 'result' => $result]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Custom SMS sent: :sent. Failed: :failed.', ['sent' => $sent, 'failed' => $failed]),
            'data' => ['sent' => $sent, 'failed' => $failed],
        ]);
    }
}
