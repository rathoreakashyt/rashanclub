<?php

namespace Modules\Configuration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Modules\Sale\Models\Customer;
use Modules\Configuration\Services\EmailService;
use Illuminate\Support\Facades\Log;

class EmailMarketingController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Base query for customers in current company with valid email.
     */
    protected function baseCustomerQuery()
    {
        $companyId = session('company.company_id');
        return Customer::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereNotNull('email')
            ->where('email', '!=', '');
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
     * GET /marketing/email/stats — counts for birthday today, anniversary today, eligible (with email).
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
                'email' => [
                    'birthday' => $birthdayCount,
                    'anniversary' => $anniversaryCount,
                    'eligible' => $eligibleCount,
                ],
            ],
        ]);
    }

    /**
     * GET /marketing/email/recipients — list of customers with email for multi-select (searchable).
     */
    public function recipients(Request $request)
    {
        $query = $this->baseCustomerQuery()
            ->select('id', 'name', 'email')
            ->orderBy('name');

        $search = $request->get('q');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $customers = $query->get();
        $data = $customers->map(function ($c) {
            return [
                'id' => (string) $c->id,
                'value' => (string) $c->id,
                'title' => $c->name . ' (' . ($c->email ?? '') . ')',
                'email' => $c->email,
                'name' => $c->name,
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * GET /marketing/email/recommended-message/{type} — default subject/message for birthday or anniversary.
     */
    public function recommendedMessage(string $type)
    {
        $type = strtolower($type);
        if ($type === 'birthday') {
            $subject = __('Happy Birthday!');
            $message = __("We're wishing you a very happy birthday! Thank you for being a valued customer.");
        } elseif ($type === 'anniversary') {
            $subject = __('Happy Anniversary!');
            $message = __("Congratulations on your anniversary! We appreciate your continued support.");
        } else {
            $subject = '';
            $message = '';
        }
        return response()->json([
            'status' => 'success',
            'data' => ['subject' => $subject, 'message' => $message],
        ]);
    }

    /**
     * POST /marketing/email/send/birthday — send email to customers with birthday today.
     */
    public function sendBirthday(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
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

        $subject = $request->input('subject');
        $message = $request->input('message');
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $customer) {
            $result = $this->emailService->sendEmail(
                $customer->email,
                $subject,
                $message,
                [],
                'Birthday',
                null,
                ['customerName' => $customer->name ?? '']
            );
            if (($result['status'] ?? '') === 'Success') {
                $sent++;
            } else {
                $failed++;
                Log::warning('Birthday email failed', ['customer_id' => $customer->id, 'result' => $result]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Birthday emails sent: :sent. Failed: :failed.', ['sent' => $sent, 'failed' => $failed]),
            'data' => ['sent' => $sent, 'failed' => $failed],
        ]);
    }

    /**
     * POST /marketing/email/send/anniversary — send email to customers with anniversary today.
     */
    public function sendAnniversary(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
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

        $subject = $request->input('subject');
        $message = $request->input('message');
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $customer) {
            $result = $this->emailService->sendEmail(
                $customer->email,
                $subject,
                $message,
                [],
                'Anniversary',
                null,
                ['customerName' => $customer->name ?? '']
            );
            if (($result['status'] ?? '') === 'Success') {
                $sent++;
            } else {
                $failed++;
                Log::warning('Anniversary email failed', ['customer_id' => $customer->id, 'result' => $result]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Anniversary emails sent: :sent. Failed: :failed.', ['sent' => $sent, 'failed' => $failed]),
            'data' => ['sent' => $sent, 'failed' => $failed],
        ]);
    }

    /**
     * POST /marketing/email/send/custom — send email to selected customers or all.
     */
    public function sendCustom(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'selected_customers' => 'nullable', // can be JSON string or array
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
                'message' => __('No valid recipients with email address.'),
            ], 422);
        }

        $subject = $request->input('subject');
        $message = $request->input('message');
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $customer) {
            $result = $this->emailService->sendEmail(
                $customer->email,
                $subject,
                $message,
                [],
                'Customer',
                null,
                ['customerName' => $customer->name ?? '']
            );
            if (($result['status'] ?? '') === 'Success') {
                $sent++;
            } else {
                $failed++;
                Log::warning('Custom marketing email failed', ['customer_id' => $customer->id, 'result' => $result]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Custom emails sent: :sent. Failed: :failed.', ['sent' => $sent, 'failed' => $failed]),
            'data' => ['sent' => $sent, 'failed' => $failed],
        ]);
    }
}
