<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\DepositWithdraw;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Modules\Accounting\Repositories\DepositWithdrawRepository;

class DepositWithdrawService
{
    protected $depositWithdrawRepository;

    /**
     * DepositWithdrawService constructor.
     *
     * @param DepositWithdrawRepository $depositWithdrawRepository
     */
    public function __construct(DepositWithdrawRepository $depositWithdrawRepository)
    {
        $this->depositWithdrawRepository = $depositWithdrawRepository;
    }

    /**
     * Get all deposit/withdraws with pagination
     */
    public function getAllDepositWithdraws($perPage = 10)
    {
        return $this->depositWithdrawRepository->paginate($perPage);
    }

    /**
     * Get data for creating a new deposit/withdraw
     */
    public function getCreateData()
    {
        $data = [];
        $lastRecord = $this->depositWithdrawRepository->getLastDepositWithdrawForCompany();
        $nextId = $lastRecord ? $lastRecord->id + 1 : 1;
        $data['reference_no'] = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        return $data;
    }

    /**
     * Get data for editing a deposit/withdraw
     */
    public function getEditData($id)
    {
        $depositWithdraw = $this->depositWithdrawRepository->findByEncryptedIdWithRelations($id);
        if (!$depositWithdraw) {
            throw new \Exception('Deposit/Withdraw not found');
        }

        return [
            'deposit_withdraw' => $depositWithdraw,
            'reference_no' => $depositWithdraw->reference_no
        ];
    }

    /**
     * Create a new deposit/withdraw
     */
    public function createDepositWithdraw(array $data)
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';
        return $this->depositWithdrawRepository->create($data);
    }

    /**
     * Update an existing deposit/withdraw
     */
    public function updateDepositWithdraw($id, array $data)
    {
        $depositWithdraw = $this->depositWithdrawRepository->findByEncryptedId($id);
        if (!$depositWithdraw) {
            throw new \Exception('Deposit/Withdraw not found');
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        
        return $this->depositWithdrawRepository->update($depositWithdraw, $data);
    }

    /**
     * Delete a deposit/withdraw
     */
    public function deleteDepositWithdraw($id)
    {
        $depositWithdraw = $this->depositWithdrawRepository->findByEncryptedId($id);
        if (!$depositWithdraw) {
            throw new \Exception('Deposit/Withdraw not found');
        }

        return $this->depositWithdrawRepository->delete($depositWithdraw);
    }

    /**
     * Get deposit/withdraws by type
     */
    public function getByType(string $type)
    {
        return $this->depositWithdrawRepository->getByType($type);
    }

    /**
     * Get deposit/withdraw statistics
     */
    public function getDepositWithdrawStatistics()
    {
        return [
            'total' => $this->depositWithdrawRepository->getTotalCount(),
            'deposits' => $this->depositWithdrawRepository->getCountByType('Deposit'),
            'withdraws' => $this->depositWithdrawRepository->getCountByType('Withdraw'),
        ];
    }
}

