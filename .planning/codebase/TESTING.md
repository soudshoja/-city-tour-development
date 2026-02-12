# Testing Patterns

**Analysis Date:** 2026-02-12

## Test Framework

**Runner:**
- PHPUnit 11.0.1
- Config: `phpunit.xml` at project root
- Test suites: Unit tests (`tests/Unit/`) and Feature tests (`tests/Feature/`)

**Assertion Library:**
- PHPUnit assertions built-in
- Laravel testing assertions for HTTP and database

**Run Commands:**
```bash
php artisan test                    # Run all tests
php artisan test --filter TestName  # Run specific test
php artisan test --coverage         # Run with coverage report
```

## Test File Organization

**Location:**
- Tests co-located in parallel directory structure: `tests/Unit/` mirrors `app/`
- Example: Model tests in `tests/Unit/`, Controller/Feature tests in `tests/Feature/`

**Naming:**
- Test files end with `Test.php`: `TaskTest.php`, `AgentTest.php`, `ClientTest.php`
- Test classes extend `Tests\TestCase`
- Separate Feature and Unit test suites in phpunit.xml

**Structure:**
```
tests/
├── Feature/              # HTTP and integration tests
│   ├── Admin/
│   ├── Api/
│   ├── Auth/
│   ├── Integration/
│   ├── Security/
│   ├── TaskTest.php
│   ├── ClientTest.php
│   └── InvoiceTest.php
├── Unit/                 # Model and service unit tests
│   ├── Services/
│   ├── Console/
│   ├── AI/
│   ├── AgentTest.php
│   └── ExampleTest.php
├── Fixtures/            # Test data factories
│   ├── N8nResponseFactory.php
│   └── FixtureLoader.php
└── TestCase.php         # Base test class
```

## Test Structure

**Base Test Class** (`/home/soudshoja/soud-laravel/tests/TestCase.php`):
```php
abstract class TestCase extends BaseTestCase
{
    protected bool $skipPermissionSeeder = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            !$this->skipPermissionSeeder &&
            in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))
        ) {
            $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        }
    }
}
```

**Patterns:**

1. **Trait Usage:**
   - `RefreshDatabase` trait for database isolation
   - `WithFaker` trait for fake data generation
   - Applied at class level: `use RefreshDatabase;`

2. **Setup Pattern** (from `/home/soudshoja/soud-laravel/tests/Unit/AgentTest.php`):
```php
class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected $agent;
    protected $user;
    protected $branch;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create prerequisites
        $this->agentType = AgentType::create([
            'id' => 1,
            'name' => 'Commission',
        ]);

        $companyUser = User::factory()->create([
            'role_id' => Role::COMPANY
        ]);

        $this->company = Company::factory()->create([
            'user_id' => $companyUser->id
        ]);
        session(['company_id' => $this->company->id]);

        // Create final test subject
        $this->agent = Agent::factory()->create([
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
        ]);
    }
}
```

3. **Teardown Pattern:**
   - No explicit teardown methods found
   - `RefreshDatabase` handles cleanup automatically after each test

4. **Assertion Pattern:**
   - Direct model property assertion: `$this->assertEquals($fillableAttributes, $this->agent->getFillable());`
   - Relationship assertion: `$this->assertInstanceOf(User::class, $this->agent->user);`
   - Database assertion: `$this->assertDatabaseHas('tasks', ['reference' => $reference]);`

## Mocking

**Framework:** Mockery 1.6

**Patterns (from `/home/soudshoja/soud-laravel/tests/Feature/Integration/EndToEndDocumentProcessingTest.php`):**
```php
// Mock HTTP responses
Http::fake([
    config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
]);

// Spy on Laravel services
Log::spy();
```

**What to Mock:**
- External API calls (N8n, OpenAI, payment gateways)
- HTTP requests via `Http::fake()`
- Logging via `Log::spy()` to prevent actual log writes
- File operations where appropriate

**What NOT to Mock:**
- Database operations (use real database with RefreshDatabase)
- Eloquent models (use factories to create test data)
- Laravel container/facades (test with real implementations)
- Authentication (use createUser() or auth()->login())

## Fixtures and Factories

**Test Data Location:**
- Factories: `database/factories/` (Laravel Model Factories)
- Fixtures: `tests/Fixtures/` for static test data
  - `N8nResponseFactory.php` - Generates mock N8n responses
  - `FixtureLoader.php` - Loads test data files

**Factory Pattern** (implicit from factories directory):
- Models use `HasFactory` trait
- Call via: `User::factory()->create(['role_id' => Role::ADMIN])`
- Create unsaved instances: `User::factory()->make()`

