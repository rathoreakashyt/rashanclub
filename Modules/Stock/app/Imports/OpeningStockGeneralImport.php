<?php

namespace Modules\Stock\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Validators\Failure;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\SetOpeningStock;

class OpeningStockGeneralImport implements ToCollection, WithHeadingRow, SkipsOnFailure
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
        'stock' => 'D',
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
            // Rows with empty stock are valid but skipped in normalizeRow (no failure)
        }

        if (!empty($validationFailures)) {
            foreach ($validationFailures as $failure) {
                $this->onFailure($failure);
            }
            return;
        }

        foreach ($meaningfulRows as $item) {
            $rowArray = $item['rowArray'];
            $payload = $this->normalizeRow($rowArray);
            if ($payload === null) {
                $this->skippedRows++;
                continue;
            }

            SetOpeningStock::create(array_merge($payload, [
                'user_id' => $this->userId ?: null,
                'company_id' => $this->companyId,
                'outlet_id' => $this->outletId,
            ]));
            $this->createdRows++;
        }
    }

    private function validationMessage(int $rowNumber, string $attribute, string $message): string
    {
        $column = self::ATTRIBUTE_COLUMNS[$attribute] ?? $attribute;
        return "Row Number {$rowNumber}, Column {$column}, {$message}";
    }

    private function validateRow(array $row, int $rowNumber): ?array
    {
        $name = $this->stringValue($row, 'name');
        $code = $this->stringValue($row, 'code');
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
        if (!in_array($item->type, ['General_Product', 'Installment_Product'], true)) {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'Item must be General Product or Installment Product.')]];
        }

        // If stock is empty, skip this row (no validation error; will not be imported)
        if ($stockRaw === null || $stockRaw === '') {
            return null;
        }
        $stockNum = $this->parseDecimal($stockRaw);
        if ($stockNum === null || $stockNum < 0) {
            return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'Stock must be a number greater than or equal to 0.')]];
        }

        return null;
    }

    private function normalizeRow(array $row): ?array
    {
        $code = $this->stringValue($row, 'code');
        $stockRaw = $this->stringValue($row, 'stock');
        // Skip row when stock is not filled
        if ($stockRaw === null || $stockRaw === '') {
            return null;
        }
        $stockNum = $this->parseDecimal($stockRaw);
        if ($stockNum === null || $stockNum < 0) {
            return null;
        }

        $item = Item::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->where('code', $code)
            ->first();

        if (!$item || !in_array($item->type, ['General_Product', 'Installment_Product'], true)) {
            return null;
        }

        $quantity = (float) $stockNum * (float) ($item->conversion_rate ?? 1);

        return [
            'item_id' => $item->id,
            'item_type' => $item->type,
            'item_description' => null,
            'stock_quantity' => $quantity,
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
