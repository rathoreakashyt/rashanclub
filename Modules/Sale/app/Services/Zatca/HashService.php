<?php

namespace Modules\Sale\Services\Zatca;

use Illuminate\Support\Facades\Log;

/**
 * Hash Service for ZATCA Invoice Hashing
 * Implements SHA-256 hashing for invoice hash chain
 */
class HashService
{
    /**
     * Generate SHA-256 hash for invoice
     * 
     * @param string $data The data to hash (usually UBL XML)
     * @return string SHA-256 hash in hexadecimal format
     */
    public function generateHash(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Generate invoice hash for ZATCA compliance
     * Format: SHA256(PreviousInvoiceHash + UUID + InvoiceHash)
     * 
     * @param string|null $previousHash Previous invoice hash (null for first invoice)
     * @param string $uuid Invoice UUID
     * @param string $invoiceHash Current invoice hash
     * @return string Combined hash
     */
    public function generateInvoiceHash(?string $previousHash, string $uuid, string $invoiceHash): string
    {
        $previousHash = $previousHash ?? str_repeat('0', 64); // 64 zeros for first invoice
        
        $combined = $previousHash . $uuid . $invoiceHash;
        
        return $this->generateHash($combined);
    }

    /**
     * Get previous invoice hash for a company/outlet
     * 
     * @param int $companyId
     * @param int|null $outletId
     * @return string|null
     */
    public function getPreviousInvoiceHash(int $companyId, ?int $outletId = null): ?string
    {
        $query = \Modules\Sale\Models\InvoiceHashChain::where('company_id', $companyId)
            ->orderBy('chain_index', 'desc')
            ->orderBy('created_at', 'desc');
        
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        
        $lastChain = $query->first();
        
        return $lastChain ? $lastChain->current_hash : null;
    }

    /**
     * Validate hash format (64 character hex string)
     * 
     * @param string $hash
     * @return bool
     */
    public function validateHash(string $hash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
    }
}
