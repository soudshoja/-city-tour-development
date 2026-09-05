<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reconciliation;

/**
 * accounting-builds T8 (Lane E). A plain marker import object for
 * `Excel::toArray($import, $filePath)` (Maatwebsite\Excel\Excel::toArray() — see vendor source,
 * `Reader::toArray()`/`Excel::toArray()`) reading an XLSX statement's FIRST sheet as a raw 2D
 * grid: no `WithHeadingRow` (that formatter slugifies headers, e.g. "Booking Reference" ->
 * "booking_reference", which would silently break an exact-label column map), no `ToModel`/
 * `ToArray` per-row callback (`Excel::toArray()` returns the grid directly from the reader — it
 * does not invoke an `array()`/`model()` hook on $import at all; those only fire on
 * `Excel::import()`). {@see SupplierStatementImporter} reads the literal first row as headers
 * itself and every subsequent row as data.
 */
final class RawGridImport {}
