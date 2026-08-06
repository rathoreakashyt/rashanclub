<?php

namespace Modules\Stock\Services;

use Modules\Stock\Models\Variation;
use Modules\Stock\Repositories\VariationRepository;
use Illuminate\Support\Facades\Auth;

class VariationService
{
    protected $variationRepository;

    /**
     * VariationService constructor.
     *
     * @param VariationRepository $variationRepository
     */
    public function __construct(VariationRepository $variationRepository)
    {
        $this->variationRepository = $variationRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->variationRepository->getDataTableData($params);
    }

    /**
     * Get all variations
     */
    public function getAllVariations()
    {
        return $this->variationRepository->all();
    }

    /**
     * Get variations with pagination
     */
    public function getVariationsPaginated(int $perPage = 10)
    {
        return $this->variationRepository->paginate($perPage);
    }

    /**
     * Get variation by encrypted ID
     */
    public function getVariationByEncryptedId(string $encryptedId): ?Variation
    {
        return $this->variationRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new variation
     */
    public function createVariation(array $data): Variation
    {
        $variation_value = $data['variation_value'] ?? [];
        $variation_value = array_filter($variation_value, fn($v) => !empty(trim($v)));
        
        $data['variation_value'] = $variation_value;
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';
        
        return $this->variationRepository->create($data);
    }

    /**
     * Update an existing variation
     */
    public function updateVariation(string $encryptedId, array $data): bool
    {
        $variation = $this->variationRepository->findByEncryptedId($encryptedId);
        
        if (!$variation) {
            throw new \Exception('Variation not found');
        }

        $variation_value = $data['variation_value'] ?? [];
        $variation_value = array_filter($variation_value, fn($v) => !empty(trim($v)));
        
        $data['variation_value'] = $variation_value;
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');

        return $this->variationRepository->update($variation, $data);
    }

    /**
     * Delete a variation
     */
    public function deleteVariation(string $encryptedId): bool
    {
        $variation = $this->variationRepository->findByEncryptedId($encryptedId);
        
        if (!$variation) {
            throw new \Exception('Variation not found');
        }

        return $this->variationRepository->delete($variation);
    }

    /**
     * Search variations
     */
    public function searchVariations(string $term)
    {
        return $this->variationRepository->search($term);
    }

    /**
     * Get variation statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->variationRepository->getTotalCount(),
        ];
    }
}

