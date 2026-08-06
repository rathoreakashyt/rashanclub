<?php

namespace Modules\Stock\Services;

use Modules\Stock\Models\Item;
use Modules\Stock\Repositories\StockRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class StockService
{
    protected $stockRepository;

    public function __construct(StockRepository $stockRepository)
    {
        $this->stockRepository = $stockRepository;
    }

    /**
     * Get data for DataTables (with server-side pagination)
     */
    public function getDataTableData(array $filters = [], int $start = 0, int $length = 10): array
    {
        return $this->stockRepository->getDataTableData($filters, $start, $length);
    }

    /**
     * Get stock evaluation (total stock value and count)
     */
    public function getStockEvaluation(array $filters = []): array
    {
        return $this->stockRepository->getStockEvaluation($filters);
    }

    /**
     * Get stock segmentation of an item (IMEI/Serial, Expiry, Variations)
     */
    public function getStockSegmentationOfItem(int $itemId, string $itemType): string
    {
        return $this->stockRepository->getStockSegmentationOfItem($itemId, $itemType);
    }

    /**
     * Get low stock data for DataTables (with server-side pagination)
     */
    public function getLowStockDataTableData(array $filters = [], int $start = 0, int $length = 10): array
    {
        return $this->stockRepository->getLowStockDataTableData($filters, $start, $length);
    }
}

