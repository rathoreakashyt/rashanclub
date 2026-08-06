<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceHashChain extends Model
{
    protected $table = 'invoice_hash_chain';

    protected $fillable = [
        'company_id',
        'outlet_id',
        'previous_hash',
        'current_hash',
        'zatca_invoice_id',
        'chain_index',
    ];

    protected $casts = [
        'chain_index' => 'integer',
    ];

    /**
     * Get the ZATCA invoice for this hash chain entry.
     */
    public function zatcaInvoice(): BelongsTo
    {
        return $this->belongsTo(ZatcaInvoice::class, 'zatca_invoice_id');
    }
}
