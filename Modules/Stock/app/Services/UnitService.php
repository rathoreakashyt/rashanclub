<?php

namespace Modules\Stock\Services;

use Modules\Stock\Models\Unit;
use Modules\Stock\Repositories\UnitRepository;
use Illuminate\Support\Facades\Auth;

class UnitService
{
    protected $unitRepository;

    /**
     * UnitService constructor.
     *
     * @param UnitRepository $unitRepository
     */
    public function __construct(UnitRepository $unitRepository)
    {
        $this->unitRepository = $unitRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->unitRepository->getDataTableData($params);
    }

    /**
     * Get all units
     */
    public function getAllUnits()
    {
        return $this->unitRepository->all();
    }

    /**
     * Get units with pagination
     */
    public function getUnitsPaginated(int $perPage = 10)
    {
        return $this->unitRepository->paginate($perPage);
    }

    /**
     * Get unit by encrypted ID
     */
    public function getUnitByEncryptedId(string $encryptedId): ?Unit
    {
        return $this->unitRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new unit
     */
    public function createUnit(array $data): Unit
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';
        return $this->unitRepository->create($data);
    }

    /**
     * Update an existing unit
     */
    public function updateUnit(string $encryptedId, array $data): bool
    {
        $unit = $this->unitRepository->findByEncryptedId($encryptedId);
        if (!$unit) {
            throw new \Exception('Unit not found');
        }
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        return $this->unitRepository->update($unit, $data);
    }

    /**
     * Delete a unit
     */
    public function deleteUnit(string $encryptedId): bool
    {
        $unit = $this->unitRepository->findByEncryptedId($encryptedId);
        if (!$unit) {
            throw new \Exception('Unit not found');
        }
        return $this->unitRepository->delete($unit);
    }

    /**
     * Search units
     */
    public function searchUnits(string $term)
    {
        return $this->unitRepository->search($term);
    }

    /**
     * Get unit statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->unitRepository->getTotalCount(),
        ];
    }
}
