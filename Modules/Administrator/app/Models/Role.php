<?php

namespace Modules\Administrator\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $appends = [
        'encrypted_id',
    ];

    /**
     * Get the encrypted ID attribute.
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        try {
            $id = decrypt($value);
            return $this->where('id', $id)->first();
        } catch (\Exception $e) {
            return null;
        }
    }
}
