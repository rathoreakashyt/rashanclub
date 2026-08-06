<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Warranty;
use Illuminate\Support\Facades\DB;

class WarrantyRepository
{
    /**
     * Get all warranties for a company.
     */
    public function getAllForCompany(int $companyId, int $perPage = 15)
    {
        return Warranty::with(['customer', 'technician', 'outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a warranty by ID with its relationships.
     */
    public function getByIdWithRelations(int $id)
    {
        return Warranty::with([
            'customer',
            'technician',
            'user',
            'outlet',
            'company'
        ])->find($id);
    }

    /**
     * Create a new warranty.
     */
    public function create(array $data)
    {
        return Warranty::create($data);
    }

    /**
     * Update a warranty.
     */
    public function update(int $id, array $data)
    {
        $warranty = Warranty::findOrFail($id);
        $warranty->update($data);
        return $warranty;
    }

    /**
     * Delete a warranty (soft delete).
     */
    public function delete(int $id)
    {
        $warranty = Warranty::findOrFail($id);
        $warranty->del_status = 'Deleted';
        $warranty->save();
        return $warranty;
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

        $query = Warranty::with(['customer', 'technician'])
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
                  ->orWhere('product_serial_no', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_mobile', 'like', "%{$search}%")
                  ->orWhere('receiving_date', 'like', "%{$search}%")
                  ->orWhere('current_status', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('technician', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = Warranty::where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        if ($outletId) {
            $recordsTotal->where('outlet_id', $outletId);
        }
        $recordsTotal = $recordsTotal->count();

        $filteredCount = $query->count();

        $warranties = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $warranties->map(function ($warranty, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $warranty->id,
                'customer_name' => $warranty->customer_name ?? ($warranty->customer ? $warranty->customer->name : 'N/A'),
                'customer_mobile' => $warranty->customer_mobile ?? ($warranty->customer ? $warranty->customer->phone : 'N/A'),
                'product_name' => $warranty->product_name,
                'product_serial_no' => $warranty->product_serial_no ?? 'N/A',
                'receiving_date' => formatDate($warranty->receiving_date),
                'delivery_date' => $warranty->delivery_date ? formatDate($warranty->delivery_date) : 'N/A',
                'current_status' => $warranty->current_status ?? 'N/A',
                'current_status_label' => $this->getStatusLabel($warranty->current_status ?? ''),
                'technician_name' => $warranty->technician ? $warranty->technician->name : 'N/A',
                'encrypted_id' => $warranty->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }

    /**
     * Get status label by status code
     */
    public function getStatusLabel(string $status): string
    {
        $statuses = [
            'R_F_C' => 'Receive From Customer',
            'S_T_V' => 'Send To Vendor',
            'R_T_V' => 'Receive From Vendor',
            'D_T_C' => 'Delivered To Customer',
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Update warranty status
     */
    public function updateStatus(int $id, string $status)
    {
        $warranty = Warranty::findOrFail($id);
        $warranty->current_status = $status;
        $warranty->save();
        return $warranty;
    }
}
