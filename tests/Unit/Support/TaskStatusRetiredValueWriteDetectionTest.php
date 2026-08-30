<?php

namespace Tests\Unit\Support;

use Tests\Feature\Accounting\ArchitectureTest;
use Tests\TestCase;

/**
 * W6.I re-verify fix round -- pins {@see ArchitectureTest::isTaskStatusRetiredValueWrite()}'s
 * exact decision logic against synthetic strings (no real `app/` filesystem scan), regression-
 * testing the CI-fragility false positive the previous verify pass flagged: the ORIGINAL
 * `tests_no_new_writes_of_ticketed_or_refunded_task_status` ratchet scanned for `->status =
 * 'ticketed'|'refunded'` / `'status' => 'ticketed'|'refunded'` with nothing tying either shape to
 * the `tasks` TABLE specifically -- a legitimate `invoices.status = 'refunded'` write (a real,
 * valid value on a DIFFERENT table per importer-status-contract.md's own Table 1) would also have
 * matched.
 */
class TaskStatusRetiredValueWriteDetectionTest extends TestCase
{
    // ── Genuine violations (must still be caught) ────────────────────────────────────────────────

    public function test_task_property_assignment_to_ticketed_is_a_violation(): void
    {
        $this->assertTrue(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "        \$task->status = 'ticketed';\n",
            "        \$task->status = 'ticketed';\n"
        ));
    }

    public function test_task_variable_property_assignment_to_refunded_is_a_violation(): void
    {
        $this->assertTrue(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "        \$originalTask->status = 'refunded';\n",
            "        \$originalTask->status = 'refunded';\n"
        ));
    }

    public function test_array_literal_status_ticketed_inside_task_create_context_is_a_violation(): void
    {
        $window = "        Task::create([\n            'reference' => \$reference,\n            'status' => 'ticketed',\n";
        $this->assertTrue(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "            'status' => 'ticketed',\n",
            $window
        ));
    }

    public function test_array_literal_status_refunded_inside_task_update_context_is_a_violation(): void
    {
        $window = "        \$task->update([\n            'status' => 'refunded',\n";
        $this->assertTrue(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "            'status' => 'refunded',\n",
            $window
        ));
    }

    // ── False positives the fix round eliminates ─────────────────────────────────────────────────

    public function test_invoice_property_assignment_to_refunded_is_not_a_violation(): void
    {
        // A real, legitimate value on invoices.status (importer-status-contract.md Table 1) --
        // must never be mistaken for a tasks.status write.
        $this->assertFalse(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "        \$invoice->status = 'refunded';\n",
            "        \$invoice->status = 'refunded';\n"
        ));
    }

    public function test_bare_unqualified_property_assignment_to_ticketed_is_not_a_violation(): void
    {
        // No variable name at all to anchor on to a Task -- must not match by accident.
        $this->assertFalse(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "        \$anything->status = 'ticketed';\n",
            "        \$anything->status = 'ticketed';\n"
        ));
    }

    public function test_array_literal_status_refunded_with_no_task_context_in_window_is_not_a_violation(): void
    {
        // A plain Invoice::create() array literal -- the preceding window mentions no Task at
        // all, so this must not be flagged.
        $window = "        Invoice::create([\n            'invoice_number' => \$number,\n            'status' => 'refunded',\n";
        $this->assertFalse(ArchitectureTest::isTaskStatusRetiredValueWrite(
            "            'status' => 'refunded',\n",
            $window
        ));
    }

    public function test_bare_read_via_where_clause_is_not_a_violation(): void
    {
        $line = "        Task::where('status', 'ticketed')->get();\n";
        $this->assertFalse(ArchitectureTest::isTaskStatusRetiredValueWrite($line, $line));
    }

    public function test_enum_literal_list_in_array_read_is_not_a_violation(): void
    {
        $line = "        in_array(\$status, ['ticketed', 'refunded'], true);\n";
        $this->assertFalse(ArchitectureTest::isTaskStatusRetiredValueWrite($line, $line));
    }
}
