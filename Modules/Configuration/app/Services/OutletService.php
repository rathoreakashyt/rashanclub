<?php

namespace Modules\Configuration\Services;

use App\Services\BippService;
use Modules\Configuration\Models\Outlet;
use Modules\Configuration\Models\State;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Modules\Configuration\Repositories\OutletRepository;

class OutletService
{
    protected $outletRepository;

    /**
     * OutletService constructor.
     *
     * @param OutletRepository $outletRepository
     */
    public function __construct(OutletRepository $outletRepository)
    {
        $this->outletRepository = $outletRepository;
    }

    /**
     * Get all outlets with pagination
     */
    public function getAllOutlets($perPage = 100)
    {
        return $this->outletRepository->paginate($perPage);
    }

    /**
     * Get active outlets
     */
    public function getActiveOutlets()
    {
        return $this->outletRepository->getActiveOutlets();
    }

    /**
     * Get data for creating a new outlet
     */
    public function getCreateData()
    {
        $data = [];
        $lastOutlet = $this->outletRepository->getLastOutletForCompany();
        $nextId = $lastOutlet ? $lastOutlet->id + 1 : 1;
        $data['outlet_code'] = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        $data['states'] = State::orderBy('state_code')->get();
        return $data;
    }

    /**
     * Get data for editing an outlet
     */
    public function getEditData($id)
    {
        $outlet = $this->outletRepository->findByEncryptedIdWithRelations($id);
        if (!$outlet) {
            throw new \Exception('Outlet not found');
        }

        return [
            'outlet' => $outlet,
            'outlet_code' => $outlet->outlet_code,
            'states' => State::orderBy('state_code')->get(),
        ];
    }

    /**
     * Create a new outlet
     */
    public function createOutlet(array $data)
    {
        if (!BippService::canAddOutlets(1)) {
            throw new \Exception(BippService::outletLimitMessage());
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data = $this->normalizeGstFields($data);
        return $this->outletRepository->create($data);
    }

    /**
     * Update an existing outlet
     */
    public function updateOutlet($id, array $data)
    {
        $outlet = $this->outletRepository->findByEncryptedId($id);
        if (!$outlet) {
            throw new \Exception('Outlet not found');
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data = $this->normalizeGstFields($data);
        return $this->outletRepository->update($outlet, $data);
    }

    /**
     * When GST is disabled, ensure state_id, gstin are null.
     * When GST is enabled, normalize GSTIN (uppercase, no spaces) for storage.
     */
    protected function normalizeGstFields(array $data): array
    {
        if (session('company.tax_is_gst') !== 'Yes') {
            $data['state_id'] = null;
            $data['gstin'] = null;
        } elseif (! empty($data['gstin'])) {
            $data['gstin'] = \App\Helpers\GstinValidator::normalize($data['gstin']);
        }
        return $data;
    }

    /**
     * Delete an outlet
     */
    public function deleteOutlet($id)
    {
        $outlet = $this->outletRepository->findByEncryptedId($id);
        if (!$outlet) {
            throw new \Exception('Outlet not found');
        }

        return $this->outletRepository->delete($outlet);
    }

    /**
     * Toggle outlet status
     */
    public function toggleOutletStatus($id)
    {
        $outlet = $this->outletRepository->findByEncryptedId($id);
        if (!$outlet) {
            throw new \Exception('Outlet not found');
        }

        $newStatus = $outlet->active_status === 'Active' ? 'Inactive' : 'Active';
        
        return $this->outletRepository->update($outlet, [
            'active_status' => $newStatus,
            'user_id' => Auth::id()
        ]);
    }

    /**
     * Enter an outlet and set session data
     */
    public function enterOutlet($id)
    {
        try {
            $decryptedId = decrypt($id);
            $decryptedId = (int) $decryptedId;
            $outlet = $this->outletRepository->find($decryptedId);
        } catch (\Exception $e) {
            // If direct decryption fails, try the findByEncryptedId method
            $outlet = $this->outletRepository->findByEncryptedId($id);
        }
        
        if (!$outlet) {
            throw new \Exception('Outlet not found');
        }

        session([
            'outlet' => [
                'outlet_id'    => $outlet->id,
                'outlet_name'  => $outlet->outlet_name,
                'address'      => $outlet->address,
                'phone'        => $outlet->phone,
                'outlet_email' => $outlet->email,
            ]
        ]);

        // Redirect based on role
        if (Auth::user()->hasRole('Super Admin')) {
            return 'dashboard';
        }
        return 'userhome';
    }

    /**
     * Get outlet statistics
     */
    public function getOutletStatistics()
    {
        return [
            'total' => $this->outletRepository->getTotalCount(),
            'active' => $this->outletRepository->getCountByStatus('Active'),
            'inactive' => $this->outletRepository->getCountByStatus('Inactive'),
        ];
    }
}