**Fixture Example** (`tests/Fixtures/N8nResponseFactory.php`):
- Generates realistic webhook payloads for testing
- Used in integration tests

## Coverage

**Requirements:** Not enforced (no coverage configuration in phpunit.xml)

**View Coverage:**
```bash
php artisan test --coverage
```

**Coverage Settings in phpunit.xml:**
- Source includes `app/` directory only
- No coverage threshold set

## Test Types

**Unit Tests:**
- Location: `tests/Unit/`
- Scope: Test individual classes in isolation
- Example: `tests/Unit/Services/AirFileParserTest.php` tests the parser without database
- Approach: Create service, call methods, assert results

**Integration Tests:**
- Location: `tests/Feature/Integration/`
- Scope: Test workflows involving multiple components
- Examples:
  - `EndToEndDocumentProcessingTest.php` - Full Laravel → N8n → Laravel flow
  - `N8nDocumentProcessingTest.php` - N8n webhook contract validation
  - `WebhookContractTest.php` - Webhook signature verification
- Approach: Set up data, trigger action, verify side effects (database, HTTP calls)

**Feature Tests:**
- Location: `tests/Feature/`
- Scope: Test HTTP endpoints and user workflows
- Examples: `TaskTest.php`, `ClientTest.php`, `InvoiceTest.php`
- Approach: Make HTTP requests, assert responses and database state

**E2E Tests:**
- Location: Not found in standard locations
- Load tests exist: `tests/Load/DocumentProcessingLoadTest.php` for performance testing
- Not using dedicated E2E framework (no Dusk/Playwright tests)

## Common Patterns

**Async Testing:**
Not explicitly found - Laravel uses synchronous queue for testing (`QUEUE_CONNECTION=sync` in phpunit.xml)

**Example of Async Handling** (from `phpunit.xml`):
```xml
<env name="QUEUE_CONNECTION" value="sync"/>
```

**Error Testing** (from `/home/soudshoja/soud-laravel/tests/Unit/Services/AirFileParserTest.php`):
```php
public function test_parser_handles_invalid_files()
{
    $tempFile = tempnam(sys_get_temp_dir(), 'invalid_air_');
    file_put_contents($tempFile, "INVALID CONTENT\nNOT A VALID AIR FILE");

    try {
        $parser = new AirFileParser($tempFile);
        $result = $parser->parseTaskSchema();

        $this->assertIsArray($result);

    } catch (\Exception $e) {
        $this->assertInstanceOf(\Exception::class, $e);
    } finally {
        unlink($tempFile);
    }
}
```

**DataProvider Pattern** (from AirFileParserTest.php):
```php
#[\PHPUnit\Framework\Attributes\DataProvider('airFileTestCaseProvider')]
public function test_individual_air_file_extraction($testCase)
{
    $this->runSingleTestCase($testCase);
}
```

**Permission Testing** (from `/home/soudshoja/soud-laravel/tests/Feature/TaskTest.php`):
- PermissionSeeder run automatically in setUp() when RefreshDatabase used
- Roles created manually: `Role::firstOrCreate(['name' => 'admin'])`
- Permissions assigned: `$adminRole->givePermissionTo($adminPermissions);`
- Authorization tested via Gate: Tests authenticate as different roles

**Example Permission Setup:**
```php
$adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
$adminPermissions = [
    'create task', 'view task', 'update task', 'delete task',
    'create supplier', 'view supplier', // ...
];
$adminRole->givePermissionTo($adminPermissions);
```

## Test Environment Configuration

**Database Connection:**
- Test database: `city_tour_test` (MySQL)
- Connection: `mysql_testing` (defined in phpunit.xml)
- Automatically refreshed per test due to `RefreshDatabase` trait

**Environment Settings** (from phpunit.xml):
```xml
<env name="APP_ENV" value="testing"/>
<env name="CACHE_STORE" value="array"/>
<env name="DB_CONNECTION" value="mysql_testing"/>
<env name="DB_DATABASE" value="city_tour_test"/>
<env name="MAIL_MAILER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

## Testing Best Practices Observed

1. **Database Isolation:** RefreshDatabase trait ensures clean state
2. **Setup in setUp():** Complex prerequisite data created in setup, not in each test
3. **HTTP Mocking:** Http::fake() prevents external calls
4. **Error Handling:** Tests verify both success and error cases
5. **Factory Usage:** Models created with factories for consistency
6. **Permission Testing:** Comprehensive role and permission setup

## Test Documentation

- `tests/Feature/QUICK_START.md` - Quick reference for writing tests
- `tests/Feature/TEST_SUITE_DOCUMENTATION.md` - Comprehensive test suite guide

---

*Testing analysis: 2026-02-12*
