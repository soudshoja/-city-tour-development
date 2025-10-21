<?php

namespace App\Models;

use App\Enums\TaskRuleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class TaskRules extends Model
{
    protected $table = 'task_rules';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'name',
        'description',
        'column',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Validate rule name
            if (!in_array($model->name, array_map(fn($case) => $case->value, TaskRuleEnum::cases()))) {
                throw new InvalidArgumentException("Invalid task rule name: {$model->name}");
            }

            // Validate column only for rules that require it
            if ($model->name === TaskRuleEnum::MINUS_EXISTING->value) {
                if (empty($model->column)) {
                    throw new InvalidArgumentException("Column is required for rule: {$model->name}");
                }

                $taskModel = new Task();
                $taskColumns = Schema::getColumnListing($taskModel->getTable());
                
                if (!in_array($model->column, $taskColumns)) {
                    throw new InvalidArgumentException("Invalid column name: {$model->column}. Column does not exist in tasks table.");
                }
            }

            // For DEFAULT rule, column should be null
            if ($model->name === TaskRuleEnum::DEFAULT->value && !empty($model->column)) {
                $model->column = null;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForSupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeForColumn($query, $column)
    {
        return $query->where('column', $column);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    public function scopeCompanyWide($query, $companyId)
    {
        return $query->where('company_id', $companyId)->whereNull('supplier_id');
    }

    /**
     * Check if this rule requires a column
     */
    public function requiresColumn(): bool
    {
        return $this->name === TaskRuleEnum::MINUS_EXISTING->value;
    }

    /**
     * Check if this is a default rule
     */
    public function isDefault(): bool
    {
        return $this->name === TaskRuleEnum::DEFAULT->value;
    }

    /**
     * Get all available task columns for validation
     */
    public static function getAvailableColumns(): array
    {
        $taskModel = new Task();
        return Schema::getColumnListing($taskModel->getTable());
    }
}
