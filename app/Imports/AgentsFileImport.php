<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * AgentsFileImport
 *
 * Parses an uploaded "bulk agents" Excel/CSV file into a raw 2D array (no
 * WithHeadingRow — CompanyRegistrationRequest::prepareForValidation() reads
 * columns positionally: [0]=name [1]=email [2]=phone [3]=amadeus_id) so the
 * header row's exact wording never matters.
 */
class AgentsFileImport implements ToArray
{
    public function array(array $array)
    {
        // Excel::toArray() returns the parsed sheet data directly to the
        // caller; this method only needs to exist to satisfy the ToArray
        // contract.
    }
}
