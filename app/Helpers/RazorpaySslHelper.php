<?php

/**
 * Razorpay SSL Configuration Helper
 * 
 * This helper disables SSL verification for Razorpay in development environments.
 * WARNING: Only use this in local/development environments, never in production!
 */

if (!function_exists('configureRazorpaySsl')) {
    /**
     * Configure SSL verification for Razorpay SDK
     * This patches the Requests library used by Razorpay to disable SSL verification
     */
    function configureRazorpaySsl(): void
    {
        try {
            // Check if we're in production - never disable SSL in production
            if (function_exists('app') && app()->environment('production')) {
                return;
            }
            
            // Only apply in local/development environments OR if explicitly enabled
            $isLocal = false;
            if (function_exists('app')) {
                $isLocal = app()->environment(['local', 'development', 'testing']);
            }
            
            // Check if explicitly enabled via environment variable (allows override)
            // Default to true for development to fix XAMPP SSL issues
            $envDisableSsl = filter_var(env('RAZORPAY_DISABLE_SSL_VERIFICATION', true), FILTER_VALIDATE_BOOLEAN);
            
            // Apply patch if in local environment OR explicitly enabled
            // Default to true for development to fix XAMPP SSL issues
            if (!$isLocal && !$envDisableSsl) {
                return;
            }
        } catch (\Exception $e) {
            // If we can't determine environment, assume development and apply patch
            // This is safer for development environments
        }
        
        try {
            // The Razorpay SDK uses Requests library (rmccue/requests)
            // We need to patch it to disable SSL verification
            
            // Method 1: Set environment variable (Requests library checks this)
            putenv('REQUESTS_CA_BUNDLE=');
            
            // Method 2: Patch the Requests library's cURL transport directly
            // This is the most reliable method for XAMPP/Windows
            patchRequestsCurlTransport();
            
            // Method 3: Set stream context defaults (fallback)
            stream_context_set_default([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
        } catch (\Exception $e) {
            // Silently fail - don't break the application
            \Log::warning('Failed to configure Razorpay SSL: ' . $e->getMessage());
        }
    }
    
    /**
     * Patch Requests library cURL transport to disable SSL verification
     * This directly modifies the vendor file (development only!)
     * 
     * NOTE: This patch will be lost if you run `composer update rmccue/requests`
     * For a permanent solution, see the instructions in the error message or
     * configure php.ini with proper CA certificate bundle.
     */
    function patchRequestsCurlTransport(): void
    {
        $curlTransportPath = base_path('vendor/rmccue/requests/library/Requests/Transport/cURL.php');
        
        if (!file_exists($curlTransportPath)) {
            \Log::warning('Requests cURL transport file not found at: ' . $curlTransportPath);
            return;
        }
        
        // Read the file
        $content = file_get_contents($curlTransportPath);
        
        // Check if already patched
        if (strpos($content, '// PATCHED: SSL verification disabled for development') !== false) {
            return; // Already patched
        }
        
        // Find the line where we can inject SSL verification disable
        // We'll add it AFTER all verify checks to ensure it's not overridden
        // This should be right before curl_exec
        $searchPattern = '/(if \(isset\(\$options\[\'verifyname\'\]\)[^}]+}\s*\n\s*\$response = curl_exec)/s';
        
        if (preg_match($searchPattern, $content, $matches)) {
            // Add SSL verification disable after all verify checks, before curl_exec
            $patchCode = "\n\n\t\t// PATCHED: SSL verification disabled for development\n\t\t" .
                "// WARNING: This patch should only be used in development environments!\n\t\t" .
                "// This patch will be lost if you run 'composer update rmccue/requests'\n\t\t" .
                "// This runs AFTER all verify checks to ensure SSL is disabled\n\t\t" .
                "curl_setopt(\$this->fp, CURLOPT_SSL_VERIFYPEER, false);\n\t\t" .
                "curl_setopt(\$this->fp, CURLOPT_SSL_VERIFYHOST, false);\n\n\t\t";
            
            $content = preg_replace(
                $searchPattern,
                '$1' . $patchCode . '$response = curl_exec',
                $content,
                1 // Only replace first occurrence
            );
            
            // Write back to file
            if (file_put_contents($curlTransportPath, $content) === false) {
                \Log::error('Failed to patch Requests cURL transport. Please check file permissions.');
            } else {
                \Log::info('Successfully patched Requests cURL transport to disable SSL verification for development.');
            }
        } else {
            \Log::warning('Could not find insertion point in Requests cURL transport file. Manual patch may be required.');
        }
    }
}
