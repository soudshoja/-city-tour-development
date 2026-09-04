<?php

namespace App\Services\Vouchers\Exceptions;

use RuntimeException;

/**
 * Thrown by VoucherService::issue() when the Task handed to it is dead —
 * its own status is 'void', or another task in the same company points
 * back at it via original_task_id (it has been superseded/reissued) —
 * F4, owner measurement 2026-08-27 on PNR 9VKQJP / task 8001 (status=void,
 * its only sibling also void): nothing in VoucherService or
 * VoucherController guarded against issuing a voucher for a dead task at
 * all — one click on the existing staff panel would produce a voucher
 * whose Passengers row prints the void ticket. This makes that refusal a
 * clear, staff-facing failure instead of a silent no-op or an exception
 * page: VoucherController::issueForTask() catches this and responds with
 * its message via the normal success/error JSON or flash-message path.
 */
class VoucherSubjectDeadException extends RuntimeException
{
    public static function forTask(int $taskId, string $reason): self
    {
        return new self("Task #{$taskId} cannot be issued a voucher: {$reason}");
    }
}
