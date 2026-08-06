<?php

namespace Modules\Stock\Imports;

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
use Modules\Stock\Models\SetOpeningStock;

class OpeningStockMedicineImport implements ToCollection, WithHeadingRow, SkipsOnFailure
{
    use Importable;
    use SkipsFailures;

    private readonly int $userId;
    private readonly ?int $companyId;
    private readonly int $outletId;

    private int $processedRows = 0;
    private int $createdRows = 0;
    private int $skippedRows = 0;

    private const ATTRIBUTE_COLUMNS = [
        'name' => 'A',
        'code' => 'B',
        'item_type' => 'C',
        'expiry_date_maintain' => 'D',
        'stock' => 'E',
    ];

    public function __construct(?int $userId = null, ?int $companyId = null, int $outletId)
    {
        $this->userId = $userId ?? Auth::id() ?? 0;
        $this->companyId = $companyId ?? session('company.company_id');
        $this->outletId = $outletId;
    }

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

        if (!empty($validationFailures)) {
            foreach ($validationFailures as $failure) {
                $this->onFailure($failure);
            }
            return;
        }

        foreach ($meaningfulRows as $item) {
            $rowArray = $item['rowArray'];
            $payloads = $this->normalizeRow($rowArray);
            if ($payloads === null || empty($payloads)) {
                $this->skippedRows++;
                continue;
            }

            foreach ($payloads as $payload) {
                SetOpeningStock::create(array_merge($payload, [
                    'user_id' => $this->userId ?: null,
                    'company_id' => $this->companyId,
                    'outlet_id' => $this->outletId,
                ]));
                $this->createdRows++;
            }
        }
    }

    private function validationMessage(int $rowNumber, string $attribute, string $message): string
    {
        $column = self::ATTRIBUTE_COLUMNS[$attribute] ?? $attribute;
        return "Row Number {$rowNumber}, Column {$column}, {$message}";
    }

    private function normalizeYesNo(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['yes', 'y', '1'], true)) {
            return 'Yes';
        }
        if (in_array($v, ['no', 'n', '0'], true)) {
            return 'No';
        }
        return null;
    }

    private function validateRow(array $row, int $rowNumber): ?array
    {
        $name = $this->stringValue($row, 'name');
        $code = $this->stringValue($row, 'code');
        $expiryMaintain = $this->normalizeYesNo(
            $this->stringValue($row, 'expiry_date_maintain') ?? $this->stringValue($row, 'expiry date maintain')
        );
        $stockRaw = $this->stringValue($row, 'stock');

        if ($name === null || $name === '') {
            return ['attribute' => 'name', 'errors' => [$this->validationMessage($rowNumber, 'name', 'Name is required.')]];
        }
        if ($code === null || $code === '') {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'Code is required.')]];
        }

        $item = Item::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->where('code', $code)
            ->first();

        if (!$item) {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'Item with this code not found.')]];
        }
        if ($item->type !== 'Medicine_Product') {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'Item must be Medicine Product.')]];
        }
        if ($expiryMaintain === null) {
            return ['attribute' => 'expiry_date_maintain', 'errors' => [$this->validationMessage($rowNumber, 'expiry_date_maintain', 'Expiry Date Maintain must be Yes or No.')]];
        }

        // Empty stock: skip row (no validation error)
        if ($stockRaw === null || $stockRaw === '') {
            return null;
        }

        if ($expiryMaintain === 'Yes') {
            // Stock format: qty/YYYY-MM-DD, qty/YYYY-MM-DD (comma separates pairs)
            $pairs = $this->parseQuantityExpiryPairs($stockRaw);
            if ($pairs === null) {
                return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'Stock must be in format quantity/expiry_date (e.g. 12/2026-12-30, 12/2026-11-30).')]];
            }
            foreach ($pairs as $pair) {
                if ($pair['qty'] < 0) {
                    return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'Quantity must be greater than or equal to 0.')]];
                }
                if (!$this->isValidDate($pair['date'])) {
                    return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'Invalid expiry date: ' . $pair['date'] . '. Use YYYY-MM-DD.')]];
                }
            }
        } else {
            $stockNum = $this->parseDecimal($stockRaw);
            if ($stockNum === null || $stockNum < 0) {
                return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'Stock must be a number greater than or equal to 0.')]];
            }
        }

        return null;
    }

    /**
     * Parse Stock column when Expiry Date Maintain = Yes.
     * Format: "qty/YYYY-MM-DD, qty/YYYY-MM-DD" (comma separates pairs).
     * @return array<int, array{qty: float, date: string}>|null
     */
    private function parseQuantityExpiryPairs(string $value): ?array
    {
        $parts = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            // Split by "/" — first part is qty, rest is date (YYYY-MM-DD may contain no extra /)
            $slashPos = strpos($part, '/');
            if ($slashPos === false) {
                return null;
            }
            $qtyStr = trim(substr($part, 0, $slashPos));
            $dateStr = trim(substr($part, $slashPos + 1));
            if ($qtyStr === '' || $dateStr === '' || !is_numeric($qtyStr)) {
                return null;
            }
            $result[] = ['qty' => (float) $qtyStr, 'date' => $dateStr];
        }
        return empty($result) ? null : $result;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = Carbon::createFromFormat('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * @return array<int, array{item_id: int, item_type: string, item_description: string|null, stock_quantity: float}>|null
     */
    private function normalizeRow(array $row): ?array
    {
        $code = $this->stringValue($row, 'code');
        $expiryMaintain = $this->normalizeYesNo(
            $this->stringValue($row, 'expiry_date_maintain') ?? $this->stringValue($row, 'expiry date maintain')
        );
        $stockRaw = $this->stringValue($row, 'stock');

        if ($stockRaw === null || $stockRaw === '') {
            return null;
        }

        $item = Item::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->where('code', $code)
            ->first();

        if (!$item || $item->type !== 'Medicine_Product') {
            return null;
        }

        $conversionRate = (float) ($item->conversion_rate ?? 1);

        if ($expiryMaintain === 'Yes') {
            $pairs = $this->parseQuantityExpiryPairs($stockRaw);
            if ($pairs === null || empty($pairs)) {
                return null;
            }
            $payloads = [];
            foreach ($pairs as $pair) {
                if ($pair['qty'] < 0 || !$this->isValidDate($pair['date'])) {
                    continue;
                }
                $payloads[] = [
                    'item_id' => $item->id,
                    'item_type' => $item->type,
                    'item_description' => $pair['date'],
                    'stock_quantity' => (float) $pair['qty'] * $conversionRate,
                ];
            }
            return empty($payloads) ? null : $payloads;
        }

        // Expiry Date Maintain = No: single quantity
        $stockNum = $this->parseDecimal($stockRaw);
        if ($stockNum === null || $stockNum < 0) {
            return null;
        }
        return [
            [
                'item_id' => $item->id,
                'item_type' => $item->type,
                'item_description' => null,
                'stock_quantity' => (float) $stockNum * $conversionRate,
            ],
        ];
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            return null;
        }
        return (float) $trimmed;
    }

    private function hasMeaningfulData(array $row): bool
    {
        return collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
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
        $this->failures = array_merge($this->failures, $failure);
    }
}
