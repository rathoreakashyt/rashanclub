<?php

namespace Modules\Stock\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OpeningStockGeneralExport implements FromCollection, WithHeadings
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
            'Stock',
        ];
    }

    /**
     * Export items where type is General_Product or Installment_Product.
     */
    public function collection()
    {
        return \Modules\Stock\Models\Item::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->whereIn('type', ['General_Product', 'Installment_Product'])
            ->orderBy('name')
            ->get(['name', 'code', 'type'])
            ->map(fn ($item) => [$item->name, $item->code, $item->type, '']);
    }
}
