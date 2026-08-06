<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\Counter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CounterRepository
{
    protected $model;

    public function __construct(Counter $model)
    {
        $this->model = $model;
    }

    /**
     * Get all counters
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['printer', 'outlet'])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get counters with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['printer', 'outlet'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find counter by ID
     */
    public function find(int $id): ?Counter
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find counter by ID with relationships
     */
    public function findWithRelations(int $id): ?Counter
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['printer', 'outlet'])
            ->find($id);
    }

    /**
     * Find counter by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Counter
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
     * Find counter by encrypted ID with relationships
     */
    public function findByEncryptedIdWithRelations(string $encryptedId): ?Counter
    {
        try {
            $id = decrypt($encryptedId);
            $id = (int) $id;
            return $this->findWithRelations($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new counter
     */
    public function create(array $data): Counter
    {
        return $this->model->create($data);
    }

    /**
     * Update a counter
     */
    public function update(Counter $counter, array $data): bool
    {
        return $counter->update($data);
    }

    /**
     * Delete a counter (soft delete)
     */
    public function delete(Counter $counter): bool
    {
        return $counter->update(['del_status' => 'Deleted']);
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
            ->with(['printer', 'outlet'])
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

        $counters = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $counters->map(function ($counter, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $counter->id, // Keep actual ID
                'name' => $counter->name,
                'description' => truncateText($counter->description),
                'printer_id' => $counter->printer ? $counter->printer->title : null,
                'outlet_id' => $counter->outlet ? $counter->outlet->outlet_name : null,
                'encrypted_id' => $counter->encrypted_id
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
     * Get total counter count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search counters
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['printer', 'outlet'])
            ->where(function($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}

