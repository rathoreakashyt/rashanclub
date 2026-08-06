<?php

namespace Modules\Sale\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\ZatcaInvoice;
use Modules\Sale\Services\Zatca\ZatcaService;
use Illuminate\Support\Facades\Log;

/**
 * Queue Job for Processing ZATCA Invoices
 * Handles offline invoice processing and retries
 */
class ProcessZatcaInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $saleId;
    public $tries;
    public $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(int $saleId, int $tries = 3)
    {
        $this->saleId = $saleId;
        $this->tries = $tries;
        $this->timeout = config('zatca.timeout', 30) + 30; // Add buffer for processing
    }

    /**
     * Execute the job.
     */
    public function handle(ZatcaService $zatcaService): void
    {
        $sale = Sale::find($this->saleId);
        
        if (!$sale) {
            Log::warning('Sale not found for ZATCA processing', [
                'sale_id' => $this->saleId
            ]);
            return;
        }

        // Check if ZATCA invoice already exists
        $zatcaInvoice = ZatcaInvoice::where('sale_id', $sale->id)->first();
        
        try {
            // If invoice doesn't exist, create it first
            if (!$zatcaInvoice) {
                $isOffline = !$this->checkInternetConnection();
                $zatcaInvoice = $zatcaService->processInvoice($sale, $isOffline);
            } else {
                // Invoice exists, just submit to ZATCA if not already submitted
                if ($zatcaInvoice->zatca_status === 'pending' && !$zatcaInvoice->is_offline) {
                    $success = $zatcaService->submitToZatca($zatcaInvoice);
                    
                    if (!$success && $zatcaInvoice->retry_count < $this->tries) {
                        // Retry with exponential backoff
                        $delay = config('zatca.retry_delay', 60) * pow(2, $zatcaInvoice->retry_count);
                        $this->release($delay);
                        return;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error('ZATCA Invoice Job Failed', [
                'sale_id' => $this->saleId,
                'zatca_invoice_id' => $zatcaInvoice?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retry if attempts remaining
            if ($this->attempts() < $this->tries) {
                $delay = config('zatca.retry_delay', 60) * pow(2, $this->attempts());
                $this->release($delay);
            } else {
                // Mark as failed after max retries
                if ($zatcaInvoice) {
                    $zatcaInvoice->update([
                        'zatca_status' => 'failed',
                        'zatca_error' => 'Job failed after ' . $this->tries . ' attempts: ' . $e->getMessage(),
                        'failed_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Check internet connection
     */
    private function checkInternetConnection(): bool
    {
        try {
            $connected = @gethostbyname('www.google.com');
            return $connected !== 'www.google.com';
        } catch (\Exception $e) {
            return true; // Assume online if check fails
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $zatcaInvoice = ZatcaInvoice::where('sale_id', $this->saleId)->first();
        
        if ($zatcaInvoice) {
            $zatcaInvoice->update([
                'zatca_status' => 'failed',
                'zatca_error' => 'Job failed: ' . $exception->getMessage(),
                'failed_at' => now(),
            ]);
        }
        
        Log::error('ZATCA Invoice Job Failed Permanently', [
            'sale_id' => $this->saleId,
            'error' => $exception->getMessage()
        ]);
    }
}
