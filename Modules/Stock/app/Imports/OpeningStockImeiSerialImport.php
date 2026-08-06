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

class OpeningStockImeiSerialImport implements ToCollection, WithHeadingRow, SkipsOnFailure
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

        $existingInDb = $this->getExistingImeiSerialsForOutlet();
        $imeiSeenInFile = [];
        $validationFailures = [];
        foreach ($meaningfulRows as $item) {
            $rowArray = $item['rowArray'];
            $excelRowNumber = $item['excelRowNumber'];
            $this->processedRows++;

            $validation = $this->validateRow($rowArray, $excelRowNumber, $existingInDb, $imeiSeenInFile);
            if ($validation !== null) {
                $validationFailures[] = new Failure(
                    $excelRowNumber,
                    $validation['attribute'],
                    $validation['errors'],
                    $rowArray
                );
            } else {
                $stockRaw = $this->stringValue($rowArray, 'stock');
                if ($stockRaw !== null && $stockRaw !== '') {
                    foreach ($this->parseImeiSerialList($stockRaw) as $imei) {
                        $imeiSeenInFile[$imei] = true;
                    }
                }
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

            // Save one row per IMEI/Serial (Stock column exploded by comma)
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

    /**
     * Get all IMEI/Serial values already in use for this outlet (from set_opening_stocks and view_stock_detail).
     */
    private function getExistingImeiSerialsForOutlet(): array
    {
        $existing = [];

        $fromOpening = SetOpeningStock::query()
            ->where('company_id', $this->companyId)
            ->where('outlet_id', $this->outletId)
            ->where('del_status', 'Live')
            ->whereNotNull('item_description')
            ->where('item_description', '!=', '')
            ->pluck('item_description');

        foreach ($fromOpening as $desc) {
            if (is_string($desc)) {
                foreach ($this->parseImeiSerialList($desc) as $imei) {
                    $existing[$imei] = true;
                }
            }
        }

        try {
            $fromStock = DB::table('view_stock_detail')
                ->where('outlet_id', $this->outletId)
                ->whereNotNull('expiry_imei_serial')
                ->where('expiry_imei_serial', '!=', '')
                ->pluck('expiry_imei_serial');
            foreach ($fromStock as $imei) {
                $existing[trim((string) $imei)] = true;
            }
        } catch (\Throwable $e) {
            // view_stock_detail may not exist
        }

        return array_keys($existing);
    }

    /**
     * Parse comma-separated IMEI/Serial values. Each comma-separated value counts as one IMEI.
     * e.g. "123, 345" or "123,345" -> ["123", "345"]
     */
    private function parseImeiSerialList(string $value): array
    {
        $parts = preg_split('/\s*[,|\n]\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, fn ($p) => $p !== ''));
    }

    /**
     * @param array $existingInDb Existing IMEI/Serial in DB (opening stock + view_stock_detail)
     * @param array $imeiSeenInFile IMEIs already accepted from previous rows in this file (key = imei)
     */
    private function validateRow(array $row, int $rowNumber, array $existingInDb, array $imeiSeenInFile): ?array
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
        if (!in_array($item->type, ['IMEI_Product', 'Serial_Product'], true)) {
            return ['attribute' => 'code', 'errors' => [$this->validationMessage($rowNumber, 'code', 'Item must be IMEI Product or Serial Product.')]];
        }

        if ($stockRaw === null || $stockRaw === '') {
            return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'IMEI/Serial (Stock) is required. Use comma separate e.g. 5555, 6666.')]];
        }

        $imeiList = $this->parseImeiSerialList($stockRaw);
        if (empty($imeiList)) {
            return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'At least one IMEI/Serial is required. Use comma separate e.g. 123, 345.')]];
        }

        // Same row: duplicate IMEI/Serial in one cell (e.g. "123, 123" not allowed)
        $imeiListUnique = array_unique($imeiList);
        if (count($imeiListUnique) !== count($imeiList)) {
            $dupesInRow = array_keys(array_filter(array_count_values($imeiList), fn ($c) => $c > 1));
            $list = implode(', ', array_slice($dupesInRow, 0, 5));
            return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'Duplicate IMEI/Serial in same row: ' . $list . '. Each IMEI/Serial must be unique.')]];
        }

        // Already in database or already in this file (unique across DB + same file)
        $duplicatesInDb = array_intersect($imeiList, $existingInDb);
        $duplicatesInFile = array_intersect_key(array_flip($imeiList), $imeiSeenInFile);
        $duplicatesInFile = array_keys($duplicatesInFile);
        $duplicates = array_unique(array_merge($duplicatesInDb, $duplicatesInFile));

        if (!empty($duplicates)) {
            $list = implode(', ', array_slice($duplicates, 0, 5));
            if (count($duplicates) > 5) {
                $list .= ' ... and ' . (count($duplicates) - 5) . ' more';
            }
            return ['attribute' => 'stock', 'errors' => [$this->validationMessage($rowNumber, 'stock', 'IMEI/Serial already exists: ' . $list . '.')]];
        }

        return null;
    }

    /**
     * Read Stock column, explode by comma, return one payload per IMEI/Serial for saving.
     * Each IMEI is saved as a separate row in set_opening_stocks (same as add_purchase flow).
     *
     * @return array|null Array of payloads (one per IMEI), or null to skip row
     */
    private function normalizeRow(array $row): ?array
    {
        $code = $this->stringValue($row, 'code');
        $stockRaw = $this->stringValue($row, 'stock');

        // Explode Stock column by comma -> list of IMEI/Serial values
        $imeiList = $stockRaw !== null && $stockRaw !== '' ? $this->parseImeiSerialList($stockRaw) : [];
        if (empty($imeiList)) {
            return null;
        }

        $item = Item::query()
            ->where('company_id', $this->companyId)
            ->where('del_status', 'Live')
            ->where('code', $code)
            ->first();

        if (!$item || !in_array($item->type, ['IMEI_Product', 'Serial_Product'], true)) {
            return null;
        }

        $conversionRate = (float) ($item->conversion_rate ?? 1);
        $payloads = [];

        // One row per IMEI/Serial (iterate and save each to database)
        foreach ($imeiList as $imeiValue) {
            $imeiValue = trim($imeiValue);
            if ($imeiValue === '') {
                continue;
            }
            $payloads[] = [
                'item_id' => $item->id,
                'item_type' => $item->type,
                'item_description' => $imeiValue,
                'stock_quantity' => $conversionRate,
            ];
        }

        return empty($payloads) ? null : $payloads;
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
