<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\ServicingRepository;
use Modules\Sale\Models\Servicing;

class ServicingService
{
    protected $servicingRepository;

    public function __construct(ServicingRepository $servicingRepository)
    {
        $this->servicingRepository = $servicingRepository;
    }

    /**
     * Get all servicings for a company.
     */
    public function getAllServicings(int $companyId, int $perPage = 15)
    {
        return $this->servicingRepository->getAllForCompany($companyId, $perPage);
    }

    /**
     * Get a servicing by ID with its relationships.
     */
    public function getServicingById(int $id)
    {
        return $this->servicingRepository->getByIdWithRelations($id);
    }

    /**
     * Get a servicing by encrypted ID with its relationships.
     */
    public function getServicingByEncryptedId(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            return $this->servicingRepository->getByIdWithRelations((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new servicing.
     */
    public function createServicing(array $data)
    {
        // Set default values
        $data['company_id'] = $data['company_id'] ?? session('company.company_id');
        $data['outlet_id'] = $data['outlet_id'] ?? session('outlet.outlet_id');
        $data['user_id'] = $data['user_id'] ?? auth()->id();
        
        // Calculate due amount if not provided
        if (!isset($data['due_amount'])) {
            $servicingCharge = $data['servicing_charge'] ?? 0;
            $paidAmount = $data['paid_amount'] ?? 0;
            $data['due_amount'] = max(0, $servicingCharge - $paidAmount);
        }

        return $this->servicingRepository->create($data);
    }

    /**
     * Update a servicing.
     */
    public function updateServicing(string $encryptedId, array $data)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid servicing ID');
        }

        // Calculate due amount if servicing_charge or paid_amount is updated
        if (isset($data['servicing_charge']) || isset($data['paid_amount'])) {
            $servicing = $this->servicingRepository->getByIdWithRelations((int)$id);
            if ($servicing) {
                $servicingCharge = $data['servicing_charge'] ?? $servicing->servicing_charge;
                $paidAmount = $data['paid_amount'] ?? $servicing->paid_amount;
                $data['due_amount'] = max(0, $servicingCharge - $paidAmount);
            }
        }

        return $this->servicingRepository->update((int)$id, $data);
    }

    /**
     * Delete a servicing.
     */
    public function deleteServicing(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid servicing ID');
        }
        
        $servicing = Servicing::find((int)$id);
        if (!$servicing) {
            throw new \Exception('Servicing not found');
        }
        
        return $this->servicingRepository->delete((int)$id);
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->servicingRepository->getDataTableData($params);
    }
}
