<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\BankPayment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceReceipt;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Accounting\ChequeImageStore;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\AccountingTestCase;

/**
 * Security fix (automated review finding): ReceiptVoucherController::storeChequeImage() and
 * BankPaymentController::storeChequeImageFile() used to trust `getClientOriginalExtension()` for
 * the stored filename and write to the PUBLIC disk -- an unrestricted upload with a
 * client-controlled extension under the public webroot (potential RCE if the web server executes
 * it, plus unauthenticated access to cheque images regardless of company). Both now delegate to
 * the shared {@see ChequeImageStore}. This suite covers:
 *
 *  1. A real image uploaded under a `.php` name is stored under a server-derived `.png`/`.jpg`
 *     extension on the PRIVATE disk, and is not reachable under the public `/storage` path.
 *  2. Real PHP source content is rejected (422), regardless of the extension/Content-Type claimed.
 *  3. The new authenticated download route enforces a company-tenant check: 403 for another
 *     company, 200 with the correct MIME type for the owner.
 *  4. A static, no-DB guard that neither controller regressed back to
 *     `getClientOriginalExtension()` / `storeAs(..., 'public')`.
 */
class ChequeImageUploadSecurityTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * A raw (non-`Illuminate\Http\Testing\File`) UploadedFile whose `getMimeType()` really sniffs
     * the temp file's bytes (Symfony's `File::getMimeType()`), unlike the fake-upload helper class
     * whose `getMimeType()` is overridden to guess purely from the given filename. This is the only
     * way to exercise the production content-sniffing path in a test.
     */
    private function realUploadedFileWithContent(string $originalName, string $content): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'chq');
        file_put_contents($tmpPath, $content);

        return new UploadedFile($tmpPath, $originalName, null, null, true);
    }

    private function realPngBytes(): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(20, 20));

        return ob_get_clean();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 1. Malicious extension, real image content -> stored safely under the sniffed extension
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Exercises {@see ChequeImageStore} directly with the exact scenario named in the review
     * ("a file named x.php with image/png content"). This is deliberately a SERVICE-level test,
     * not an HTTP round-trip: Laravel's own `mimes` validation rule independently refuses ANY
     * upload whose CLIENT-claimed extension is `php`/`php3`-`php8`/`phtml`/`phar`
     * ({@see \Illuminate\Validation\Concerns\ValidatesAttributes::shouldBlockPhpUpload()}) before
     * the request even reaches the controller -- a separate, welcome layer of defense, but one that
     * would make a `.php`-named file never reach {@see ChequeImageStore} at all through the real
     * route, so it cannot demonstrate THIS class's own content-sniffing behaviour end-to-end. The
     * two HTTP-level tests directly below prove the same "never trust the client's extension" fix
     * through the real route using a dangerous extension Laravel's upload guard does not special-
     * case (`.exe`), so both defenses are covered without one masking the other.
     */
    public function test_chequeimagestore_derives_the_extension_from_content_for_a_php_named_file_with_real_png_bytes(): void
    {
        Storage::fake('local');
        $file = $this->realUploadedFileWithContent('x.php', $this->realPngBytes());

        $path = app(ChequeImageStore::class)->storeUploadedFile($file, 1);

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path, 'Extension must be derived from the sniffed MIME type, never the client filename.');
        $this->assertStringNotContainsString('.php', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->get('/storage/'.$path)->assertNotFound();
    }

    public function test_rv_store_with_an_exe_named_file_containing_real_png_bytes_stores_as_png_and_is_not_public(): void
    {
        Storage::fake('local');
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');
        $maliciousName = $this->realUploadedFileWithContent('evil.exe', $this->realPngBytes());

        $resp = $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'cheque_image' => $maliciousName,
            'remarks_create' => 'Malicious extension, real image content',
        ]);
        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($invoiceReceipt->cheque_image_path);

        $path = $invoiceReceipt->cheque_image_path;
        $this->assertStringEndsWith('.png', $path, 'Extension must be derived from the sniffed MIME type, never the client filename.');
        $this->assertStringNotContainsString('.exe', $path);
        Storage::disk('local')->assertExists($path);

        // Never reachable under the conventional public storage-symlink path.
        Storage::disk('public')->assertMissing($path);
        $this->get('/storage/'.$path)->assertNotFound();
    }

    public function test_pv_batch_item_with_an_exe_named_file_containing_real_png_bytes_stores_as_png(): void
    {
        Storage::fake('local');
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $bank = $this->accountByCode($company->id, '1201');
        $target = $this->accountByCode($company->id, '5222');
        Setting::create(['company_id' => $company->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);
        $maliciousName = $this->realUploadedFileWithContent('shell.exe', $this->realPngBytes());

        $this->actingAs($admin)->post(route('bank-payments.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-EVIL', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'Malicious extension, real image content',
            'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 10, 'cheque_image' => $maliciousName]],
        ]);

        $bankPayment = BankPayment::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($bankPayment->cheque_image_path);
        $this->assertStringEndsWith('.png', $bankPayment->cheque_image_path);
        Storage::disk('public')->assertMissing($bankPayment->cheque_image_path);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 2. Real PHP source content is rejected, regardless of claimed extension/Content-Type
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_chequeimagestore_rejects_real_php_source_content_even_with_an_allowed_looking_name(): void
    {
        $file = $this->realUploadedFileWithContent('cheque.jpg', "<?php echo 'not an image'; ?>");

        $this->expectException(ValidationException::class);

        app(ChequeImageStore::class)->storeUploadedFile($file, 1);
    }

    public function test_rv_store_end_to_end_rejects_a_file_whose_real_content_is_php(): void
    {
        Storage::fake('local');
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');
        $phpFile = $this->realUploadedFileWithContent('cheque.jpg', "<?php system(\$_GET['c']); ?>");

        $response = $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'cheque_image' => $phpFile,
            'remarks_create' => 'Real PHP content disguised as jpg',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('cheque_image');
        $this->assertSame(0, InvoiceReceipt::where('company_id', $company->id)->count(), 'No row should be created for a rejected upload.');
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 3. Download route: authenticated + tenant-checked
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_rv_cheque_image_download_is_200_with_correct_mime_for_the_owner_and_403_for_another_company(): void
    {
        Storage::fake('local');
        [$companyA, $branchA, , , $adminA] = $this->makeFixture();
        $this->enableEngine($companyA);

        $account = $this->accountByCode($companyA->id, '2110');
        $file = UploadedFile::fake()->image('cheque.jpg', 200, 100)->size(50);

        $this->actingAs($adminA)->post(route('receipt-voucher.store'), [
            'company_id' => $companyA->id,
            'branch_id' => $branchA->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'cheque_image' => $file,
            'remarks_create' => 'Owner download test',
        ])->assertRedirect();

        $invoiceReceipt = InvoiceReceipt::where('company_id', $companyA->id)->latest('id')->first();

        // Owner (same session company) -> 200, correct mime.
        $this->actingAs($adminA)->get(route('receipt-voucher.cheque-image', $invoiceReceipt->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // A Role::COMPANY user belonging to a DIFFERENT company -> 403. This specifically exercises
        // the controller's own explicit tenant check: ReceiptVoucherPolicy::view() alone would
        // allow this (it falls through to the role-only viewAny() for any non-agent role).
        $userB = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $userB->id]);
        $this->trackCompanyForInvariants($companyB->id);

        $this->actingAs($userB)->get(route('receipt-voucher.cheque-image', $invoiceReceipt->id))
            ->assertForbidden();
    }

    public function test_pv_cheque_image_download_is_200_for_the_owner_and_403_for_another_company(): void
    {
        Storage::fake('local');
        [$companyA, $branchA, , , $adminA] = $this->makeFixture();
        $this->enableEngine($companyA);

        $bank = $this->accountByCode($companyA->id, '1201');
        $target = $this->accountByCode($companyA->id, '5222');
        Setting::create(['company_id' => $companyA->id, 'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'value' => '1000', 'type' => 'string']);
        $file = UploadedFile::fake()->create('cheque.pdf', 40, 'application/pdf');

        $this->actingAs($adminA)->post(route('bank-payments.store'), [
            'company_id' => $companyA->id, 'branch_id' => $branchA->id, 'docdate' => now()->toDateString(),
            'bankpaymentref' => 'REF-DL', 'bankpaymenttype' => 'Payment', 'pay_from_account' => $bank->id,
            'remarks_create' => 'Owner download test',
            'items' => [['type_selector' => 'account', 'account_id' => $target->id, 'credit' => 15, 'cheque_image' => $file]],
        ]);

        $bankPayment = BankPayment::where('company_id', $companyA->id)->latest('id')->first();
        $this->assertNotNull($bankPayment->cheque_image_path);

        $this->actingAs($adminA)->get(route('bank-payments.cheque-image', $bankPayment->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $userB = User::factory()->create(['role_id' => Role::COMPANY]);
        $companyB = Company::factory()->create(['user_id' => $userB->id]);
        $this->trackCompanyForInvariants($companyB->id);

        $this->actingAs($userB)->get(route('bank-payments.cheque-image', $bankPayment->id))
            ->assertForbidden();
    }

    public function test_cheque_image_download_is_404_when_no_image_was_ever_uploaded(): void
    {
        [$company, $branch, , , $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $account = $this->accountByCode($company->id, '2110');
        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id, 'branch_id' => $branch->id, 'docdate' => now()->toDateString(),
            'type' => 'account', 'account_id' => $account->id, 'amount' => 50, 'remarks_create' => 'No cheque image',
        ]);

        $invoiceReceipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();

        $this->actingAs($admin)->get(route('receipt-voucher.cheque-image', $invoiceReceipt->id))
            ->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // 4. Static regression guard -- neither controller may reintroduce the vulnerable pattern
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Strips comments/docblocks via `token_get_all()` before matching, so the assertion checks
     * actual CODE, never a docblock that (legitimately, elsewhere in this same file) narrates the
     * historical vulnerability by name -- a plain string/regex search over the raw file would
     * false-positive on those very docblocks.
     */
    private function codeOnlySource(string $path): string
    {
        $code = '';

        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    public function test_neither_controller_source_contains_the_vulnerable_upload_pattern(): void
    {
        $rvSource = $this->codeOnlySource(app_path('Http/Controllers/ReceiptVoucherController.php'));
        $pvSource = $this->codeOnlySource(app_path('Http/Controllers/BankPaymentController.php'));

        foreach (['ReceiptVoucherController' => $rvSource, 'BankPaymentController' => $pvSource] as $name => $source) {
            $this->assertDoesNotMatchRegularExpression(
                '/getClientOriginalExtension\s*\(/',
                $source,
                "{$name} must never derive a stored filename's extension from client-controlled input."
            );
            $this->assertDoesNotMatchRegularExpression(
                "/storeAs\\([^;]*'public'\\)/s",
                $source,
                "{$name} must never write an uploaded cheque image to the public disk."
            );
        }
    }
}
