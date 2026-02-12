# Coding Conventions

**Analysis Date:** 2026-02-12

## Naming Patterns

**Files:**
- Classes use PascalCase: `TaskController.php`, `AirFileParser.php`, `NotificationTrait.php`
- Models match their database table concept: `Task.php`, `Company.php`, `Client.php`
- Controllers follow resource pattern: `TaskController.php`, `ClientController.php`, `InvoiceController.php`
- Traits are suffixed with `Trait`: `NotificationTrait.php`, `CurrencyExchangeTrait.php`, `Converter.php`
- Commands are descriptive verbs: `ProcessAirFiles.php`, `ReadAndProcessEmails.php`

**Functions:**
- Methods use camelCase: `getTasks()`, `saveFlightDetails()`, `processTaskFinancial()`
- Private methods prefixed with underscore or keyword: `private function getMissingFields()`, `_sanitizePdfName()`
- Test methods use `test_` prefix followed by descriptive snake_case: `test_agent_has_fillable_attributes()`, `test_parser_handles_invalid_files()`
- Helper functions use snake_case: `getCompanyId()`, `determineUserRole()`

**Variables:**
- Class properties use camelCase: `$content`, `$lines`, `$testDataPath`
- Loop variables are single letters or descriptive: `$q`, `$qq`, `$paxIdx`
- Protected/private properties use leading underscore or explicit visibility: `protected $fillable`, `private $content`

**Types:**
- Models use singular form: `Task`, `Company`, `Agent`
- Collections are plural: `$tasks`, `$companies`, `$agents`
- Boolean variables prefixed with `is` or `has`: `$isComplete`, `$hasFactory`
- Response objects use explicit naming: `ClientStoreResponse` (in `ClientController.php`)

## Code Style

**Formatting:**
- 4 spaces for indentation (defined in `.editorconfig`)
- LF line endings
- UTF-8 encoding
- Trailing whitespace trimmed
- Final newline inserted at end of files

**Linting:**
- Laravel Pint used for PSR-12 code formatting
- Command: `./vendor/bin/pint`
- Applied before commit

**PSR-12 Standards:**
- Namespace declarations at top of file: `namespace App\Models;`
- Grouped imports alphabetically
- Opening braces on same line: `public function test() {`
- Type hints on all parameters and return types where possible

## Import Organization

**Order:**
1. Global PHP classes: `use Exception;`, `use Carbon\Carbon;`
2. Laravel Framework imports: `use Illuminate\Database\Eloquent\Model;`, `use Illuminate\Http\Request;`
3. Package/vendor imports: `use Barryvdh\DomPDF\Facade\Pdf;`, `use Maatwebsite\Excel\Facades\Excel;`
4. Application imports (alphabetically): `use App\Models\Task;`, `use App\Services\AirFileParser;`, `use App\Http\Traits\NotificationTrait;`

**Path Aliases:**
- `App\` maps to `app/` directory
- `Database\Factories\` maps to `database/factories/`
- `Database\Seeders\` maps to `database/seeders/`
- `Tests\` maps to `tests/`

**Example from `/home/soudshoja/soud-laravel/app/Http/Controllers/TaskController.php`:**
```php
<?php

namespace App\Http\Controllers;

use Exception;
use App\AI\AIManager;
use App\Http\Traits\Converter;
use App\Http\Traits\CurrencyExchangeTrait;
use App\Http\Traits\NotificationTrait;
use App\Models\Task;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
```

## Error Handling

**Patterns:**
- Try-catch blocks for external API calls and file operations
- Generic `Exception` class used: `throw new Exception("File not found: {$filePath}")`
- Also uses `InvalidArgumentException` for validation: `throw new InvalidArgumentException("Invalid invoice status")`
- Methods return consistent array format on error: `AIResponse::error('Chat failed: ' . $e->getMessage())`
- Request validation via `$request->validate()` with automatic 422 response

**Example from `/home/soudshoja/soud-laravel/app/AI/AIManager.php`:**
```php
public function chat(array $messages): array
{
    try {
        return $this->client->chat($messages);
    } catch (Exception $e) {
        return AIResponse::error('Chat failed: ' . $e->getMessage());
    }
}
```

**Request Validation Pattern** (from `/home/soudshoja/soud-laravel/app/Http/Requests/DocumentProcessingRequest.php`):
- FormRequest classes validate input with `rules()` method
- Custom validation messages in `messages()` method
- Failed validation throws `HttpResponseException` with JSON response
- Custom validators as closures for complex logic

## Logging

**Framework:** Laravel Log facade (`Illuminate\Support\Facades\Log`)

**Patterns:**
- Used for debugging and error tracking: `Log::spy()` in tests to prevent actual logging
- File-based logging to `storage/logs/`
- Typically called as `Log::info()`, `Log::error()`, `Log::debug()`

**Where Used:**
- Error tracking: `/home/soudshoja/soud-laravel/app/Services/ErrorAlertService.php`
- Document processing: `/home/soudshoja/soud-laravel/app/Console/Commands/ProcessAirFiles.php`
- Webhook handling: `/home/soudshoja/soud-laravel/app/Http/Controllers/Api/Webhooks/N8nCallbackController.php`

## Comments

**When to Comment:**
- Method headers for public functions (brief description of purpose)
- Complex business logic that isn't immediately obvious
- Integration points with external services
- Algorithm explanations or non-standard approaches
- TODO/FIXME markers for incomplete work (6 found in codebase)

**Documentation Present:**
- PHPDoc comments on public methods (inconsistent coverage)
- Example from `/home/soudshoja/soud-laravel/app/Services/AirFileParser.php`:
```php
/**
 * Parse the AIR file and extract task schema data
 * Now returns an array of tasks for multiple passengers
 */
