<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\Company;

class CompanyRepository
{
    protected $model;

    public function __construct(Company $model)
    {
        $this->model = $model;
    }

    /**
     * Find company by ID
     */
    public function find(int $id): ?Company
    {
        return $this->model->find($id);
    }

    /**
     * Find company by ID or fail
     */
    public function findOrFail(int $id): Company
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Update company
     */
    public function update(Company $company, array $data): bool
    {
        return $company->update($data);
    }

    /**
     * Update company settings
     */
    public function updateSettings(Company $company, array $settings): bool
    {
        foreach ($settings as $key => $value) {
            $company->$key = $value;
        }
        return $company->save();
    }

    /**
     * Get company for current session
     */
    public function getCurrentCompany(): ?Company
    {
        $companyId = session('company.company_id');
        if ($companyId) {
            $company = $this->find($companyId);
            if ($company) {
                return $company;
            }
        }
        // Fallback: get first company (for single-company setups)
        return $this->model->where('del_status', 'Live')->first();
    }
}

