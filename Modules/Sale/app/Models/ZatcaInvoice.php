<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZatcaInvoice extends Model
{
    protected $table = 'zatca_invoices';

    protected $fillable = [
        'sale_id',
        'company_id',
        'outlet_id',
        'invoice_type',
        'uuid',
        'invoice_hash',
        'previous_invoice_hash',
        'qr_code',
        'zatca_status',
        'zatca_error',
        'cleared_at',
        'reported_at',
        'failed_at',
        'retry_count',
        'last_retry_at',
        'ubl_xml',
        'signed_xml',
        'cryptographic_stamp',
        'packaging_authorized_serial_number',
        'is_offline',
        'queued_at',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
        'reported_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'queued_at' => 'datetime',
        'is_offline' => 'boolean',
        'retry_count' => 'integer',
    ];

    /**
     * Get the sale that owns this ZATCA invoice.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get all ZATCA requests for this invoice.
     */
    public function zatcaRequests(): HasMany
    {
        return $this->hasMany(ZatcaRequest::class, 'zatca_invoice_id');
    }

    /**
     * Get the hash chain entry for this invoice.
     */
    public function hashChain(): BelongsTo
    {
        return $this->belongsTo(InvoiceHashChain::class, 'id', 'zatca_invoice_id');
    }

    /**
     * Check if invoice is cleared.
     */
    public function isCleared(): bool
    {
        return $this->zatca_status === 'cleared';
    }

    /**
     * Check if invoice is reported.
     */
    public function isReported(): bool
    {
        return $this->zatca_status === 'reported';
    }

    /**
     * Check if invoice is pending.
     */
    public function isPending(): bool
    {
        return $this->zatca_status === 'pending';
    }

    /**
     * Check if invoice has failed.
     */
    public function hasFailed(): bool
    {
        return $this->zatca_status === 'failed';
    }
}
