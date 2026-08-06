<?php

namespace Modules\Sale\Services;

use Mike42\Escpos\Printer;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

/**
 * Sends receipt data to ESC/POS printer (Mike42).
 */
class ReceiptPrintService
{
    /**
     * Print invoice receipt. $data is the content_data object (array or stdClass) from PrintServerService.
     */
    public function printReceipt($data): void
    {
        $data = is_array($data) ? (object) $data : $data;

        $connector = $this->createConnector($data);
        $profile = CapabilityProfile::load($data->profile_ ?? 'default');
        $printer = new Printer($connector, $profile);

        $charsPerLine = (int) ($data->characters_per_line ?? 42);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 2);
        $printer->text(printText($data->store_name ?? '', $charsPerLine) . "\n");
        $printer->setEmphasis(false);
        $printer->setTextSize(1, 1);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text(printText($data->address ?? '', $charsPerLine) . "\n");
        $printer->text('Phone: ' . ($data->phone ?? '') . "\n");
        if (($data->collect_tax ?? '') === 'Yes' && !empty($data->tax_registration_no)) {
            $printer->text(($data->tax_title ?? '') . ':' . ($data->tax_registration_no ?? '') . "\n");
        }
        $printer->text('Invoice No: ' . ($data->sale_no_p ?? '') . "\n");
        $printer->feed();
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text('Date: ' . ($data->date ?? '') . ' ' . ($data->time_inv ?? '') . "\n");
        $printer->text('Sales Associate: ' . ($data->sales_associate ?? '') . "\n");
        $printer->text('Customer: ' . ($data->customer_name ?? '---') . "\n");
        if (!empty($data->customer_address ?? '')) {
            $printer->text('Address: ' . ($data->customer_address ?? '') . "\n");
        }
        if (!empty($data->gst_number ?? '')) {
            $printer->text('GST Number: ' . ($data->gst_number ?? '') . "\n");
        }

        $printer->text($data->items ?? '');
        $printer->text(drawLine($charsPerLine));

        $printer->text($data->totals ?? '');
        $printer->text(drawLine($charsPerLine));

        $printer->text($data->payments ?? '');
        $printer->text(drawLine($charsPerLine));

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text(printText($data->invoice_footer ?? '', $charsPerLine) . "\n");
        if (!empty($data->random_code ?? '')) {
            $printer->qrCode($data->random_code ?? '');
        }
        $printer->setEmphasis(false);
        $printer->cut();

        if (!empty($data->open_cash_drawer_when_printing_invoice) && $data->open_cash_drawer_when_printing_invoice === 'ON') {
            $printer->pulse();
        }

        $printer->close();
    }

    protected function createConnector($data)
    {
        $type = $data->type ?? 'network';
        if ($type === 'network') {
            return new NetworkPrintConnector(
                $data->printer_ip_address ?? '127.0.0.1',
                (int) ($data->printer_port ?? 9100)
            );
        }
        if ($type === 'linux') {
            return new FilePrintConnector($data->path ?? '/dev/usb/lp0');
        }
        return new WindowsPrintConnector($data->path ?? 'POS-80');
    }
}
