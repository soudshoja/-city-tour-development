<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Modules\DotwAI\Services\PhoneResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Unit tests for PhoneResolverService.
 *
 * Verifies the full phone-to-context resolution chain:
 * phone -> agent -> company -> credentials -> track determination.
 *
 * @covers \App\Modules\DotwAI\Services\PhoneResolverService
 * @see FOUND-03
 */
class PhoneResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private PhoneResolverService $resolver;
    private Company $company;
    private Branch $branch;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PhoneResolverService();

        // Create test data chain: Company -> Branch -> Agent -> Credential
        $this->company = Company::factory()->create(['name' => 'Test Agency']);
        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Branch',
        ]);
        $this->agent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'phone_number' => '99800027',
            'country_code' => '+965',
        ]);

        CompanyDotwCredential::create([
            'company_id' => $this->company->id,
            'dotw_username' => Crypt::encrypt('testuser'),
            'dotw_password' => Crypt::encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent' => 0,
            'is_active' => true,
            'b2b_enabled' => true,
            'b2c_enabled' => false,
        ]);
    }

    public function test_resolves_phone_with_country_prefix(): void
    {
        $context = $this->resolver->resolve('+96599800027');

        $this->assertNotNull($context);
        $this->assertEquals($this->company->id, $context->companyId);
        $this->assertEquals('b2b', $context->track);
        $this->assertEquals($this->agent->id, $context->agent->id);
    }

    public function test_resolves_phone_without_prefix(): void
    {
        $context = $this->resolver->resolve('99800027');

        $this->assertNotNull($context);
        $this->assertEquals($this->company->id, $context->companyId);
        $this->assertEquals('b2b', $context->track);
    }

    public function test_returns_null_for_unknown_phone(): void
    {
        $context = $this->resolver->resolve('0000000');

        $this->assertNull($context);
    }

    public function test_returns_null_when_agent_has_no_branch(): void
    {
        // Create agent without a valid branch (branch_id points to non-existent branch)
        $orphanAgent = Agent::factory()->create([
            'branch_id' => 99999,
            'phone_number' => '55512345',
            'country_code' => '+965',
        ]);

        $context = $this->resolver->resolve('55512345');

        $this->assertNull($context);
    }

    public function test_returns_null_when_credentials_inactive(): void
    {
        // Deactivate the credential
        CompanyDotwCredential::where('company_id', $this->company->id)
            ->update(['is_active' => false]);

        $context = $this->resolver->resolve('99800027');

        $this->assertNull($context);
    }

    public function test_resolves_b2c_track_when_markup_positive(): void
    {
        // Update credential to have a markup (B2C indicator)
        CompanyDotwCredential::where('company_id', $this->company->id)
            ->update(['markup_percent' => 20]);

        $context = $this->resolver->resolve('99800027');

        $this->assertNotNull($context);
        $this->assertEquals('b2c', $context->track);
        $this->assertEquals(20.0, $context->markupPercent);
        $this->assertEquals(1.20, $context->getMarkupMultiplier());
    }
}
