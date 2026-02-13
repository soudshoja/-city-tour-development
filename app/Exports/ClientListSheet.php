<?php

namespace App\Exports;

use App\Models\Client;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * ClientListSheet
 *
 * Second sheet containing the company's client list for reference.
 */
class ClientListSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @var int
     */
    protected $companyId;

    /**
     * Create a new sheet instance.
     *
     * @return void
     */
    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Return collection of clients for the company.
     *
     * @return Collection
     */
    public function collection()
    {
        return Client::where('company_id', $this->companyId)
            ->select('name', 'phone', 'email', 'civil_no')
            ->get();
    }

    /**
     * Return column headings.
     */
    public function headings(): array
    {
        return [
            'client_name',
            'mobile',
            'email',
            'civil_no',
        ];
    }

    /**
     * Return the sheet title.
     */
    public function title(): string
    {
        return 'Client List';
    }
}
