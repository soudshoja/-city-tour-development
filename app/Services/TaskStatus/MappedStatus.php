<?php

declare(strict_types=1);

namespace App\Services\TaskStatus;

use App\Models\SupplierStatusMap;

/**
 * Return value of {@see \App\Services\TaskStatusService::mapStatus()}. Plain immutable DTO --
 * carries the resolved canonical status plus enough provenance (which row resolved it, and at
 * which resolution level) for the W6.U "test a raw status" preview and the supplier-status-map
 * screen's own "which row resolved it" display, without callers needing to re-query
 * `supplier_status_maps` themselves.
 */
final class MappedStatus
{
    public const LEVEL_SUPPLIER = 'company_supplier';
    public const LEVEL_COMPANY_DEFAULT = 'company_default';
    public const LEVEL_GLOBAL_SUPPLIER = 'global_supplier';
    public const LEVEL_GLOBAL_DEFAULT = 'global_default';
    public const LEVEL_UNMAPPED = 'unmapped';
    public const LEVEL_OVERRIDE = 'override';

    public function __construct(
        public readonly string $canonicalStatus,
        public readonly string $resolutionLevel,
        public readonly ?SupplierStatusMap $row = null,
        public readonly ?string $deadlineSource = null,
    ) {
    }

    public function isUnmapped(): bool
    {
        return $this->canonicalStatus === 'needs_review';
    }
}
