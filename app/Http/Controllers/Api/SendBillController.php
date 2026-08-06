<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Configuration\Services\EmailService;
use Modules\Configuration\Services\SMSService;

class SendBillController extends Controller
{
    /**
     * GET /api/send-bill/settings
     * Channel enable/disable status for the POS "Send Bill" popup.
     * No secrets are exposed - only flags.
     */
    public function settings(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()->company_id ?? 1);
        $company = DB::table('companies')->where('id', $companyId)->first();

        $enabled = fn ($value) => strtolower((string) $value) === 'enable';

        return response()->json([
            'whatsapp_enabled' => $enabled($company->whatsapp_invoice_enable_status ?? ''),
            'sms_enabled'      => $enabled($company->sms_enable_status ?? ''),
            'email_enabled'    => $enabled($company->smtp_enable_status ?? ''),
            'company_name'     => $company->name ?? '',
        ]);
    }

    /**
     * POST /api/send-bill
     * Body: { channel: 'sms'|'email', to, subject?, message }
     * Sends via the providers configured on the /setting page (companies table).
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:sms,email',
            'to'      => 'required|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            if ($validated['channel'] === 'sms') {
                $result = app(SMSService::class)->sendSMS($validated['to'], $validated['message']);
            } else {
                $result = app(EmailService::class)->sendEmail(
                    $validated['to'],
                    $validated['subject'] ?? 'Your Bill',
                    $validated['message'],
                    [],
                    'Sale'
                );
            }

            $ok = is_array($result) && isset($result['status']) && strtolower($result['status']) === 'success';
            return response()->json([
                'success' => $ok,
                'message' => $result['message'] ?? 'Unknown response',
            ], $ok ? 200 : 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
