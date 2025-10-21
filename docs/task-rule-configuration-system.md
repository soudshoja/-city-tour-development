# TaskRuleConfiguration System

## Overview

The TaskRuleConfiguration system provides a flexible way to apply custom business rules when creating tasks, based on company-specific configurations stored in the `task_rules` table.

## Components

### 1. TaskRules Model (`app/Models/TaskRules.php`)
- Stores rule configurations per company
- Validates rule names and required columns
- Provides helper methods and relationships

### 2. TaskRuleEnum (`app/Enums/TaskRuleEnum.php`)
- Defines available rule types:
  - `DEFAULT`: No modifications (default behavior)
  - `MINUS_EXISTING`: Subtract existing task value from new task value

### 3. TaskRuleConfiguration Service (`app/Services/TaskRuleConfiguration.php`)
- Main service that applies rules to task data
- Processes rules based on company configuration
- Logs all rule applications for debugging

## Rule Types

### DEFAULT Rule
- **Purpose**: Maintains original behavior (no modifications)
- **Column Required**: No (should be null)
- **Usage**: When you want explicit default behavior for a company
- **Example**:
  ```php
  TaskRules::create([
      'company_id' => 1,
      'name' => 'default',
      'description' => 'Default task behavior',
      'column' => null
  ]);
  ```

### MINUS_EXISTING Rule
- **Purpose**: Subtract existing task field value from new task field value
- **Column Required**: Yes (specify which field to subtract from)
- **Usage**: For reissued tickets where you want to calculate the difference
- **Example**:
  ```php
  TaskRules::create([
      'company_id' => 1,
      'name' => 'minus_existing',
      'description' => 'Subtract existing price for reissued tickets',
      'column' => 'price'
  ]);
  ```

## How It Works

### 1. Rule Priority System
1. **Supplier-specific rule**: If supplier has `task_rule_id`, use that specific rule
2. **Company-wide rules**: If no supplier rule, use all company rules
3. **Default behavior**: If no rules found, return data unchanged

### 2. Rule Application Flow
```php
// 1. Check if supplier has specific rule
if ($supplierId && $supplier->task_rule_id) {
    $taskRules = TaskRules::where('id', $supplier->task_rule_id)->get();
} else {
    $taskRules = TaskRules::where('company_id', $companyId)->get();
}

// 2. Apply each rule to task data
foreach ($taskRules as $rule) {
    $taskData = $this->applyRule($rule, $taskData, $existingTask);
}
```

### 2. MINUS_EXISTING Example
```php
// Original task data
$taskData = ['price' => 100.00, 'total' => 120.00];

// Existing task
$existingTask = new Task(['price' => 50.00, 'total' => 60.00]);

// Rule: MINUS_EXISTING for 'price' column
// Result: price becomes 100.00 - 50.00 = 50.00
// total remains 120.00 (no rule for total)
```

## Integration with TaskController

### Current Integration Point
In your TaskController@store method, around line 778 where you handle existing tasks:

```php
if ($existingTask) {
    $ruleConfig = new TaskRuleConfiguration();
    $task = new Task($request->all());
    $modifiedData = $ruleConfig->applyRules($task, $existingTask);
    
    $request->merge($modifiedData);
}
```

### Benefits Over Current Hardcoded Logic
1. **Flexible**: Rules are database-driven, not hardcoded
2. **Supplier-Specific**: Each supplier can have its own rule
3. **Company-Specific**: Fallback to company-wide rules
4. **Extensible**: Easy to add new rule types
5. **Maintainable**: Rules can be changed without code deployment
6. **Auditable**: All rule applications are logged

## Database Setup

### 1. TaskRules Table Structure
```sql
CREATE TABLE task_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    column VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);
```

### 2. Seeding Rules
```php
// In TaskRuleSeeder or database seeder
TaskRules::create([
    'company_id' => 1,
    'name' => 'minus_existing',
    'description' => 'For Jazeera Airways reissued tickets',
    'column' => 'total'
]);
```

## Testing

### 1. Unit Tests
Run the unit tests to verify functionality:
```bash
php artisan test tests/Unit/TaskRuleConfigurationTest.php
```

### 2. Console Command Testing
Test the configuration with the Artisan command:
```bash
php artisan task:test-rules 1
```

## Configuration Examples

### Example 1: Jazeera Airways Supplier-Specific Rule
```php
// Create rule for Jazeera Airways
$jazeeraRule = TaskRules::create([
    'company_id' => 1,
    'name' => 'minus_existing',
    'description' => 'Subtract existing total for Jazeera Airways reissues',
    'column' => 'total'
]);

// Assign rule to Jazeera Airways supplier
$jazeeraSupplier = Supplier::where('name', 'Jazeera Airways')->first();
$jazeeraSupplier->task_rule_id = $jazeeraRule->id;
$jazeeraSupplier->save();
```

### Example 2: Multiple Column Rules
```php
// Rule for price column
TaskRules::create([
    'company_id' => 1,
    'name' => 'minus_existing',
    'description' => 'Subtract existing price',
    'column' => 'price'
]);

// Rule for surcharge column
TaskRules::create([
    'company_id' => 1,
    'name' => 'minus_existing',
    'description' => 'Subtract existing surcharge',
    'column' => 'surcharge'
]);
```

### Example 3: Mixed Rules (Supplier + Company)
```php
// Company-wide default rule
$defaultRule = TaskRules::create([
    'company_id' => 1,
    'name' => 'default'
]);

// Specific rule for Jazeera Airways
$jazeeraRule = TaskRules::create([
    'company_id' => 1,
    'name' => 'minus_existing',
    'column' => 'total'
]);

// Assign suppliers
Supplier::where('name', 'Jazeera Airways')
    ->update(['task_rule_id' => $jazeeraRule->id]);

Supplier::where('name', 'Other Supplier')
    ->update(['task_rule_id' => $defaultRule->id]);
```

## Future Extensions

The system is designed to be easily extensible. Future rule types could include:

1. **PERCENTAGE_OF_EXISTING**: Calculate percentage of existing value
2. **FIXED_AMOUNT**: Add/subtract fixed amount
3. **CONDITIONAL_RULES**: Rules based on supplier, status, etc.
4. **DATE_BASED_RULES**: Rules based on dates or time periods

## Migration Strategy

1. **Phase 1**: Set up the system (current)
2. **Phase 2**: Add rules for existing hardcoded logic
3. **Phase 3**: Replace hardcoded logic with rule system
4. **Phase 4**: Test thoroughly with different scenarios
5. **Phase 5**: Deploy and monitor

This approach allows you to gradually migrate from hardcoded business logic to a flexible, database-driven rule system.