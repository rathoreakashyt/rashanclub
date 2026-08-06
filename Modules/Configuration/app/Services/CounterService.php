<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Counter;
use Modules\Configuration\Models\Outlet;
use Modules\Configuration\Models\Printer;
use Modules\Configuration\Repositories\CounterRepository;
use Illuminate\Support\Facades\Auth;

class CounterService
{
    protected $counterRepository;

    /**
     * CounterService constructor.
     *
     * @param CounterRepository $counterRepository
     */
    public function __construct(CounterRepository $counterRepository)
    {
        $this->counterRepository = $counterRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->counterRepository->getDataTableData($params);
    }

    /**
     * Get all counters
     */
    public function getAllCounters()
    {
        return $this->counterRepository->all();
    }

    /**
     * Get counters with pagination
     */
    public function getCountersPaginated(int $perPage = 10)
    {
        return $this->counterRepository->paginate($perPage);
    }

    /**
     * Get counter by encrypted ID
     */
    public function getCounterByEncryptedId(string $encryptedId): ?Counter
    {
        return $this->counterRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Get counter by encrypted ID with relationships
     */
    public function getCounterByEncryptedIdWithRelations(string $encryptedId): ?Counter
    {
        return $this->counterRepository->findByEncryptedIdWithRelations($encryptedId);
    }

    /**
     * Get data for creating/editing counter
     */
    public function getFormData(): array
    {
        $printers = Printer::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->get();

        $outlets = Outlet::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->get();

        return [
            'printers' => $printers,
            'outlets' => $outlets
        ];
    }

    /**
     * Create a new counter
     */
    public function createCounter(array $data): Counter
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        
        return $this->counterRepository->create($data);
    }

    /**
     * Update an existing counter
     */
    public function updateCounter(string $encryptedId, array $data): bool
    {
        $counter = $this->counterRepository->findByEncryptedId($encryptedId);
        
        if (!$counter) {
            throw new \Exception('Counter not found');
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['updated_at'] = now();

        return $this->counterRepository->update($counter, $data);
    }

    /**
     * Delete a counter
     */
    public function deleteCounter(string $encryptedId): bool
    {
        $counter = $this->counterRepository->findByEncryptedId($encryptedId);
        
        if (!$counter) {
            throw new \Exception('Counter not found');
        }

        return $this->counterRepository->delete($counter);
    }

    /**
     * Search counters
     */
    public function searchCounters(string $term)
    {
        return $this->counterRepository->search($term);
    }

    /**
     * Get counter statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->counterRepository->getTotalCount(),
        ];
    }
}

