<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\DeliveryPartner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Modules\Configuration\Repositories\DeliveryPartnerRepository;

class DeliveryPartnerService
{
    protected $deliveryPartnerRepository;

    /**
     * DeliveryPartnerService constructor.
     *
     * @param DeliveryPartnerRepository $deliveryPartnerRepository
     */
    public function __construct(DeliveryPartnerRepository $deliveryPartnerRepository)
    {
        $this->deliveryPartnerRepository = $deliveryPartnerRepository;
    }

    /**
     * Get all delivery partners with pagination
     */
    public function getAllDeliveryPartners($perPage = 10)
    {
        return $this->deliveryPartnerRepository->paginate($perPage);
    }

    /**
     * Get active delivery partners
     */
    public function getActiveDeliveryPartners()
    {
        return $this->deliveryPartnerRepository->getActivePartners();
    }

    /**
     * Get data for creating a new delivery partner
     */
    public function getCreateData()
    {
        $data = [];
        $lastPartner = $this->deliveryPartnerRepository->getLastPartnerForCompany();
        $nextId = $lastPartner ? $lastPartner->id + 1 : 1;
        $data['partner_code'] = 'DP' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        return $data;
    }

    /**
     * Get data for editing a delivery partner
     */
    public function getEditData($id)
    {
        $partner = $this->deliveryPartnerRepository->findByEncryptedIdWithRelations($id);
        if (!$partner) {
            throw new \Exception('Delivery Partner not found');
        }

        return [
            'partner' => $partner,
            'partner_code' => $partner->partner_code
        ];
    }

    /**
     * Create a new delivery partner
     */
    public function createDeliveryPartner(array $data)
    {
        $data['name'] = $data['partner_name'];
        unset($data['partner_name']);
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        return $this->deliveryPartnerRepository->create($data);
    }

    /**
     * Update an existing delivery partner
     */
    public function updateDeliveryPartner($id, array $data)
    {
        $partner = $this->deliveryPartnerRepository->findByEncryptedId($id);
        if (!$partner) {
            throw new \Exception('Delivery Partner not found');
        }

        $data['name'] = $data['partner_name'];
        unset($data['partner_name']);
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        
        return $this->deliveryPartnerRepository->update($partner, $data);
    }

    /**
     * Delete a delivery partner
     */
    public function deleteDeliveryPartner($id)
    {
        $partner = $this->deliveryPartnerRepository->findByEncryptedId($id);
        if (!$partner) {
            throw new \Exception('Delivery Partner not found');
        }

        return $this->deliveryPartnerRepository->delete($partner);
    }

    /**
     * Toggle delivery partner status
     */
    public function toggleDeliveryPartnerStatus($id)
    {
        $partner = $this->deliveryPartnerRepository->findByEncryptedId($id);
        if (!$partner) {
            throw new \Exception('Delivery Partner not found');
        }

        $newStatus = $partner->active_status === 'Active' ? 'Inactive' : 'Active';
        
        return $this->deliveryPartnerRepository->update($partner, [
            'active_status' => $newStatus,
            'user_id' => Auth::id()
        ]);
    }

    /**
     * Get delivery partner statistics
     */
    public function getDeliveryPartnerStatistics()
    {
        return [
            'total' => $this->deliveryPartnerRepository->getTotalCount(),
        ];
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->deliveryPartnerRepository->getDataTableData($params);
    }

    /**
     * Get delivery partner by encrypted ID
     */
    public function getDeliveryPartnerByEncryptedId(string $encryptedId)
    {
        return $this->deliveryPartnerRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Get delivery partners by vehicle type
     */
    public function getDeliveryPartnersByVehicleType($vehicleType)
    {
        return $this->deliveryPartnerRepository->getByVehicleType($vehicleType);
    }
}
