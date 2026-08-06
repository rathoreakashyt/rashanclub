<?php

namespace Modules\Accounting\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Accounting\Models\PaymentMethod;

class PaymentMethodRepository
{
    public function __construct(private PaymentMethod $model)
    {
    }

    /**
     * Base query scoped to the current company and live records.
     */
    protected function baseQuery()
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('status', 'Enable')
            ->where('account_type', '!=', 'Loyalty Point')
            ->where('company_id', session('company.company_id'));
    }

    /**
     * List payment methods for select inputs.
     */
    public function listForSelect(): Collection
    {
        return $this->baseQuery()
            ->select('id', 'name')
            ->orderBy('sort_id')
            ->get();
    }
}


