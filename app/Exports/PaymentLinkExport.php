<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentLinkExport implements FromQuery, ShouldAutoSize, WithCustomChunkSize, WithHeadings, WithMapping, WithStyles
{
    protected Builder|QueryBuilder|Relation $query;

    public function __construct(Builder|QueryBuilder|Relation $query)
    {
        $this->query = $query;
    }

    public function query(): Builder|QueryBuilder|Relation
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Voucher Number',
            'Status',
            'Agent',
            'Client Name',
            'Client Phone',
            'Payment Gateway',
            'Payment Method',
            'Net Amount',
            'Service Charge',
            'Client Pay',
            'Currency',
            'Reference',
            'Created By',
            'Created At',
            'Paid At',
            'Notes',
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->voucher_number,
            ucfirst($payment->status ?? ''),
            $payment->agent?->name ?? 'N/A',
            $payment->client?->full_name ?? 'N/A',
            $payment->client ? ($payment->client->country_code.$payment->client->phone) : 'N/A',
            $payment->payment_gateway ?? 'N/A',
            $payment->paymentMethod?->english_name ?? 'N/A',
            $payment->amount,
            $payment->service_charge ?? 0,
            ($payment->amount ?? 0) + ($payment->service_charge ?? 0),
            $payment->currency ?? 'KWD',
            $payment->myFatoorahPayment?->invoice_ref ?? $payment->payment_reference ?? '',
            $payment->createdBy?->name ?? 'N/A',
            $payment->created_at?->format('d M Y H:i') ?? '',
            $payment->payment_date ? Carbon::parse($payment->payment_date)->format('d M Y H:i') : '',
            $payment->notes ?? '',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
