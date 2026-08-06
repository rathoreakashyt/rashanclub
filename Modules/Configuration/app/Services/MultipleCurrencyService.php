<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\MultipleCurrency;
use Modules\Configuration\Repositories\MultipleCurrencyRepository;
use Illuminate\Support\Facades\Auth;

class MultipleCurrencyService
{
    protected $multipleCurrencyRepository;

    /**
     * MultipleCurrencyService constructor.
     *
     * @param MultipleCurrencyRepository $multipleCurrencyRepository
     */
    public function __construct(MultipleCurrencyRepository $multipleCurrencyRepository)
    {
        $this->multipleCurrencyRepository = $multipleCurrencyRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->multipleCurrencyRepository->getDataTableData($params);
    }

    /**
     * Get all multiple currencies
     */
    public function getAllMultipleCurrencies()
    {
        return $this->multipleCurrencyRepository->all();
    }

    /**
     * Get multiple currencies with pagination
     */
    public function getMultipleCurrenciesPaginated(int $perPage = 10)
    {
        return $this->multipleCurrencyRepository->paginate($perPage);
    }

    /**
     * Get multiple currency by encrypted ID
     */
    public function getMultipleCurrencyByEncryptedId(string $encryptedId): ?MultipleCurrency
    {
        return $this->multipleCurrencyRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new multiple currency
     */
    public function createMultipleCurrency(array $data): MultipleCurrency
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        
        return $this->multipleCurrencyRepository->create($data);
    }

    /**
     * Update an existing multiple currency
     */
    public function updateMultipleCurrency(string $encryptedId, array $data): bool
    {
        $multipleCurrency = $this->multipleCurrencyRepository->findByEncryptedId($encryptedId);
        
        if (!$multipleCurrency) {
            throw new \Exception('Multiple Currency not found');
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['updated_at'] = now();

        return $this->multipleCurrencyRepository->update($multipleCurrency, $data);
    }

    /**
     * Delete a multiple currency
     */
    public function deleteMultipleCurrency(string $encryptedId): bool
    {
        $multipleCurrency = $this->multipleCurrencyRepository->findByEncryptedId($encryptedId);
        
        if (!$multipleCurrency) {
            throw new \Exception('Multiple Currency not found');
        }

        return $this->multipleCurrencyRepository->delete($multipleCurrency);
    }

    /**
     * Search multiple currencies
     */
    public function searchMultipleCurrencies(string $term)
    {
        return $this->multipleCurrencyRepository->search($term);
    }

    /**
     * Get multiple currency statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->multipleCurrencyRepository->getTotalCount(),
        ];
    }

    /**
     * Check if currency exists for the current company
     */
    public function currencyExists(string $currency, ?string $excludeEncryptedId = null): bool
    {
        $excludeId = null;
        if ($excludeEncryptedId) {
            try {
                $excludeId = decrypt($excludeEncryptedId);
                $excludeId = (int) $excludeId;
            } catch (\Exception $e) {
                $excludeId = null;
            }
        }

        return $this->multipleCurrencyRepository->currencyExists($currency, $excludeId);
    }
}

