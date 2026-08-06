<?php

namespace Modules\Stock\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OpeningStockMedicineExport implements FromCollection, WithHeadings
{
    protected $companyId;

    public function __construct(?int $companyId = null)
    {
        $this->companyId = $companyId ?? session('company.company_id');
    }

    public function headings(): array
    {
        return [
            'Name',
            'Code',
            'Item Type',
            'Expiry Date Maintain',
            'Stock',
        ];
    }

    /**
     * Export items where type is Medicine_Product.
     * Name, Code, Item Type, Expiry Date Maintain are pre-filled from database.
     * Stock column is empty: if Expiry Date Maintain = Yes fill as "qty/YYYY-MM-DD, qty/YYYY-MM-DD"; else fill quantity only.
     */
    public function collection()
    {
        return \Modules\Stock\Models\Item::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->where('type', 'Medicine_Product')
            ->orderBy('name')
            ->get(['name', 'code', 'type', 'expiry_date_maintain'])
            ->map(fn ($item) => [
                $item->name,
                $item->code,
                $item->type,
                $item->expiry_date_maintain ?? 'Yes',
                '',
            ]);
    }
}
