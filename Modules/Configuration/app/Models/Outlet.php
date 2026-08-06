<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['state_code'];

    /**
     * Get the state associated with the outlet.
     */
    public function state()
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Get state_code from the state relation (for backward compatibility with GstTaxService, POS).
     */
    public function getStateCodeAttribute(): ?string
    {
        return $this->state?->state_code;
    }

    /**
     * Get the counters associated with the outlet.
     */
    public function counters()
    {
        return $this->hasMany(Counter::class, 'outlet_id');
    }

    /**
     * Get the user who created this outlet
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}