public function parseTaskSchema()
```

**No JSDoc/TSDoc** - This is PHP, not TypeScript/JavaScript

## Function Design

**Size:**
- Functions range from single-line getters to 400+ line controllers (complexity concern)
- Most methods 20-100 lines
- Large controllers (`TaskController.php` 6,392 lines, `PaymentController.php` 6,828 lines) suggest refactoring candidates

**Parameters:**
- Request objects passed for HTTP operations: `public function getTasks(Request $request): JsonResponse`
- Model instances passed for database operations: `public function show($id)` (should be type-hinted)
- Array parameters for complex data: `saveFlightDetails(array $data, int $taskId)`
- Type hints on all parameters preferred but not always present

**Return Values:**
- Controllers return `JsonResponse`, `View`, or `RedirectResponse`
- Services return arrays (often with status, message, data keys)
- Models return model instances or collections
- Methods consistently return same type

**Example from `/home/soudshoja/soud-laravel/app/Http/Controllers/TaskController.php` (lines 70-90):**
```php
public function getTasks(Request $request): JsonResponse
{
    Gate::authorize('viewAny', Task::class);

    $request->validate([
        'user_id' => 'nullable|exists:users,id',
        'filter' => 'nullable|array',
        'q' => 'nullable|string'
    ]);

    $user = User::find($request->user_id) ?? Auth::user();
    $whoIsUser = determineUserRole($user);

    // Complex query building...
}
```

## Module Design

**Exports:**
- Classes explicitly declare namespace at top
- Single class per file (no multiple class definitions)
- Full namespace path used: `namespace App\Http\Controllers;`

**Barrel Files:**
- Not used in this codebase

**Trait Organization:**
- Traits stored in `app/Http/Traits/` for controller concerns
- Traits used for shared functionality: `NotificationTrait`, `CurrencyExchangeTrait`, `Converter`
- Controllers typically use multiple traits via `use Trait1, Trait2;` syntax

**Example Trait Usage** from `/home/soudshoja/soud-laravel/app/Http/Controllers/TaskController.php` (line 68):
```php
class TaskController extends Controller
{
    use NotificationTrait, Converter, CurrencyExchangeTrait;
}
```

## Architecture Patterns

**Models:**
- Use Eloquent ORM exclusively
- Relationships defined as methods: `hasMany()`, `belongsTo()`, `manyToMany()`
- Fillable arrays define mass-assignable fields
- Casts define type casting: `protected $casts = ['issued_date' => 'datetime'];`
- Global scopes commented out but available for use

**Controllers:**
- Extend base `Controller` class from Laravel
- Use Gate facade for authorization: `Gate::authorize('viewAny', Task::class);`
- Heavy use of helper functions: `getCompanyId($user)`, `determineUserRole($user)`
- Mix of Eloquent queries and raw SQL (inconsistent)

**Services:**
- Location: `/app/Services/`
- Handle business logic: `AirFileParser.php`, `HotelSearchService.php`, `ErrorAlertService.php`
- Received via constructor injection or app container

**Helper Functions:**
- Defined in `/app/Helper/helper.php`
- Wrapped in `if(!function_exists())` guards to prevent redeclaration
- Used globally throughout application

## Database Patterns

**Migrations:**
- Located in `database/migrations/`
- Run with: `php artisan migrate`

**Models and Relationships:**
- Use `HasFactory` trait for testing
- Use `SoftDeletes` for audit trail: `use SoftDeletes;`
- Define relationships as methods on models

**Example Model** from `/home/soudshoja/soud-laravel/app/Models/Task.php`:
```php
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id', 'agent_id', 'company_id', 'supplier_id',
        'type', 'status', 'supplier_status', // ... many more
    ];

    protected $casts = [
        'issued_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];
}
```

---

*Convention analysis: 2026-02-12*
