<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mpesa Payment {{ $success ? 'Success' : 'Failed' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .message {
            margin: 1rem 0;
            font-size: 1.1rem;
        }
        .close-btn {
            margin-top: 1.5rem;
            padding: 0.5rem 1.5rem;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        .close-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($success)
            <div class="icon success">✓</div>
            <h2 style="color: #28a745;">Payment Successful!</h2>
            <p class="message">{{ $message ?: 'Your Mpesa payment has been processed successfully.' }}</p>
            @if(isset($transaction_id))
                <p style="font-size: 0.9rem; color: #666;">Transaction ID: <code>{{ $transaction_id }}</code></p>
            @endif
            @if(isset($checkout_request_id))
                <p style="font-size: 0.9rem; color: #666;">Checkout Request ID: <code>{{ $checkout_request_id }}</code></p>
            @endif
        @else
            <div class="icon error">✗</div>
            <h2 style="color: #dc3545;">Payment {{ $status === 'failed' ? 'Failed' : 'Error' }}</h2>
            <p class="message">{{ $message ?: 'Your Mpesa payment could not be processed.' }}</p>
            @if(isset($transaction_id))
                <p style="font-size: 0.9rem; color: #666;">Transaction ID: <code>{{ $transaction_id }}</code></p>
            @endif
            @if(isset($checkout_request_id))
                <p style="font-size: 0.9rem; color: #666;">Checkout Request ID: <code>{{ $checkout_request_id }}</code></p>
            @endif
        @endif
        
        <button class="close-btn" onclick="window.close(); if (window.opener) { window.opener.location.reload(); }">
            Close Window
        </button>
    </div>

    <script>
        // Notify parent window if this is a popup
        if (window.opener) {
            try {
                window.opener.postMessage({
                    type: 'mpesa_payment_{{ $success ? "success" : "failed" }}',
                    transaction_id: '{{ $transaction_id ?? "" }}',
                    checkout_request_id: '{{ $checkout_request_id ?? "" }}',
                    success: {{ $success ? 'true' : 'false' }},
                    message: '{{ $message ?? "" }}'
                }, '*');
            } catch (e) {
                console.error('Error notifying parent window:', e);
            }
        }

        // Auto-close after 3 seconds if successful
        @if($success)
        setTimeout(function() {
            window.close();
            if (window.opener) {
                window.opener.location.reload();
            }
        }, 3000);
        @endif
    </script>
</body>
</html>
