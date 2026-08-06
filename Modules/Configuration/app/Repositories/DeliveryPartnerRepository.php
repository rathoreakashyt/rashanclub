<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\DeliveryPartner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryPartnerRepository
{
    protected $model;

    public function __construct(DeliveryPartner $model)
    {
        $this->model = $model;
    }

    /**
     * Get all delivery partners
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')->where('company_id', session('company.company_id'))->get();
    }

    /**
     * Get delivery partners with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->with(['creator', 'company'])
            ->where('company_id', session('company.company_id'))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find delivery partner by ID
     */
    public function find(int $id): ?DeliveryPartner
    {
        return $this->model->where('del_status', 'Live')->where('company_id', session('company.company_id'))->find($id);
    }

    /**
     * Find delivery partner by ID with relationships
     */
    public function findWithRelations(int $id): ?DeliveryPartner
    {
        return $this->model->where('del_status', 'Live')
            ->with(['creator', 'company'])
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Create a new delivery partner
     */
    public function create(array $data): DeliveryPartner
    {
        return $this->model->create($data);
    }

    /**
     * Update a delivery partner
     */
    public function update(DeliveryPartner $partner, array $data): bool
    {
        return $partner->update($data);
    }

    /**
     * Delete a delivery partner (soft delete)
     */
    public function delete(DeliveryPartner $partner): bool
    {
        return $partner->update(['del_status' => 'Deleted', 'company_id' => session('company.company_id')]);
    }

    /**
     * Search delivery partners by name
     */
    public function searchByName(string $name): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('name', 'like', "%{$name}%")
            ->where('company_id', session('company.company_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get delivery partners by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('active_status', $status)
            ->where('company_id', session('company.company_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get delivery partners created by a specific user
     */
    public function getByCreator(int $userId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('user_id', $userId)
            ->where('company_id', session('company.company_id'))
            ->orderBy('created_at', 'desc')
            ->get();
    }


    /**
     * Get total delivery partner count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')->where('company_id', session('company.company_id'))->count();
    }

    /**
     * Get the last delivery partner for the current company to generate the next partner code
     */
    public function getLastPartnerForCompany(): ?DeliveryPartner
    {
        return $this->model->where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Find a delivery partner by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?DeliveryPartner
    {
        try {
            $id = decrypt($encryptedId);
            // Ensure the decrypted value is an integer
            $id = (int) $id;
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find a delivery partner by encrypted ID with relationships
     */
    public function findByEncryptedIdWithRelations(string $encryptedId): ?DeliveryPartner
    {
        try {
            $id = decrypt($encryptedId);
            // Ensure the decrypted value is an integer
            $id = (int) $id;
            return $this->findWithRelations($id);
        } catch (\Exception $e) {
            return null;
        }
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
            ->where('del_status', 'Live')
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('del_status', 'Live')
            ->count();

        $filteredCount = $query->count();

        $partners = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $partners->map(function ($partner, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $partner->id, // Keep actual ID
                'partner_name' => $partner->name,
                'description' => $partner->description ?? '',
                'encrypted_id' => $partner->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }
}
