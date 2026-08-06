<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\Denomination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DenominationRepository
{
    protected $model;

    public function __construct(Denomination $model)
    {
        $this->model = $model;
    }

    /**
     * Get all denominations
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get denominations with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find denomination by ID
     */
    public function find(int $id): ?Denomination
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find denomination by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Denomination
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
     * Create a new denomination
     */
    public function create(array $data): Denomination
    {
        return $this->model->create($data);
    }

    /**
     * Update a denomination
     */
    public function update(Denomination $denomination, array $data): bool
    {
        return $denomination->update($data);
    }

    /**
     * Delete a denomination (soft delete)
     */
    public function delete(Denomination $denomination): bool
    {
        return $denomination->update(['del_status' => 'Deleted']);
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
                $q->where('value', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $denominations = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $denominations->map(function ($denomination, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $denomination->id, // Keep actual ID
                'amount' => $denomination->amount,
                'description' => $denomination->description,
                'encrypted_id' => $denomination->encrypted_id
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
     * Get total denomination count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search denominations
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where(function($q) use ($term) {
                $q->where('value', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}

