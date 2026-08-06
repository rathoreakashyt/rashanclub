<?php

namespace Modules\Configuration\Http\Request;

use App\Http\Requests\BaseRequest;

class PrinterRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'invoice_print' => ['required', 'string', 'in:web_browser,live_server_print'],
        ];

        // If invoice_print is live_server_print, add additional required fields
        if ($this->input('invoice_print') == 'live_server_print') {
            $rules = array_merge($rules, [
                'type' => ['required', 'string', 'max:25'],
                'characters_per_line' => ['required', 'integer'],
                'print_server_url_invoice' => ['required', 'string', 'max:255'],
                'path' => ['required', 'string', 'max:255'],
            ]);

            // If type is network, add network-specific required fields
            if ($this->input('type') == 'network') {
                $rules = array_merge($rules, [
                    'printer_ip_address' => ['required', 'string', 'max:255'],
                    'printer_port' => ['required', 'string', 'max:55'],
                    'fiscal_printer_status' => ['required', 'string'],
                    'open_cash_drawer_when_printing_invoice' => ['required', 'string'],
                ]);
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $title = __('Title');
        $printingChoice = __('Printing Choice');
        $printerType = __('Printer Type');
        $charactersPerLine = __('Characters Per Line');
        $ipv4Address = __('IPV4 Address');
        $shareName = __('Share Name');
        $printerIpAddress = __('Printer IP Address');
        $printerPort = __('Printer Port');
        $fiscalPrinterStatus = __('Fiscal Printer Status');
        $openCashDrawer = __('Open Cash Drawer Option');
        
        return [
            'title.required' => __('The') . ' ' . $title . ' ' . __('is required.'),
            'title.string' => __('The') . ' ' . $title . ' ' . __('must be a valid string.'),
            'title.max' => __('The') . ' ' . $title . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'invoice_print.required' => __('The') . ' ' . $printingChoice . ' ' . __('is required.'),
            'invoice_print.in' => __('The') . ' ' . $printingChoice . ' ' . __('must be either') . ' ' . __('web browser') . ' ' . __('or') . ' ' . __('direct print') . '.',
            'type.required' => __('The') . ' ' . $printerType . ' ' . __('is required when using direct print.'),
            'type.string' => __('The') . ' ' . $printerType . ' ' . __('must be a valid string.'),
            'type.max' => __('The') . ' ' . $printerType . ' ' . __('may not be greater than') . ' 25 ' . __('characters.'),
            'characters_per_line.required' => __('The') . ' ' . $charactersPerLine . ' ' . __('is required when using direct print.'),
            'characters_per_line.integer' => __('The') . ' ' . $charactersPerLine . ' ' . __('must be an integer.'),
            'print_server_url_invoice.required' => __('The') . ' ' . $ipv4Address . ' ' . __('is required when using direct print.'),
            'print_server_url_invoice.string' => __('The') . ' ' . $ipv4Address . ' ' . __('must be a valid string.'),
            'print_server_url_invoice.max' => __('The') . ' ' . $ipv4Address . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'path.required' => __('The') . ' ' . $shareName . ' ' . __('is required when using direct print.'),
            'path.string' => __('The') . ' ' . $shareName . ' ' . __('must be a valid string.'),
            'path.max' => __('The') . ' ' . $shareName . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'printer_ip_address.required' => __('The') . ' ' . $printerIpAddress . ' ' . __('is required when using network printer.'),
            'printer_ip_address.string' => __('The') . ' ' . $printerIpAddress . ' ' . __('must be a valid string.'),
            'printer_ip_address.max' => __('The') . ' ' . $printerIpAddress . ' ' . __('may not be greater than') . ' 255 ' . __('characters.'),
            'printer_port.required' => __('The') . ' ' . $printerPort . ' ' . __('is required when using network printer.'),
            'printer_port.string' => __('The') . ' ' . $printerPort . ' ' . __('must be a valid string.'),
            'printer_port.max' => __('The') . ' ' . $printerPort . ' ' . __('may not be greater than') . ' 55 ' . __('characters.'),
            'fiscal_printer_status.required' => __('The') . ' ' . $fiscalPrinterStatus . ' ' . __('is required when using network printer.'),
            'open_cash_drawer_when_printing_invoice.required' => __('The') . ' ' . $openCashDrawer . ' ' . __('is required when using network printer.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Title'),
            'invoice_print' => __('Printing Choice'),
            'type' => __('Printer Type'),
            'characters_per_line' => __('Characters Per Line'),
            'print_server_url_invoice' => __('IPV4 Address'),
            'path' => __('Share Name'),
            'printer_ip_address' => __('Printer IP Address'),
            'printer_port' => __('Printer Port'),
            'fiscal_printer_status' => __('Fiscal Printer Status'),
            'open_cash_drawer_when_printing_invoice' => __('Open Cash Drawer Option'),
        ];
    }
}

