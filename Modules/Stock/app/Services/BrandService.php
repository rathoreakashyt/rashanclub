<?php

namespace Modules\Stock\Services;

use Modules\Stock\Models\Brand;
use Modules\Stock\Repositories\BrandRepository;
use Illuminate\Support\Facades\Auth;

class BrandService
{
    protected $brandRepository;

    /**
     * BrandService constructor.
     *
     * @param BrandRepository $brandRepository
     */
    public function __construct(BrandRepository $brandRepository)
    {
        $this->brandRepository = $brandRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->brandRepository->getDataTableData($params);
    }

    /**
     * Get all brands
     */
    public function getAllBrands()
    {
        return $this->brandRepository->all();
    }

    /**
     * Get brands with pagination
     */
    public function getBrandsPaginated(int $perPage = 10)
    {
        return $this->brandRepository->paginate($perPage);
    }

    /**
     * Get brand by encrypted ID
     */
    public function getBrandByEncryptedId(string $encryptedId): ?Brand
    {
        return $this->brandRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new brand
     */
    public function createBrand(array $data): Brand
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';
        return $this->brandRepository->create($data);
    }

    /**
     * Update an existing brand
     */
    public function updateBrand(string $encryptedId, array $data): bool
    {
        $brand = $this->brandRepository->findByEncryptedId($encryptedId);
        if (!$brand) {
            throw new \Exception('Brand not found');
        }
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        return $this->brandRepository->update($brand, $data);
    }

    /**
     * Delete a brand
     */
    public function deleteBrand(string $encryptedId): bool
    {
        $brand = $this->brandRepository->findByEncryptedId($encryptedId);
        if (!$brand) {
            throw new \Exception('Brand not found');
        }
        return $this->brandRepository->delete($brand);
    }

    /**
     * Search brands
     */
    public function searchBrands(string $term)
    {
        return $this->brandRepository->search($term);
    }

    /**
     * Get brand statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->brandRepository->getTotalCount(),
        ];
    }
}
