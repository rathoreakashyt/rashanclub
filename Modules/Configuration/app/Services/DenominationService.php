<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Denomination;
use Modules\Configuration\Repositories\DenominationRepository;
use Illuminate\Support\Facades\Auth;

class DenominationService
{
    protected $denominationRepository;

    /**
     * DenominationService constructor.
     *
     * @param DenominationRepository $denominationRepository
     */
    public function __construct(DenominationRepository $denominationRepository)
    {
        $this->denominationRepository = $denominationRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->denominationRepository->getDataTableData($params);
    }

    /**
     * Get all denominations
     */
    public function getAllDenominations()
    {
        return $this->denominationRepository->all();
    }

    /**
     * Get denominations with pagination
     */
    public function getDenominationsPaginated(int $perPage = 10)
    {
        return $this->denominationRepository->paginate($perPage);
    }

    /**
     * Get denomination by encrypted ID
     */
    public function getDenominationByEncryptedId(string $encryptedId): ?Denomination
    {
        return $this->denominationRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new denomination
     */
    public function createDenomination(array $data): Denomination
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        return $this->denominationRepository->create($data);
    }

    /**
     * Update an existing denomination
     */
    public function updateDenomination(string $encryptedId, array $data): bool
    {
        $denomination = $this->denominationRepository->findByEncryptedId($encryptedId);
        if (!$denomination) {
            throw new \Exception('Denomination not found');
        }
        return $this->denominationRepository->update($denomination, $data);
    }

    /**
     * Delete a denomination
     */
    public function deleteDenomination(string $encryptedId): bool
    {
        $denomination = $this->denominationRepository->findByEncryptedId($encryptedId);
        if (!$denomination) {
            throw new \Exception('Denomination not found');
        }
        return $this->denominationRepository->delete($denomination);
    }

    /**
     * Search denominations
     */
    public function searchDenominations(string $term)
    {
        return $this->denominationRepository->search($term);
    }

    /**
     * Get denomination statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->denominationRepository->getTotalCount(),
        ];
    }
}

