<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Sale;
use Illuminate\Support\Facades\DB;

class SaleRepository
{
    /**
     * Get all sales for a company.
     */
    public function getAllForCompany(int $companyId, int $perPage = 15)
    {
        return Sale::with(['customer', 'employee', 'outlet'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a sale by ID with its details.
     */
    public function getByIdWithDetails(int $id)
    {
        return Sale::with([
            'customer',
            'employee',
            'user',
            'outlet',
            'company',
            'saleDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'saleDetails.item',
            'salePayments' => function($query) {
                $query->where('del_status', 'Live');
            },
            'salePayments.paymentMethod'
        ])->find($id);
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

        $query = Sale::with(['customer', 'employee'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc');

        // Filter by outlet if set
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('sale_no', 'like', "%{$search}%")
                  ->orWhere('sale_date', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('employee', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId);
        
        if ($outletId) {
            $recordsTotal->where('outlet_id', $outletId);
        }
        $recordsTotal = $recordsTotal->count();

        $filteredCount = $query->count();

        $sales = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $sales->map(function ($sale, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $sale->id,
                'sale_no' => $sale->sale_no,
                'customer_name' => $sale->customer ? $sale->customer->name : 'Walk-in Customer',
                'employee_name' => $sale->employee ? $sale->employee->name : null,
                'sale_date' => formatDate($sale->sale_date),
                'grand_total' => formatAmount($sale->grand_total),
                'paid_amount' => formatAmount($sale->paid_amount),
                'due_amount' => formatAmount($sale->due_amount),
                'encrypted_id' => $sale->encrypted_id
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
