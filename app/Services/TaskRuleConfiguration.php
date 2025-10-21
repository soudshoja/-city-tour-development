<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskRules;
use App\Enums\TaskRuleEnum;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TaskRuleConfiguration
{
    public function applyRules(Task $task, ?Task $existingTask = null): array
    {
        $companyId = $task->company_id;
        $supplierId = $task->supplier_id;

        if($companyId === null){
            throw new InvalidArgumentException("Task must have a company_id to apply rules.");
        }

        if($supplierId === null){
            throw new InvalidArgumentException("Task must have a supplier_id to apply rules.");
        }

        $taskRules = $this->getRulesForTask($companyId, $supplierId);
        
        if ($taskRules->isEmpty()) {
            Log::info("No task rules found, using default behavior", [
                'company_id' => $companyId, 
                'supplier_id' => $supplierId
            ]);
            return $task->toArray();
        }

        Log::info("Applying task rules", [
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'rules_count' => $taskRules->count(),
            'existing_task' => $existingTask ? $existingTask->id : null
        ]);

        $taskData = $task->toArray();
        
        foreach ($taskRules as $rule) {
            $taskData = $this->applyRule($rule, $taskData, $existingTask);
        }

        return $taskData;
    }

    protected function getRulesForTask(int $companyId, ?int $supplierId = null)
    {
        if ($supplierId) {
            $supplierRules = TaskRules::where('company_id', $companyId)
                ->where('supplier_id', $supplierId)
                ->get();
            
            return $supplierRules;
        }
        
        return collect();
    }

    protected function applyRule(TaskRules $rule, array $taskData, ?Task $existingTask = null): array
    {
        switch ($rule->name) {
            case TaskRuleEnum::DEFAULT->value:
                return $this->applyDefaultRule($rule, $taskData);
                
            case TaskRuleEnum::MINUS_EXISTING->value:
                return $this->applyMinusExistingRule($rule, $taskData, $existingTask);
                
            default:
                Log::warning("Unknown task rule: {$rule->name}");
                return $taskData;
        }
    }

    protected function applyDefaultRule(TaskRules $rule, array $taskData): array
    {
        Log::debug("Applying DEFAULT rule", ['rule_id' => $rule->id]);
        return $taskData;
    }

    protected function applyMinusExistingRule(TaskRules $rule, array $taskData, ?Task $existingTask = null): array
    {
        if (!$existingTask) {
            Log::info("MINUS_EXISTING rule but no existing task", ['rule_id' => $rule->id]);
            return $taskData;
        }

        if (!$rule->column) {
            Log::warning("MINUS_EXISTING rule missing column", ['rule_id' => $rule->id]);
            return $taskData;
        }

        $columnName = $rule->column;
        
        if (!array_key_exists($columnName, $taskData)) {
            Log::warning("Column {$columnName} not found", ['rule_id' => $rule->id]);
            return $taskData;
        }

        $newValue = (float) ($taskData[$columnName] ?? 0);
        $existingValue = (float) ($existingTask->{$columnName} ?? 0);
        $calculatedValue = $newValue - $existingValue;

        Log::info("Applied MINUS_EXISTING rule", [
            'rule_id' => $rule->id,
            'column' => $columnName,
            'calculation' => "{$newValue} - {$existingValue} = {$calculatedValue}",
            'existing_task_id' => $existingTask->id
        ]);

        $taskData[$columnName] = $calculatedValue;
        return $taskData;
    }

    public function getRulesForColumn(int $companyId, string $column)
    {
        return TaskRules::where('company_id', $companyId)
            ->where('column', $column)
            ->get();
    }

    public function hasRules(int $companyId): bool
    {
        return TaskRules::where('company_id', $companyId)->exists();
    }
}