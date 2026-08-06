<?php

namespace Modules\Stock\Services;

use Modules\Stock\Models\Rack;
use Modules\Stock\Repositories\RackRepository;
use Illuminate\Support\Facades\Auth;

class RackService
{
    protected $rackRepository;

    /**
     * RackService constructor.
     *
     * @param RackRepository $rackRepository
     */
    public function __construct(RackRepository $rackRepository)
    {
        $this->rackRepository = $rackRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->rackRepository->getDataTableData($params);
    }

    /**
     * Get all racks
     */
    public function getAllRacks()
    {
        return $this->rackRepository->all();
    }

    /**
     * Get racks with pagination
     */
    public function getRacksPaginated(int $perPage = 10)
    {
        return $this->rackRepository->paginate($perPage);
    }

    /**
     * Get rack by encrypted ID
     */
    public function getRackByEncryptedId(string $encryptedId): ?Rack
    {
        return $this->rackRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new rack
     */
    public function createRack(array $data): Rack
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';
        return $this->rackRepository->create($data);
    }

    /**
     * Update an existing rack
     */
    public function updateRack(string $encryptedId, array $data): bool
    {
        $rack = $this->rackRepository->findByEncryptedId($encryptedId);
        if (!$rack) {
            throw new \Exception('Rack not found');
        }
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        return $this->rackRepository->update($rack, $data);
    }

    /**
     * Delete a rack
     */
    public function deleteRack(string $encryptedId): bool
    {
        $rack = $this->rackRepository->findByEncryptedId($encryptedId);
        if (!$rack) {
            throw new \Exception('Rack not found');
        }
        return $this->rackRepository->delete($rack);
    }

    /**
     * Search racks
     */
    public function searchRacks(string $term)
    {
        return $this->rackRepository->search($term);
    }

    /**
     * Get rack statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->rackRepository->getTotalCount(),
        ];
    }
}

