<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'transfers';

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['encrypted_id'];

    /**
     * Get the encrypted ID attribute.
     */
    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    /**
     * Get the from outlet for the transfer.
     */
    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class, 'from_outlet_id');
    }

    /**
     * Get the to outlet for the transfer.
     */
    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class, 'to_outlet_id');
    }

    /**
     * Get the user who created the transfer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the outlet for the transfer.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Outlet::class);
    }

    /**
     * Get the company for the transfer.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Configuration\Models\Company::class);
    }

    /**
     * Get the transfer details for the transfer.
     */
    public function transferDetails(): HasMany
    {
        return $this->hasMany(TransferDetail::class);
    }

    /**
     * Scope a query to only include live records.
     */
    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope a query to only include records for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

