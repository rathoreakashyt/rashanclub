<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? 'Test Email' }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px;">
        <h2 style="color: #333; margin-top: 0;">{{ $companyName ?? 'Company' }}</h2>
        <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
        
        <div style="background-color: #fff; padding: 20px; border-radius: 5px; margin-top: 20px;">
            <h3 style="color: #333; margin-top: 0;">Test Email</h3>
            <p style="margin: 15px 0;">{{ $message_test ?? '' }}</p>
        </div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
            <p>This is a test email to verify your email configuration.</p>
        </div>
    </div>
</body>
</html>
