<?php

namespace Modules\Configuration\App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Configuration\Models\Outlet;

interface OutletRepositoryInterface
{
    /**
     * Get all outlets for the current company.
     *
     * @return Collection
     */
    public function getAllForCompany(): Collection;

    /**
     * Find an outlet by ID.
     *
     * @param int $id
     * @return Outlet|null
     */
    public function findById(int $id): ?Outlet;

    /**
     * Create a new outlet.
     *
     * @param array $data
     * @return Outlet
     */
    public function create(array $data): Outlet;

    /**
     * Update an existing outlet.
     *
     * @param int $id
     * @param array $data
     * @return Outlet
     */
    public function update(int $id, array $data): Outlet;

    /**
     * Soft delete an outlet by updating its status.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get the last outlet for the current company to generate the next outlet code.
     *
     * @return Outlet|null
     */
    public function getLastOutletForCompany(): ?Outlet;

    /**
     * Find an outlet by encrypted ID.
     *
     * @param string $encryptedId
     * @return Outlet|null
     */
    public function findByEncryptedId(string $encryptedId): ?Outlet;
}
