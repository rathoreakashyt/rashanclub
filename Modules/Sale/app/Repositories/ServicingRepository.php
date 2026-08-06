<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Servicing;
use Illuminate\Support\Facades\DB;

class ServicingRepository
{
    /**
     * Get all servicings for a company.
     */
    public function getAllForCompany(int $companyId, int $perPage = 15)
    {
        return Servicing::with(['customer', 'employee', 'outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a servicing by ID with its relationships.
     */
    public function getByIdWithRelations(int $id)
    {
        return Servicing::with([
            'customer',
            'employee',
            'user',
            'outlet',
            'company',
            'paymentMethod'
        ])->find($id);
    }

    /**
     * Create a new servicing.
     */
    public function create(array $data)
    {
        return Servicing::create($data);
    }

    /**
     * Update a servicing.
     */
    public function update(int $id, array $data)
    {
        $servicing = Servicing::findOrFail($id);
        $servicing->update($data);
        return $servicing;
    }

    /**
     * Delete a servicing (soft delete).
     */
    public function delete(int $id)
    {
        $servicing = Servicing::findOrFail($id);
        $servicing->del_status = 'Deleted';
        $servicing->save();
        return $servicing;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';
        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');

        $query = Servicing::with(['customer', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc');

        // Filter by outlet if set
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_model', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('employee', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = Servicing::where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        if ($outletId) {
            $recordsTotal->where('outlet_id', $outletId);
        }
        $recordsTotal = $recordsTotal->count();

        $filteredCount = $query->count();

        $servicings = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $servicings->map(function ($servicing, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $servicing->id,
                'date' => formatDate($servicing->date),
                'customer_name' => $servicing->customer ? $servicing->customer->name : 'N/A',
                'product_name' => $servicing->product_name,
                'product_model' => $servicing->product_model ?? 'N/A',
                'receiving_date' => formatDate($servicing->receiving_date),
                'delivery_date' => $servicing->delivery_date ? formatDate($servicing->delivery_date) : 'N/A',
                'servicing_charge' => formatAmount($servicing->servicing_charge),
                'paid_amount' => formatAmount($servicing->paid_amount),
                'due_amount' => formatAmount($servicing->due_amount),
                'status' => $servicing->status ?? 'N/A',
                'employee_name' => $servicing->employee ? $servicing->employee->name : 'N/A',
                'encrypted_id' => $servicing->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }
}
