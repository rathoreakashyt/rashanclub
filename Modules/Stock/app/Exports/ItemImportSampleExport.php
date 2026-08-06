<?php

namespace Modules\Stock\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ItemImportSampleExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Name',
            'Code',
            'Item Type',
            'Category Name',
            'Unit Type',
            'Sale Unit',
            'Purchase Unit',
            'Conversion Rate',
            'Expiry Date Maintain',
            'Supplier Name',
            'Brand Name',
            'Alternative Name',
            'Loyalty Point',
            'Alert Quantity',
            'Warranty',
            'Warranty Date',
            'Guarantee',
            'Guarantee Date',
            'Generic Name',
            'MRP Price',
            'Sale Price',
            'Purchase Price',
            'Whole Sale Price',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Sample Item',
                'SMP-001',
                'General Product',
                'Food',
                'Single Unit',
                'PCS',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '1',
                'year',
                '2',
                'month',
                '',
                '110',
                '100',
                '80',
                '90',
            ],
        ];
    }
}
