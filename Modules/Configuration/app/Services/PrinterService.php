<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Printer;
use Modules\Configuration\Repositories\PrinterRepository;
use Illuminate\Support\Facades\Auth;

class PrinterService
{
    protected $printerRepository;

    /**
     * PrinterService constructor.
     *
     * @param PrinterRepository $printerRepository
     */
    public function __construct(PrinterRepository $printerRepository)
    {
        $this->printerRepository = $printerRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->printerRepository->getDataTableData($params);
    }

    /**
     * Get all printers
     */
    public function getAllPrinters()
    {
        return $this->printerRepository->all();
    }

    /**
     * Get printers with pagination
     */
    public function getPrintersPaginated(int $perPage = 10)
    {
        return $this->printerRepository->paginate($perPage);
    }

    /**
     * Get printer by encrypted ID
     */
    public function getPrinterByEncryptedId(string $encryptedId): ?Printer
    {
        return $this->printerRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Prepare data for creating/updating printer
     */
    protected function prepareData(array $data): array
    {
        $preparedData = [
            'title' => $data['title'],
            'invoice_print' => $data['invoice_print'],
            'user_id' => Auth::id(),
            'company_id' => session('company.company_id')
        ];

        // If invoice_print is live_server_print, include additional fields
        if (isset($data['invoice_print']) && $data['invoice_print'] == 'live_server_print') {
            $preparedData['type'] = $data['type'] ?? null;
            $preparedData['characters_per_line'] = $data['characters_per_line'] ?? null;
            $preparedData['print_server_url_invoice'] = $data['print_server_url_invoice'] ?? null;
            $preparedData['path'] = $data['path'] ?? null;
            $preparedData['fiscal_printer_status'] = $data['fiscal_printer_status'] ?? null;
            $preparedData['open_cash_drawer_when_printing_invoice'] = $data['open_cash_drawer_when_printing_invoice'] ?? null;

            // If type is network, include network-specific fields
            if (isset($data['type']) && $data['type'] == 'network') {
                $preparedData['printer_ip_address'] = $data['printer_ip_address'] ?? null;
                $preparedData['printer_port'] = $data['printer_port'] ?? null;
            } else {
                // Clear network-specific fields if type is not network
                $preparedData['printer_ip_address'] = null;
                $preparedData['printer_port'] = null;
                $preparedData['fiscal_printer_status'] = 'OFF';
                $preparedData['open_cash_drawer_when_printing_invoice'] = 'OFF';
            }
        } else {
            // If invoice_print is web_browser, clear all live_server_print related fields
            $preparedData['type'] = null;
            $preparedData['characters_per_line'] = null;
            $preparedData['print_server_url_invoice'] = null;
            $preparedData['path'] = null;
            $preparedData['printer_ip_address'] = null;
            $preparedData['printer_port'] = null;
            $preparedData['fiscal_printer_status'] = 'OFF';
            $preparedData['open_cash_drawer_when_printing_invoice'] = 'OFF';
        }

        // Handle printer_name if provided (for update)
        if (isset($data['printer_name'])) {
            $preparedData['printer_name'] = $data['printer_name'];
        }

        return $preparedData;
    }

    /**
     * Create a new printer
     */
    public function createPrinter(array $data): Printer
    {
        $preparedData = $this->prepareData($data);
        return $this->printerRepository->create($preparedData);
    }

    /**
     * Update an existing printer
     */
    public function updatePrinter(string $encryptedId, array $data): bool
    {
        $printer = $this->printerRepository->findByEncryptedId($encryptedId);
        
        if (!$printer) {
            throw new \Exception('Printer not found');
        }

        $preparedData = $this->prepareData($data);
        $preparedData['updated_at'] = now();

        return $this->printerRepository->update($printer, $preparedData);
    }

    /**
     * Delete a printer
     */
    public function deletePrinter(string $encryptedId): bool
    {
        $printer = $this->printerRepository->findByEncryptedId($encryptedId);
        
        if (!$printer) {
            throw new \Exception('Printer not found');
        }

        return $this->printerRepository->delete($printer);
    }

    /**
     * Search printers
     */
    public function searchPrinters(string $term)
    {
        return $this->printerRepository->search($term);
    }

    /**
     * Get printer statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->printerRepository->getTotalCount(),
        ];
    }

    /**
     * Get printers for select dropdown
     */
    public function listForSelect()
    {
        return $this->printerRepository->all()->map(function ($printer) {
            return [
                'id' => $printer->id,
                'title' => $printer->title,
            ];
        })->values();
    }
}

