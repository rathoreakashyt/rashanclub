<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? __('Test Email') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            padding: 32px 40px;
            text-align: center;
        }
        .email-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.025em;
        }
        .email-header .company-name {
            font-size: 16px;
            opacity: 0.95;
            font-weight: 500;
        }
        .test-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 16px;
            letter-spacing: 0.02em;
        }
        .email-body {
            padding: 40px 40px 32px;
        }
        .message-content {
            font-size: 15px;
            color: #4b5563;
            margin-bottom: 24px;
        }
        .message-content p {
            margin-bottom: 12px;
        }
        .email-footer {
            background-color: #f9fafb;
            padding: 24px 40px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .email-footer p {
            font-size: 13px;
            color: #6b7280;
            margin: 0;
        }
        .email-footer .thanks {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <h1>{{ __('Test Email') }}</h1>
                <div class="company-name">{{ $companyName ?? config('app.name') }}</div>
                <span class="test-badge">{{ __('Email configuration test') }}</span>
            </div>

            <div class="email-body">
                <div class="message-content">
                    {!! nl2br(e($message_test ?? __('This is a test email to verify your email configuration.'))) !!}
                </div>
            </div>

            <div class="email-footer">
                <p class="thanks">{{ __('Thank you.') }}</p>
                <p>{{ $companyName ?? '' }}</p>
            </div>
        </div>
    </div>
</body>
</html>
