<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaRequest extends Model
{
    protected $table = 'zatca_requests';

    protected $fillable = [
        'zatca_invoice_id',
        'company_id',
        'request_type',
        'request_payload',
        'request_url',
        'request_method',
        'request_headers',
        'response_status_code',
        'response_body',
        'response_headers',
        'status',
        'error_message',
        'error_code',
        'requested_at',
        'responded_at',
        'response_time_ms',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'response_time_ms' => 'integer',
        'response_status_code' => 'integer',
    ];

    /**
     * Get the ZATCA invoice that owns this request.
     */
    public function zatcaInvoice(): BelongsTo
    {
        return $this->belongsTo(ZatcaInvoice::class, 'zatca_invoice_id');
    }
}
