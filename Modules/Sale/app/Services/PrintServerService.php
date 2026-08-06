<?php

namespace Modules\Sale\Services;

use Modules\Sale\Models\Sale;
use Modules\Sale\Repositories\SaleRepository;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Models\Outlet;

class PrintServerService
{
    public function __construct(
        protected SaleRepository $saleRepository
    ) {}

    /**
     * Build invoice content_data for receipt printer (ESC/POS).
     * Returns null if sale not found or printer not configured.
     */
    public function buildInvoiceContentData(string $saleIdInput): ?array
    {
        $sale = $this->resolveSale($saleIdInput);
        if (!$sale) {
            return null;
        }

        $printerId = getPrinterIdByRegisterID();
        if (!$printerId) {
            return null;
        }

        $printer = getPrinterInfo($printerId);
        if (!$printer) {
            return null;
        }

        $companyId = session('company.company_id');
        $outletId = session('outlet.outlet_id');
        $company = Company::find($companyId);
        $outlet = Outlet::find($outletId);

        if (!$company || !$outlet) {
            return null;
        }

        $charsPerLine = (int) ($printer->characters_per_line ?? 42);

        $data = [
            'print_type' => 'invoice',
            'logo' => $company->invoice_logo ?? '',
            'open_cash_drawer_when_printing_invoice' => $printer->open_cash_drawer_when_printing_invoice ?? 'OFF',
            'store_name' => $outlet->outlet_name ?? '',
            'address' => $outlet->address ?? '',
            'phone' => $outlet->phone ?? '',
            'collect_tax' => $company->collect_tax ?? 'No',
            'tax_title' => $company->tax_title ?? '',
            'tax_registration_no' => $company->tax_registration_no ?? '',
            'invoice_footer' => $company->invoice_footer ?? '',
            'type' => $printer->type ?? 'network',
            'printer_ip_address' => $printer->printer_ip_address ?? '',
            'printer_port' => $printer->printer_port ?? '9100',
            'path' => $printer->path ?? '',
            'characters_per_line' => $charsPerLine,
            'profile_' => $printer->profile_ ?? 'default',
            'date' => $sale->sale_date ? $sale->sale_date->format($company->date_format ?? 'Y-m-d') : '',
            'time_inv' => $sale->order_time ? $sale->order_time->format('h:i A') : '',
            'random_code' => url('invoice/' . ($sale->random_code ?? '')),
            'sales_associate' => $this->userName($sale->user_id),
            'customer_name' => '---',
            'customer_address' => '',
            'gst_number' => '',
        ];

        $customer = $sale->customer;
        if ($customer) {
            $data['customer_name'] = $customer->name ?? '---';
            $data['customer_address'] = !empty(trim((string) ($customer->address ?? ''))) ? ($customer->address ?? '') : '';
            $data['gst_number'] = !empty(trim((string) ($customer->gst_number ?? ''))) ? ($customer->gst_number ?? '') : '';
        }

        $items = "\n";
        $count = 1;
        $totalItems = 0;
        $sale->load(['saleDetails' => fn ($q) => $q->where('del_status', 'Live'), 'saleDetails.item']);

        foreach ($sale->saleDetails as $value) {
            $totalItems++;
            $menuUnitPrice = getAmtP($value->menu_unit_price);
            $itemName = $this->getItemNameCodeById($value->item_id, $value->item);
            $items .= printText('#' . $count . ' ' . $itemName, $charsPerLine) . "\n";
            $linePrice = '   ' . $value->qty . ' x ' . $menuUnitPrice . ':  ' . getAmt($value->menu_price_with_discount);
            $items .= printLine($linePrice, $charsPerLine, ' ') . "\n";
            $count++;
        }
        $data['items'] = $items;

        $data['sale_no_p'] = $sale->sale_no;
        $data['date_format_p'] = $company->date_format ?? 'Y-m-d';

        $totals = '';
        $totals .= printLine(__('Total Items') . ': ' . $totalItems, $charsPerLine) . "\n";
        if ($sale->sub_total && (float) $sale->sub_total != 0) {
            $totals .= printLine(__('Sub Total') . ': ' . getAmt($sale->sub_total), $charsPerLine) . "\n";
        }
        if ($sale->total_discount_amount && (float) $sale->total_discount_amount != 0) {
            $totals .= printLine(__('Disc Amount') . ': ' . getAmt($sale->total_discount_amount), $charsPerLine) . "\n";
        }
        if ($sale->delivery_charge && (float) $sale->delivery_charge != 0) {
            $totals .= printLine(__('Charge') . ': ' . $sale->delivery_charge . ($sale->charge_type ?? ''), $charsPerLine) . "\n";
        }

        if (($company->collect_tax ?? '') === 'Yes' && !empty($sale->sale_vat_objects)) {
            $tax = json_decode($sale->sale_vat_objects);
            if ($tax && is_array($tax)) {
                foreach ($tax as $t) {
                    $taxType = is_object($t) ? ($t->tax_field_type ?? '') : '';
                    $taxAmt = is_object($t) ? ($t->tax_field_amount ?? '0.00') : '0.00';
                    if ($taxAmt && (float) $taxAmt != 0) {
                        $totals .= printLine(' ' . $taxType . ':  ' . getAmt($taxAmt), $charsPerLine, ' ') . "\n";
                    }
                }
            }
        }
        if ($sale->rounding && (float) $sale->rounding != 0) {
            $totals .= printLine(__('Rounding') . ': ' . getAmt($sale->rounding), $charsPerLine) . "\n";
        }
        if ($sale->total_payable && (float) $sale->total_payable != 0) {
            $totals .= printLine(__('Total Payable') . ': ' . getAmt($sale->total_payable), $charsPerLine) . "\n";
        }
        if ($sale->paid_amount && (float) $sale->paid_amount != 0) {
            $totals .= printLine(__('Paid Amount') . ': ' . getAmt($sale->paid_amount), $charsPerLine) . "\n";
        }
        if ($sale->due_amount && (float) $sale->due_amount != 0) {
            $totals .= printLine(__('Due Amount') . ': ' . getAmt($sale->due_amount), $charsPerLine) . "\n";
        }

        $data['totals'] = $totals;

        $payments = '';
        $sale->load(['salePayments' => fn ($q) => $q->where('del_status', 'Live'), 'salePayments.paymentMethod']);
        foreach ($sale->salePayments as $p) {
            $paymentName = $p->paymentMethod ? $p->paymentMethod->name : ($p->payment_name ?? 'Payment');
            $payments .= printLine($paymentName . ':  ' . $p->amount, $charsPerLine, ' ') . "\n";
        }
        $data['payments'] = $payments;

        return $data;
    }

    protected function resolveSale(string $saleIdInput): ?Sale
    {
        if (ctype_digit($saleIdInput)) {
            return $this->saleRepository->getByIdWithDetails((int) $saleIdInput);
        }
        try {
            $id = decrypt($saleIdInput);
            return $this->saleRepository->getByIdWithDetails((int) $id);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function userName(?int $userId): string
    {
        if (!$userId) {
            return '---';
        }
        $user = \App\Models\User::find($userId);
        return $user ? $user->name : '---';
    }

    protected function getItemNameCodeById(int $itemId, $item = null): string
    {
        if ($item) {
            $name = $item->name ?? '';
            $code = $item->code ?? '';
            return trim($name . ($code ? ' [' . $code . ']' : ''));
        }
        $row = \Illuminate\Support\Facades\DB::table('items')->where('id', $itemId)->first();
        if (!$row) {
            return 'Item#' . $itemId;
        }
        $name = $row->name ?? '';
        $code = $row->code ?? '';
        return trim($name . ($code ? ' [' . $code . ']' : ''));
    }
}
