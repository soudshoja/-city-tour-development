<?php

/*
|--------------------------------------------------------------------------
| GDS Office → Default Payment Method (Account) Mapping
|--------------------------------------------------------------------------
|
| When a task's `issued_by` (the GDS office code parsed from the supplier
| air file) appears in this map, the task edit form pre-selects the named
| account in the "Payment Method" dropdown. The account is resolved by
| name + company_id at render time, so the same config works across all
| envs even if account IDs differ.
|
| To add more mappings, just append entries here.
|
*/

return [
    'KWIKT2843' => 'Como Travel & Tourism',
    'KWIKT211N' => 'City Travelers (EasyPay)',
];
