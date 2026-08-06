<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Variation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VariationRepository
{
    protected $model;

    public function __construct(Variation $model)
    {
        $this->model = $model;
    }

    /**
     * Get all variations
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get variations with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find variation by ID
     */
    public function find(int $id): ?Variation
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find variation by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Variation
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
     * Create a new variation
     */
    public function create(array $data): Variation
    {
        return $this->model->create($data);
    }

    /**
     * Update a variation
     */
    public function update(Variation $variation, array $data): bool
    {
        return $variation->update($data);
    }

    /**
     * Delete a variation (soft delete)
     */
    public function delete(Variation $variation): bool
    {
        return $variation->update(['del_status' => 'Deleted']);
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
                $q->where('variation_name', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $variations = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $variations->map(function ($variation, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $variation->id, // Keep actual ID
                'variation_name' => $variation->variation_name,
                'variation_value' => is_array($variation->variation_value) ? implode(', ', $variation->variation_value) : $variation->variation_value,
                'encrypted_id' => $variation->encrypted_id
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
     * Get total variation count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search variations
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('variation_name', 'like', "%{$term}%")
            ->orderBy('id', 'desc')
            ->get();
    }
}

