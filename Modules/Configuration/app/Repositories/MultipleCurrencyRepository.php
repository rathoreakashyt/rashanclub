<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\MultipleCurrency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MultipleCurrencyRepository
{
    protected $model;

    public function __construct(MultipleCurrency $model)
    {
        $this->model = $model;
    }

    /**
     * Get all multiple currencies
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get multiple currencies with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find multiple currency by ID
     */
    public function find(int $id): ?MultipleCurrency
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find multiple currency by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?MultipleCurrency
    {
        try {
            $id = decrypt($encryptedId);
            $id = (int) $id;
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new multiple currency
     */
    public function create(array $data): MultipleCurrency
    {
        return $this->model->create($data);
    }

    /**
     * Update a multiple currency
     */
    public function update(MultipleCurrency $multipleCurrency, array $data): bool
    {
        return $multipleCurrency->update($data);
    }

    /**
     * Delete a multiple currency (soft delete)
     */
    public function delete(MultipleCurrency $multipleCurrency): bool
    {
        return $multipleCurrency->update(['del_status' => 'Deleted']);
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';

        $query = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('currency', 'like', "%{$search}%")
                  ->orWhere('conversion_rate', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $multipleCurrencies = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $multipleCurrencies->map(function ($multipleCurrency, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $multipleCurrency->id, // Keep actual ID
                'currency' => $multipleCurrency->currency,
                'conversion_rate' => $multipleCurrency->conversion_rate,
                'encrypted_id' => $multipleCurrency->encrypted_id
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
     * Get total multiple currency count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search multiple currencies
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where(function($q) use ($term) {
                $q->where('currency', 'like', "%{$term}%")
                  ->orWhere('conversion_rate', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Check if currency exists for the current company
     */
    public function currencyExists(string $currency, ?int $excludeId = null): bool
    {
        $query = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('currency', $currency);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}

