<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Rack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RackRepository
{
    protected $model;

    public function __construct(Rack $model)
    {
        $this->model = $model;
    }

    /**
     * Get all racks
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get racks with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find rack by ID
     */
    public function find(int $id): ?Rack
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find rack by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Rack
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
     * Create a new rack
     */
    public function create(array $data): Rack
    {
        return $this->model->create($data);
    }

    /**
     * Update a rack
     */
    public function update(Rack $rack, array $data): bool
    {
        return $rack->update($data);
    }

    /**
     * Delete a rack (soft delete)
     */
    public function delete(Rack $rack): bool
    {
        return $rack->update(['del_status' => 'Deleted']);
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
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $racks = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $racks->map(function ($rack, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $rack->id, // Keep actual ID
                'name' => $rack->name,
                'description' => truncateText($rack->description) ?? '',
                'encrypted_id' => $rack->encrypted_id
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
     * Get total rack count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search racks
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}

