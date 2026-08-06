<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UnitRepository
{
    protected $model;

    public function __construct(Unit $model)
    {
        $this->model = $model;
    }

    /**
     * Get all units
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get units with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find unit by ID
     */
    public function find(int $id): ?Unit
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find unit by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Unit
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
     * Create a new unit
     */
    public function create(array $data): Unit
    {
        return $this->model->create($data);
    }

    /**
     * Update a unit
     */
    public function update(Unit $unit, array $data): bool
    {
        return $unit->update($data);
    }

    /**
     * Delete a unit (soft delete)
     */
    public function delete(Unit $unit): bool
    {
        return $unit->update(['del_status' => 'Deleted']);
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
                $q->where('unit_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $units = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $units->map(function ($unit, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $unit->id, // Keep actual ID
                'unit_name' => $unit->unit_name,
                'description' => truncateText($unit->description) ?? '',
                'encrypted_id' => $unit->encrypted_id
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
     * Get total unit count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search units
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where(function($q) use ($term) {
                $q->where('unit_name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}

