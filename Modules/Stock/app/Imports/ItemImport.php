<?php

namespace Modules\Stock\Imports;

use App\Services\BippService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Validators\Failure;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\ItemCategory;
use Modules\Stock\Models\Brand;
use Modules\Stock\Models\Unit;
use Modules\Purchase\Models\Supplier;

class ItemImport implements
    ToCollection,
    WithHeadingRow,
    SkipsOnFailure
{
    use Importable;
    use SkipsFailures;

    /** Allowed item types (Excel display name => DB value) */
    private const ITEM_TYPES = [
        'General Product'  => 'General_Product',
        'IMEI Product'     => 'IMEI_Product',
        'Serial Product'   => 'Serial_Product',
        'Medicine Product' => 'Medicine_Product',
        'Service Product'  => 'Service_Product',
    ];

    /** Unit type (Excel => DB) */
    private const UNIT_TYPES = [
        'Single Unit' => '1',
        'Double Unit' => '2',
    ];

    /** Attribute to Excel column letter (order matches sample header) */
    private const ATTRIBUTE_COLUMNS = [
        'name' => 'A',
        'code' => 'B',
        'item_type' => 'C',
        'category_name' => 'D',
        'unit_type' => 'E',
        'sale_unit' => 'F',
        'purchase_unit' => 'G',
        'conversion_rate' => 'H',
        'sale_price' => 'I',
        'purchase_price' => 'J',
        'whole_sale_price' => 'K',
        'expiry_date_maintain' => 'L',
        'supplier_name' => 'M',
        'brand_name' => 'N',
        'alternative_name' => 'O',
        'loyalty_point' => 'P',
        'alert_quantity' => 'Q',
        'warranty' => 'R',
        'warranty_date' => 'S',
        'guarantee' => 'T',
        'guarantee_date' => 'U',
        'generic_name' => 'V',
        'mrp_price' => 'W',
        
    ];

    private readonly int $userId;
    private readonly ?int $companyId;

    /** If true, soft-delete existing items for the company before importing (Remove Duplicate Items). */
    private readonly bool $removeDuplicateItems;

    /** If true, hard-delete all items for the company before importing (empty table for company). */
    private readonly bool $removeAllItems;

    private int $processedRows = 0;
    private int $createdRows = 0;
    private int $updatedRows = 0;
    private int $skippedRows = 0;

    /** Track codes seen in file to detect duplicates */
    private array $codesSeenInFile = [];

    /** Current Excel row number (1-based header + data row index) */
    private int $excelRowNumber = 2;

    public function __construct(?int $userId = null, ?int $companyId = null, bool $removeDuplicateItems = false, bool $removeAllItems = false)
    {
        $this->userId = $userId ?? Auth::id() ?? 0;
        $this->companyId = $companyId ?? session('company.company_id');
        $this->removeDuplicateItems = $removeDuplicateItems;
        $this->removeAllItems = $removeAllItems;
    }

    /**
     * All-or-nothing import: validate ALL rows first. If ANY row fails validation,
     * no items are imported. Only when all rows pass do we persist.
     */
    public function collection(Collection $rows): void
    {
        $meaningfulRows = [];
        $rowNumber = 2;

        foreach ($rows as $row) {
            $rowArray = $this->rowToArray($row);
            if (!$this->hasMeaningfulData($rowArray)) {
                $rowNumber++;
                continue;
            }
            $meaningfulRows[] = ['rowArray' => $rowArray, 'excelRowNumber' => $rowNumber];
            $rowNumber++;
        }

        if (empty($meaningfulRows)) {
            return;
        }

        // Phase 1: Validate every row. Collect all failures.
        $validationFailures = [];
        foreach ($meaningfulRows as $item) {
            $rowArray = $item['rowArray'];
            $excelRowNumber = $item['excelRowNumber'];
            $this->processedRows++;

            $validation = $this->validateRow($rowArray, $excelRowNumber);
            if ($validation !== null) {
                $validationFailures[] = new Failure(
                    $excelRowNumber,
                    $validation['attribute'],
                    $validation['errors'],
                    $rowArray
                );
            }
        }

        // If ANY validation failed: record failures and do NOT import anything.
        if (!empty($validationFailures)) {
            foreach ($validationFailures as $failure) {
                $this->onFailure($failure);
            }
            return;
        }

        // All rows valid. Apply pre-import cleanup for this company.
        if ($this->removeAllItems) {
            // Empty the items table for this company (hard delete).
            Item::where('company_id', $this->companyId)->delete();
        } elseif ($this->removeDuplicateItems) {
            // Soft-delete existing items so import can replace/update (Remove Duplicate Items).
            Item::where('company_id', $this->companyId)
                ->where('del_status', 'Live')
                ->update(['del_status' => 'Deleted']);
        }

        // Phase 2: Persist each row.
        foreach ($meaningfulRows as $item) {
            $rowArray = $item['rowArray'];
            $normalized = $this->normalizeRow($rowArray);
            if ($normalized === null) {
                $this->skippedRows++;
                continue;
            }

            $payload = array_merge($normalized, [
                'user_id' => $this->userId ?: null,
                'company_id' => $this->companyId,
                'del_status' => 'Live',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $existing = Item::query()
                ->where('company_id', $this->companyId)
                ->where('code', $payload['code'])
                ->first();

            if ($existing) {
                unset($payload['created_at']);
                $existing->update(array_merge($payload, ['updated_at' => Carbon::now()]));
                $this->updatedRows++;
            } else {
                if (!BippService::canAddProducts(1, $this->companyId)) {
                    throw new \RuntimeException(BippService::productLimitMessage());
                }
                Item::query()->create($payload);
                $this->createdRows++;
            }
        }
    }

    /**
     * Format validation message with row number and column letter.
     */
    private function validationMessage(int $rowNumber, string $attribute, string $message): string
    {
        $column = self::ATTRIBUTE_COLUMNS[$attribute] ?? $attribute;
        return "Row Number {$rowNumber}, Column {$column}, {$message}";
    }

    /**
     * Validate a single row. Returns null if valid, or ['attribute' => ..., 'errors' => [...]] if invalid.
     */
    private function validateRow(array $row, int $rowNumber): ?array
    {
        $name = $this->stringValue($row, 'name');
        $code = $this->stringValue($row, 'code');
        $itemTypeRaw = $this->stringValue($row, 'item_type');
        $categoryName = $this->stringValue($row, 'category_name');
        $unitTypeRaw = $this->stringValue($row, 'unit_type');
        $saleUnitName = $this->stringValue($row, 'sale_unit');
        $purchaseUnitName = $this->stringValue($row, 'purchase_unit');
        $conversionRate = $this->stringValue($row, 'conversion_rate');
        $expiryDateMaintain = $this->stringValue($row, 'expiry_date_maintain');
        $mrpPriceRaw = $this->stringValue($row, 'mrp_price');
        $salePriceRaw = $this->stringValue($row, 'sale_price');
        $purchasePriceRaw = $this->stringValue($row, 'purchase_price');
        $wholeSalePriceRaw = $this->stringValue($row, 'whole_sale_price');

        if ($name === null || $name === '') {
            return ['attribute' => 'name', 'errors' => [$this->validationMessage($rowNumber, 'name', 'The Item Name is required.')]];
        }
        if (strlen($name) > 55) {
            return ['attribute' => 'name', 'errors' => [$this->validationMessage($rowNumber, 'name', 'The Item Name must not exceed 55 characters.')]];
        }

        if ($code === null || $code === '') {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'The Code is required.')]];
        }
        if (strlen($code) > 55) {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'The Code must not exceed 55 characters.')]];
        }
        if (isset($this->codesSeenInFile[$code])) {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'Duplicate code in file. Code must be unique.')]];
        }
        $this->codesSeenInFile[$code] = true;

        $itemType = $this->resolveItemType($itemTypeRaw);
        if ($itemType === null) {
            $allowed = implode(', ', array_keys(self::ITEM_TYPES));
            return ['attribute' => 'item_type', 'errors' => [$this->validationMessage($rowNumber, 'item_type', "The Item Type must be one of: {$allowed}.")]];
        }

        if ($categoryName === null || $categoryName === '') {
            return ['attribute' => 'category_name', 'errors' => [$this->validationMessage($rowNumber, 'category_name', 'The Category Name is required.')]];
        }

        $unitType = $this->resolveUnitType($unitTypeRaw);
        if ($unitType === null) {
            return ['attribute' => 'unit_type', 'errors' => [$this->validationMessage($rowNumber, 'unit_type', 'The Unit Type must be "Single Unit" or "Double Unit".')]];
        }

        if ($unitType === '1') {
            if ($saleUnitName === null || $saleUnitName === '') {
                return ['attribute' => 'sale_unit', 'errors' => [$this->validationMessage($rowNumber, 'sale_unit', 'The Sale Unit is required when Unit Type is Single Unit.')]];
            }
            // Unit is find-or-create; no "not found" validation needed
        } else {
            if ($purchaseUnitName === null || $purchaseUnitName === '') {
                return ['attribute' => 'purchase_unit', 'errors' => [$this->validationMessage($rowNumber, 'purchase_unit', 'The Purchase Unit is required when Unit Type is Double Unit.')]];
            }
            if ($saleUnitName === null || $saleUnitName === '') {
                return ['attribute' => 'sale_unit', 'errors' => [$this->validationMessage($rowNumber, 'sale_unit', 'The Sale Unit is required when Unit Type is Double Unit.')]];
            }
            if ($conversionRate === null || $conversionRate === '' || (float) $conversionRate <= 0) {
                return ['attribute' => 'conversion_rate', 'errors' => [$this->validationMessage($rowNumber, 'conversion_rate', 'The Conversion Rate is required and must be greater than 0 when Unit Type is Double Unit.')]];
            }
            // Sale Unit and Purchase Unit are find-or-create; no "not found" validation needed
        }

        if ($itemType === 'Medicine_Product') {
            $expiryNorm = $this->normalizeYesNo($expiryDateMaintain);
            if ($expiryNorm === null) {
                return ['attribute' => 'expiry_date_maintain', 'errors' => [$this->validationMessage($rowNumber, 'expiry_date_maintain', 'The Expiry Date Maintain is required for Medicine Product. It must be Yes or No.')]];
            }
        }

        // Sale Price is required and must be numeric >= 0
        if ($salePriceRaw === null || $salePriceRaw === '') {
            return ['attribute' => 'sale_price', 'errors' => [$this->validationMessage($rowNumber, 'sale_price', 'The Sale Price is required.')]];
        }
        $salePriceNum = $this->parseDecimal($salePriceRaw);
        if ($salePriceNum === null || $salePriceNum < 0) {
            return ['attribute' => 'sale_price', 'errors' => [$this->validationMessage($rowNumber, 'sale_price', 'The Sale Price must be a number greater than or equal to 0.')]];
        }

        // MRP Price optional; if provided must be numeric >= 0
        if ($mrpPriceRaw !== null && $mrpPriceRaw !== '') {
            $mrpPriceNum = $this->parseDecimal($mrpPriceRaw);
            if ($mrpPriceNum === null || $mrpPriceNum < 0) {
                return ['attribute' => 'mrp_price', 'errors' => [$this->validationMessage($rowNumber, 'mrp_price', 'The MRP Price must be a number greater than or equal to 0.')]];
            }
        }

        // Purchase Price and Whole Sale Price optional; if provided must be numeric >= 0
        if ($purchasePriceRaw !== null && $purchasePriceRaw !== '') {
            $purchasePriceNum = $this->parseDecimal($purchasePriceRaw);
            if ($purchasePriceNum === null || $purchasePriceNum < 0) {
                return ['attribute' => 'purchase_price', 'errors' => [$this->validationMessage($rowNumber, 'purchase_price', 'The Purchase Price must be a number greater than or equal to 0.')]];
            }
        }
        if ($wholeSalePriceRaw !== null && $wholeSalePriceRaw !== '') {
            $wholeSalePriceNum = $this->parseDecimal($wholeSalePriceRaw);
            if ($wholeSalePriceNum === null || $wholeSalePriceNum < 0) {
                return ['attribute' => 'whole_sale_price', 'errors' => [$this->validationMessage($rowNumber, 'whole_sale_price', 'The Whole Sale Price must be a number greater than or equal to 0.')]];
            }
        }

        // Warranty: optional; when provided must be a number (e.g. 1, 2, 3)
        $warrantyRaw = $this->stringValue($row, 'warranty');
        if ($warrantyRaw !== null && $warrantyRaw !== '') {
            $warrantyNum = $this->parseDecimal($warrantyRaw);
            if ($warrantyNum === null || $warrantyNum < 0) {
                return ['attribute' => 'warranty', 'errors' => [$this->validationMessage($rowNumber, 'warranty', 'Warranty must be a number (e.g. 1, 2, 3).')]];
            }
        }

        // Warranty Date: optional; when provided must be one of: day, month, year (unit type, not a calendar date)
        $warrantyDateRaw = $this->stringValue($row, 'warranty_date');
        if ($warrantyDateRaw !== null && $warrantyDateRaw !== '') {
            $warrantyDateNorm = $this->normalizeDayMonthYear($warrantyDateRaw);
            if ($warrantyDateNorm === null) {
                return ['attribute' => 'warranty_date', 'errors' => [$this->validationMessage($rowNumber, 'warranty_date', 'Warranty Date must be one of: day, month, year.')]];
            }
        }

        // Guarantee: optional; when provided must be a number (e.g. 1, 2, 3)
        $guaranteeRaw = $this->stringValue($row, 'guarantee');
        if ($guaranteeRaw !== null && $guaranteeRaw !== '') {
            $guaranteeNum = $this->parseDecimal($guaranteeRaw);
            if ($guaranteeNum === null || $guaranteeNum < 0) {
                return ['attribute' => 'guarantee', 'errors' => [$this->validationMessage($rowNumber, 'guarantee', 'Guarantee must be a number (e.g. 1, 2, 3).')]];
            }
        }

        // Guarantee Date: optional; when provided must be one of: day, month, year (unit type, not a calendar date)
        $guaranteeDateRaw = $this->stringValue($row, 'guarantee_date');
        if ($guaranteeDateRaw !== null && $guaranteeDateRaw !== '') {
            $guaranteeDateNorm = $this->normalizeDayMonthYear($guaranteeDateRaw);
            if ($guaranteeDateNorm === null) {
                return ['attribute' => 'guarantee_date', 'errors' => [$this->validationMessage($rowNumber, 'guarantee_date', 'Guarantee Date must be one of: day, month, year.')]];
            }
        }

        return null;
    }

    /** Normalize day/month/year unit string. Returns "day", "month", "year" or null. */
    private function normalizeDayMonthYear(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim($value));
        if (in_array($v, ['day', 'month', 'year'], true)) {
            return $v;
        }
        return null;
    }

    /**
     * Normalize row to DB payload. Returns null if resolution fails (e.g. unit not found).
     */
    private function normalizeRow(array $row): ?array
    {
        $unitType = $this->resolveUnitType($this->stringValue($row, 'unit_type'));
        $itemType = $this->resolveItemType($this->stringValue($row, 'item_type'));

        $categoryId = $this->resolveCategoryId($this->stringValue($row, 'category_name'));
        if ($categoryId === null) {
            return null;
        }

        $saleUnitName = $this->stringValue($row, 'sale_unit');
        $purchaseUnitName = $this->stringValue($row, 'purchase_unit');
        $saleUnitId = $this->resolveUnitId($saleUnitName);
        $purchaseUnitId = $unitType === '2' ? $this->resolveUnitId($purchaseUnitName) : $saleUnitId;

        if ($saleUnitId === null) {
            return null;
        }
        if ($unitType === '2' && $purchaseUnitId === null) {
            return null;
        }

        $conversionRate = $unitType === '1' ? 1 : (float) $this->stringValue($row, 'conversion_rate');
        if ($unitType === '2' && $conversionRate <= 0) {
            return null;
        }

        $payload = [
            'name' => trim((string) $row['name'] ?? ''),
            'code' => trim((string) $row['code'] ?? ''),
            'type' => $itemType,
            'category_id' => $categoryId,
            'unit_type' => $unitType,
            'sale_unit_id' => $saleUnitId,
            'purchase_unit_id' => $purchaseUnitId,
            'conversion_rate' => (string) $conversionRate,
        ];

        if ($itemType === 'Medicine_Product') {
            $payload['expiry_date_maintain'] = $this->normalizeYesNo($this->stringValue($row, 'expiry_date_maintain')) ?? 'Yes';
        } else {
            $payload['expiry_date_maintain'] = null;
        }

        $payload['supplier_id'] = $this->resolveSupplierId($this->stringValue($row, 'supplier_name'));
        $payload['brand_id'] = $this->resolveBrandId($this->stringValue($row, 'brand_name'));
        $payload['alternative_name'] = $this->stringValue($row, 'alternative_name');
        $payload['loyalty_point'] = $this->stringValue($row, 'loyalty_point');
        $payload['alert_quantity'] = $this->stringValue($row, 'alert_quantity');

        // Warranty: number (e.g. 1, 2, 3) stored as string; empty allowed
        $warrantyRaw = $this->stringValue($row, 'warranty');
        $warrantyNum = $warrantyRaw !== null && $warrantyRaw !== '' ? $this->parseDecimal($warrantyRaw) : null;
        $payload['warranty'] = ($warrantyNum !== null && $warrantyNum >= 0) ? (string) (int) $warrantyNum : null;

        // Warranty Date: one of day, month, year (unit type); store as string
        $payload['warranty_date'] = $this->normalizeDayMonthYear($this->stringValue($row, 'warranty_date'));

        // Guarantee: number (e.g. 1, 2, 3) stored as string; empty allowed
        $guaranteeRaw = $this->stringValue($row, 'guarantee');
        $guaranteeNum = $guaranteeRaw !== null && $guaranteeRaw !== '' ? $this->parseDecimal($guaranteeRaw) : null;
        $payload['guarantee'] = ($guaranteeNum !== null && $guaranteeNum >= 0) ? (string) (int) $guaranteeNum : null;

        // Guarantee Date: one of day, month, year (unit type); store as string
        $payload['guarantee_date'] = $this->normalizeDayMonthYear($this->stringValue($row, 'guarantee_date'));

        $payload['generic_name'] = $this->stringValue($row, 'generic_name');

        // MRP Price, Sale Price (required), Purchase Price, Whole Sale Price
        $mrpPrice = $this->parseDecimal($this->stringValue($row, 'mrp_price'));
        $payload['mrp_price'] = $mrpPrice !== null && $mrpPrice >= 0 ? (string) $mrpPrice : null;
        $salePrice = $this->parseDecimal($this->stringValue($row, 'sale_price'));
        $payload['sale_price'] = $salePrice !== null && $salePrice >= 0 ? (string) $salePrice : '0';
        $purchasePrice = $this->parseDecimal($this->stringValue($row, 'purchase_price'));
        $payload['purchase_price'] = $purchasePrice !== null && $purchasePrice >= 0 ? (string) $purchasePrice : null;
        $wholeSalePrice = $this->parseDecimal($this->stringValue($row, 'whole_sale_price'));
        $payload['whole_sale_price'] = $wholeSalePrice !== null && $wholeSalePrice >= 0 ? (string) $wholeSalePrice : null;
        if ($payload['purchase_price'] !== null) {
            $payload['last_purchase_price'] = $payload['purchase_price'];
            $payload['last_three_purchase_avg'] = $payload['purchase_price'];
        }

        return $payload;
    }

    /** Parse a string to decimal (float) or null if invalid. */
    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (!is_numeric($trimmed)) {
            return null;
        }
        return (float) $trimmed;
    }

    private function resolveItemType(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = trim($value);
        foreach (self::ITEM_TYPES as $excel => $db) {
            if (strcasecmp($excel, $normalized) === 0) {
                return $db;
            }
        }
        return null;
    }

    private function resolveUnitType(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = trim($value);
        foreach (self::UNIT_TYPES as $excel => $db) {
            if (strcasecmp($excel, $normalized) === 0) {
                return $db;
            }
        }
        return null;
    }

    private function normalizeYesNo(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim($value));
        if (in_array($v, ['yes', 'y', '1'], true)) {
            return 'Yes';
        }
        if (in_array($v, ['no', 'n', '0'], true)) {
            return 'No';
        }
        return null;
    }

    /** Find or create category by name. Returns category id or null. */
    private function resolveCategoryId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $name = trim($name);
        $category = ItemCategory::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($category) {
            return $category->id;
        }
        $new = ItemCategory::query()->create([
            'name' => $name,
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'del_status' => 'Live',
        ]);
        return $new->id;
    }

    /** Find or create unit by unit_name. Returns unit id or null if name empty. */
    private function resolveUnitId(?string $unitName): ?int
    {
        if ($unitName === null || $unitName === '') {
            return null;
        }
        $name = trim($unitName);
        $unit = Unit::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->whereRaw('LOWER(unit_name) = ?', [strtolower($name)])
            ->first();
        if ($unit) {
            return $unit->id;
        }
        $new = Unit::query()->create([
            'unit_name' => $name,
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'del_status' => 'Live',
        ]);
        return $new->id;
    }

    /** Find or create supplier by name. */
    private function resolveSupplierId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $name = trim($name);
        $supplier = Supplier::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($supplier) {
            return $supplier->id;
        }
        $new = Supplier::query()->create([
            'name' => $name,
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'del_status' => 'Live',
        ]);
        return $new->id;
    }

    /** Find or create brand by name. */
    private function resolveBrandId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $name = trim($name);
        $brand = Brand::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($brand) {
            return $brand->id;
        }
        $new = Brand::query()->create([
            'name' => $name,
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'del_status' => 'Live',
        ]);
        return $new->id;
    }

    private function hasMeaningfulData(array $row): bool
    {
        return collect($row)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->isNotEmpty();
    }

    private function rowToArray($row): array
    {
        if ($row instanceof Collection) {
            return $row->toArray();
        }
        if (is_array($row)) {
            return $row;
        }
        if (is_object($row) && method_exists($row, 'toArray')) {
            return (array) $row->toArray();
        }
        return (array) $row;
    }

    private function stringValue(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function summary(): array
    {
        return [
            'processed' => $this->processedRows,
            'created' => $this->createdRows,
            'updated' => $this->updatedRows,
            'skipped' => $this->skippedRows + count($this->failures()),
            'failures' => $this->formatFailures($this->failures()),
        ];
    }

    private function formatFailures(iterable $failures): array
    {
        $formatted = [];
        foreach ($failures as $failure) {
            if (!$failure instanceof Failure) {
                continue;
            }
            $formatted[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
        }
        return $formatted;
    }

    public function onFailure(Failure ...$failure): void
    {
        $this->skippedRows += count($failure);
        // Store failures so they are returned in summary() and shown in the view
        $this->failures = array_merge($this->failures, $failure);
    }
}
