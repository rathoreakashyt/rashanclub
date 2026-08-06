<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Damage;
use Modules\Stock\Models\DamageDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DamageRepository
{
    protected $model;
    protected $detailModel;

    public function __construct(
        Damage $model,
        DamageDetail $detailModel
    ) {
        $this->model = $model;
        $this->detailModel = $detailModel;
    }

    /**
     * Get all damages for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->with(['employee', 'responsiblePerson', 'user'])
            ->live()
            ->forCompany($companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get damages with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->with(['employee', 'responsiblePerson', 'user'])
            ->live()
            ->forCompany($companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('responsiblePerson', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $damages = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $damages,
        ];
    }

    /**
     * Find damage by ID
     */
    public function find(int $id): ?Damage
    {
        return $this->model
            ->with(['responsiblePerson', 'user', 'damageDetails.item'])
            ->live()
            ->find($id);
    }

    /**
     * Find damage by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Damage
    {
        try {
            $decryptedId = decrypt($encryptedId);
            return $this->find($decryptedId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new damage with details
     */
    public function create(array $data): Damage
    {
        return DB::transaction(function () use ($data) {
            // Create the main damage
            $damage = $this->model->create($data['damage']);

            // Create damage details
            foreach ($data['details'] as $detail) {
                $detail['damage_id'] = $damage->id;
                $this->detailModel->create($detail);
            }

            return $damage->load(['employee', 'responsiblePerson', 'user', 'damageDetails.item']);
        });
    }

    /**
     * Update a damage
     */
    public function update(Damage $damage, array $data): bool
    {
        return DB::transaction(function () use ($damage, $data) {
            // Update main damage
            $damage->update($data['damage']);

            // Hard delete existing details
            $this->detailModel
                ->where('damage_id', $damage->id)
                ->delete();

            // Create new details
            foreach ($data['details'] as $detail) {
                $detail['damage_id'] = $damage->id;
                $this->detailModel->create($detail);
            }

            return true;
        });
    }

    /**
     * Soft delete a damage
     */
    public function delete(Damage $damage): bool
    {
        return DB::transaction(function () use ($damage) {
            // Mark details as deleted
            $this->detailModel
                ->where('damage_id', $damage->id)
                ->update(['del_status' => 'Deleted']);

            // Mark main damage as deleted
            return $damage->update(['del_status' => 'Deleted']);
        });
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber(): string
    {
        $companyId = session('company.company_id');
        $prefix = 'DMG';
        $year = date('Y');
        
        // Get the last damage for this company in this year
        $lastDamage = $this->model
            ->forCompany($companyId)
            ->where('reference_no', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastDamage) {
            // Extract the number part
            $parts = explode('-', $lastDamage->reference_no);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }
}

