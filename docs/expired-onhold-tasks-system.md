# Expired Confirmed Tasks Processing System

## Overview

This system automatically manages task status transitions for tasks with "confirmed" status based on their expiry dates. When tasks remain "confirmed" after their expiry date, they are automatically changed to "void" status with proper financial processing.

## Features

- **Automatic Status Management**: Converts expired "confirmed" tasks to "void"
- **Event-Driven Architecture**: Uses Laravel events and listeners for clean separation of concerns
- **Financial Integration**: Automatically triggers Chart of Accounts (CoA) processing when tasks become "void"
- **Efficient Scheduling**: Runs every 5 minutes without performance overhead
- **Comprehensive Logging**: Full audit trail of all status changes
- **Dry-Run Mode**: Test the system without making actual changes

## Business Logic

### Current Process Understanding:
- Tasks come in with "on hold" status
- For certain suppliers (Jazeera Airways, Fly Dubai, VFS), "on hold" tasks are automatically converted to "confirmed" on creation
- If tasks remain "confirmed" after expiry date, they need to be voided

### When a "confirmed" task expires:
1. **Simple Rule**: Change status from "confirmed" to "void"
2. **Financial Processing**: Trigger CoA cleanup for void tasks
3. **Ignore**: Tasks that are already "issued" or other statuses

## Components

### 1. Scheduled Command
**File**: `app/Console/Commands/ProcessExpiredConfirmedTasks.php`

- Runs every 5 minutes via Laravel scheduler
- Queries only expired "confirmed" tasks for efficiency
- Changes all expired "confirmed" tasks to "void"
- Supports dry-run mode for testing
- Comprehensive error handling and logging

### 2. Event System
**Event**: `app/Events/CheckConfirmedOrIssuedTask.php`
**Listener**: `app/Listeners/ProcessTaskFinancials.php`

- Decoupled financial processing from status changes
- Queued listener for reliability
- Automatic retry on failure
- Detailed logging for audit trail

### 3. Database Migration
**File**: `database/migrations/2025_08_11_153239_add_expiry_date_to_tasks_table.php`

- Adds `expiry_date` timestamp field to tasks table
- Creates optimized index for efficient querying
- Nullable field for backward compatibility

## Usage

### Manual Execution
```bash
# Process expired tasks
php artisan tasks:process-expired-confirmed

# Test without making changes
php artisan tasks:process-expired-confirmed --dry-run
```

### Setting Expiry Dates
When creating tasks with "confirmed" status, set the `expiry_date` field:

```php
$task = Task::create([
    'status' => 'confirmed',
    'reference' => 'BOOKING123',
    'expiry_date' => Carbon::now()->addDays(7), // Expires in 7 days
    // ... other fields
]);
```

### Triggering Events Manually
```php
// Trigger financial processing for a task
event(new CheckConfirmedOrIssuedTask($task, 'manual_trigger'));
```

## Configuration

### Scheduler Configuration
The command is registered in `app/Console/Kernel.php`:

```php
$schedule->command('tasks:process-expired-confirmed')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

### Event Registration
Events are registered in `app/Providers/AppServiceProvider.php`:

```php
Event::listen(
    CheckConfirmedOrIssuedTask::class,
    ProcessTaskFinancials::class
);
```

## Monitoring and Logging

### Log Locations
- **Application Logs**: `storage/logs/laravel.log`
- **Scheduler Logs**: Check your system's cron logs

### Key Log Messages
- `Processing expired confirmed tasks at: {timestamp}`
- `Task status changed from 'confirmed' to 'void' due to expiry`
- `Successfully processed task financials`

### Monitoring Commands
```bash
# Check recent processing activity
tail -f storage/logs/laravel.log | grep "expired confirmed"

# Test the command manually
php artisan tasks:process-expired-confirmed --dry-run

# Check scheduler status (if using Laravel Horizon)
php artisan horizon:status
```

## Testing

### Unit Tests
Run the comprehensive test suite:

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test tests/Feature/ExpiredConfirmedTasksTest.php

# Run with detailed output
php artisan test --verbose
```

### Manual Testing Scenarios

1. **Basic Expiry to Void**:
   - Create task with status "on hold" and past expiry date
   - Run command
   - Verify status changes to "void"

2. **Expiry to Issued with Confirmed Task**:
   - Create "on hold" task with past expiry date
   - Create newer "confirmed" task with same reference
   - Run command
   - Verify status changes to "issued"

3. **Non-expired Tasks**:
   - Create "on hold" task with future expiry date
   - Run command
   - Verify status remains unchanged

## Performance Considerations

- **Efficient Querying**: Uses database indexes on `status` and `expiry_date`
- **Batch Processing**: Processes all expired tasks in single command run
- **Background Execution**: Scheduled command runs in background
- **Resource Limits**: Automatically handles memory and time limits
- **Overlap Prevention**: Uses `withoutOverlapping()` to prevent concurrent runs

## Troubleshooting

### Common Issues

1. **Command Not Running**
   - Check if Laravel scheduler is configured properly
   - Verify cron job is set up: `* * * * * php /path/to/artisan schedule:run`

2. **Financial Processing Fails**
   - Check supplier company activation
   - Verify required accounts exist in CoA
   - Check task completion status

3. **Events Not Firing**
   - Verify event listener registration
   - Check queue worker status if using queues
   - Review application logs for errors

### Debug Commands
```bash
# Check scheduler configuration
php artisan schedule:list

# Test event system
php artisan tinker
> event(new App\Events\CheckConfirmedOrIssuedTask(App\Models\Task::first()));

# Check queue status
php artisan queue:work --once
```

## Security Considerations

- **Transaction Safety**: All database operations use transactions
- **Input Validation**: Proper validation of task data
- **Error Handling**: Graceful failure handling prevents data corruption
- **Audit Trail**: Complete logging of all actions for compliance

## Future Enhancements

- **Configurable Expiry Rules**: Different expiry periods per supplier/company
- **Email Notifications**: Alert stakeholders of status changes
- **Dashboard Integration**: Real-time monitoring of expired tasks
- **Advanced Retry Logic**: Sophisticated retry mechanisms for failed financial processing
- **Bulk Processing API**: REST API for bulk status updates
