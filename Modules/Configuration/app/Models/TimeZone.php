<?php

namespace Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

class TimeZone extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected $table = 'time_zones';
}

