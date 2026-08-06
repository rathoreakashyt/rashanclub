<?php

namespace Modules\Configuration\Repositories;

use Modules\Configuration\Models\Printer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PrinterRepository
{
    protected $model;

    public function __construct(Printer $model)
    {
        $this->model = $model;
    }

    /**
     * Get all printers
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get printers with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find printer by ID
     */
    public function find(int $id): ?Printer
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->find($id);
    }

    /**
     * Find printer by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Printer
    {
        try {
            $id = decrypt($encryptedId);
            $id = (int) $id;
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new printer
     */
    public function create(array $data): Printer
    {
        // If not provided, use default
        $data['fiscal_printer_status'] = $data['fiscal_printer_status'] ?? 'OFF';
        $data['open_cash_drawer_when_printing_invoice'] = $data['open_cash_drawer_when_printing_invoice'] ?? 'OFF';
        return $this->model->create($data);
    }

    /**
     * Update a printer
     */
    public function update(Printer $printer, array $data): bool
    {
        return $printer->update($data);
    }

    /**
     * Delete a printer (soft delete)
     */
    public function delete(Printer $printer): bool
    {
        return $printer->update(['del_status' => 'Deleted']);
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';

        $query = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $filteredCount = $query->count();

        $printers = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $printers->map(function ($printer, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index, // Sequential number
                'actual_id' => $printer->id, // Keep actual ID
                'title' => $printer->title,
                'type' => $printer->type,
                'characters_per_line' => $printer->characters_per_line,
                'printer_ip_address' => $printer->printer_ip_address,
                'printer_port' => $printer->printer_port,
                'path' => $printer->path,
                'encrypted_id' => $printer->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }

    /**
     * Get total printer count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Search printers
     */
    public function search(string $term): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where(function($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('type', 'like', "%{$term}%");
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}

