<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * AgentsTemplateExport
 *
 * Downloadable Excel template for the company-registration wizard's bulk
 * agent upload (step 4). One example row is included; the example email is
 * a public constant so CompanyRegistrationRequest::prepareForValidation()
 * can recognise and drop it if the user re-uploads the template unedited.
 */
class AgentsTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public const EXAMPLE_NAME = 'Jane Doe';
    public const EXAMPLE_EMAIL = 'jane.doe@example.com';
    public const EXAMPLE_PHONE = '+96500000000';
    public const EXAMPLE_AMADEUS_SIGN = '00AG';

    public function array(): array
    {
        return [
            [self::EXAMPLE_NAME, self::EXAMPLE_EMAIL, self::EXAMPLE_PHONE, self::EXAMPLE_AMADEUS_SIGN],
        ];
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Amadeus Sign'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ],
        ];
    }
}
