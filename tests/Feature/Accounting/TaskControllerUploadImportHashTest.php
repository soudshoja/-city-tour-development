<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\TaskController;
use App\Models\Company;
use App\Models\FileUpload;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * W6.I residual fix round -- a fake UploadedFile whose `getRealPath()` returns a path that was
 * never written to disk, simulating a `file_get_contents()` read failure (e.g. a transient
 * disk/permission hiccup) at the exact moment `TaskController::upload()` tries to hash the
 * file's content. `move()` is untouched (it resolves its own underlying path via
 * `getPathname()`, inherited from `SplFileInfo`'s real constructor argument, never via
 * `getRealPath()`), so the rest of the upload flow behaves exactly as it would for any other
 * file whose content simply could not be hashed.
 */
class UnreadablePathUploadedFile extends UploadedFile
{
    public function getRealPath(): string|false
    {
        return sys_get_temp_dir() . '/w6i-ghost-' . uniqid() . '.pdf';
    }
}

/**
 * W6.I fix round -- end-to-end HTTP-shaped coverage for the exact defect confirmed by the
 * previous verify pass: `file_uploads.import_hash` did not actually replace the filename-only
 * dedupe at BOTH of `TaskController::upload()`'s call sites.
 *
 * (a) The merge-supplier ("batches") branch never read or wrote `import_hash`/`source_hashes`
 *     at all -- it matched purely on `file_name`/`source_files`.
 * (b) The single-file branch's pre-existing filename-only check was left completely unchanged
 *     directly below the newly-added hash check, so it independently re-rejected (or failed to
 *     reject) uploads based on filename alone regardless of content.
 *
 * These tests call `TaskController::upload()` directly (same pattern as the sibling
 * `TaskImportKeyDedupeTest` for `store()`), with `actingAs()` since `upload()` reads
 * `Auth::user()` directly (unlike `store()`).
 */
class TaskControllerUploadImportHashTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $storageDirsToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->storageDirsToClean as $dir) {
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function makeCompanyUser(string $companyName): array
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id, 'name' => $companyName]);
        $this->actingAs($user);

        return [$user, $company];
    }

    private function trackStorageDir(Company $company, Supplier $supplier): void
    {
        $companyDirName = strtolower(preg_replace('/\s+/', '_', $company->name));
        $supplierDirName = strtolower(preg_replace('/\s+/', '_', $supplier->name));
        $this->storageDirsToClean[] = storage_path("app/{$companyDirName}/{$supplierDirName}");
    }

    // -----------------------------------------------------------------
    // (b) single-file branch
    // -----------------------------------------------------------------

    public function test_single_file_branch_catches_same_content_under_a_renamed_file(): void
    {
        [$user, $company] = $this->makeCompanyUser('W6IFixCoOne');
        $supplier = Supplier::factory()->create(['name' => 'W6IFixSupplierSingleOne']);
        $this->trackStorageDir($company, $supplier);

        $content = "PNR-HASH-TEST\nOriginal ticket bytes\n";

        FileUpload::create([
            'file_name' => 'original.pdf',
            'destination_path' => 'irrelevant/path',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'import_hash' => FileUpload::hashContent($content),
        ]);

        $file = UploadedFile::fake()->createWithContent('renamed.pdf', $content);

        $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['task_file' => [$file]]);

        $payload = app(TaskController::class)->upload($request);

        // `upload()` returns a plain PHP array (a list of result entries), not a Response
        // object -- there is no `response()->json()` wrapper anywhere in this method.
        $this->assertSame('error', $payload[0]['status']);
        $this->assertStringContainsString("already imported", $payload[0]['data'][0]['message'] ?? '');
        $this->assertFalse(
            FileUpload::where(['company_id' => $company->id, 'file_name' => 'renamed.pdf'])->exists(),
            'A same-content file under a renamed filename must be caught as a duplicate and must never create a new FileUpload row.'
        );
    }

    /**
     * W6.I residual fix round -- the two verify-2 findings, both exercised on the single-file
     * branch in one test:
     *
     * (1) a `file_get_contents()` read failure (here: `getRealPath()` pointing at a path that
     *     was never written) must yield a NULL hash and a logged warning, never a hash of the
     *     empty string -- `FileUpload::hashFile()` absorbs the failure, `upload()` logs
     *     'task_import.file_hash_failed', and the code falls through to the pre-existing
     *     filename-based fallback check.
     * (2) that fallback catching a genuine filename collision proves the fallback branch
     *     actually ran (not merely that a new row got created with a null hash).
     */
    public function test_single_file_branch_logs_a_warning_and_falls_back_to_filename_on_a_read_failure(): void
    {
        [$user, $company] = $this->makeCompanyUser('W6IFixCoGhost');
        $supplier = Supplier::factory()->create(['name' => 'W6IFixSupplierGhost']);
        $this->trackStorageDir($company, $supplier);

        // Pre-existing row with the SAME file_name/supplier/company but no import_hash (as a
        // pre-W6.I row legitimately could be) -- only the filename fallback can catch this.
        FileUpload::create([
            'file_name' => 'ghost.pdf',
            'destination_path' => 'irrelevant/path',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'import_hash' => null,
        ]);

        $realTmpPath = tempnam(sys_get_temp_dir(), 'w6i_real_') . '.pdf';
        file_put_contents($realTmpPath, 'bytes that exist on disk at the real path');

        // Constructed directly (not via UploadedFile::fake()) so `getPathname()` -- what
        // `move()` actually uses -- points at a real, readable file, while our overridden
        // `getRealPath()` -- what `upload()`'s hash step actually uses -- does not.
        $file = new UnreadablePathUploadedFile($realTmpPath, 'ghost.pdf', 'application/pdf', null, true);

        try {
            Log::spy();

            $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['task_file' => [$file]]);
            $payload = app(TaskController::class)->upload($request);

            Log::shouldHaveReceived('warning')
                ->withArgs(fn($message, $context = []) => $message === 'task_import.file_hash_failed'
                    && ($context['file_name'] ?? null) === 'ghost.pdf')
                ->atLeast()->once();

            $this->assertSame(
                'error',
                $payload[0]['status'],
                'A read failure must fall through to the filename fallback, which must still catch a real filename collision.'
            );
            $this->assertSame(
                1,
                FileUpload::where(['company_id' => $company->id, 'file_name' => 'ghost.pdf'])->count(),
                'The fallback must reject the upload as a duplicate -- no second row for the same filename.'
            );
        } finally {
            @unlink($realTmpPath);
        }
    }

    /**
     * W6.I residual fix round finding (2): the single-file branch's duplicate lookup must
     * check BOTH `import_hash` and `source_hashes`, exactly like the merge branch's own lookup
     * does, so bytes that were previously ingested only as one *source file* inside a merge
     * batch (never as any row's own `import_hash`) are still caught here.
     */
    public function test_single_file_branch_catches_content_that_exists_only_in_a_merge_rows_source_hashes(): void
    {
        [$user, $company] = $this->makeCompanyUser('W6IFixCoCross');
        $supplier = Supplier::factory()->create(['name' => 'W6IFixSupplierCross']);
        $this->trackStorageDir($company, $supplier);

        $content = "shared bytes ingested only inside a prior merge batch\n";

        // Simulates a prior merge-supplier batch row: its OWN import_hash is the merged
        // output's hash (never this content's hash), but this content's hash is recorded in
        // source_hashes -- the only place it appears anywhere in file_uploads.
        FileUpload::create([
            'file_name' => 'TBOAir-batch.pdf',
            'destination_path' => 'irrelevant/path',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'source_files' => ['unrelated.pdf', 'the-one-we-want.pdf'],
            'import_hash' => FileUpload::hashContent('the merged batch output bytes, never this content'),
            'source_hashes' => [
                FileUpload::hashContent('some other unrelated source file bytes'),
                FileUpload::hashContent($content),
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('re-uploaded.pdf', $content);

        $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['task_file' => [$file]]);

        $payload = app(TaskController::class)->upload($request);

        $this->assertSame(
            'error',
            $payload[0]['status'],
            'Content that exists only in a prior merge row\'s source_hashes must be caught as a duplicate by the single-file path -- dedupe no-op.'
        );
        $this->assertFalse(
            FileUpload::where(['company_id' => $company->id, 'file_name' => 're-uploaded.pdf'])->exists(),
            'The single-file path must not create a new row for content already recorded in another row\'s source_hashes.'
        );
    }

    public function test_single_file_branch_allows_same_filename_with_different_content(): void
    {
        // This is the exact regression named in the verify finding for item (b): the leftover
        // filename-only check must no longer fire once the file has already been proven
        // content-distinct by the hash check above it.
        [$user, $company] = $this->makeCompanyUser('W6IFixCoTwo');
        $supplier = Supplier::factory()->create(['name' => 'W6IFixSupplierSingleTwo']);
        $this->trackStorageDir($company, $supplier);

        FileUpload::create([
            'file_name' => 'ticket.pdf',
            'destination_path' => 'irrelevant/path',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'import_hash' => FileUpload::hashContent('old content entirely'),
        ]);

        $file = UploadedFile::fake()->createWithContent('ticket.pdf', 'brand new, genuinely different content');

        $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['task_file' => [$file]]);

        $payload = app(TaskController::class)->upload($request);

        $this->assertSame(
            'success',
            $payload[0]['status'],
            'A same-named file with genuinely different content must no longer be rejected as a duplicate.'
        );
        $this->assertSame(
            2,
            FileUpload::where(['company_id' => $company->id, 'file_name' => 'ticket.pdf'])->count(),
            'Both the pre-existing row and the new upload must exist -- the new one must not have been silently dropped as a dup.'
        );

        $created = FileUpload::where(['company_id' => $company->id, 'file_name' => 'ticket.pdf'])
            ->where('import_hash', FileUpload::hashContent('brand new, genuinely different content'))
            ->first();
        $this->assertNotNull($created, 'The new upload must have its own import_hash persisted.');
    }

    // -----------------------------------------------------------------
    // (a) merge-supplier ("batches") branch
    // -----------------------------------------------------------------

    public function test_merge_branch_catches_same_content_delivered_under_a_renamed_file_in_a_new_batch(): void
    {
        [$user, $company] = $this->makeCompanyUser('W6IFixCoThree');
        $supplier = Supplier::factory()->create(['name' => 'TBO Air']);
        $this->trackStorageDir($company, $supplier);

        $content = "TBO-SEGMENT-DATA\nSame bytes, different filename later\n";

        // Simulates a previous batch upload whose merged FileUpload row bundled this exact
        // source file's content (among others) under a name the new upload does not reuse.
        FileUpload::create([
            'file_name' => 'TBOAir-prevbatch.pdf',
            'destination_path' => 'irrelevant/path',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'source_files' => ['first-old-name.pdf', 'segment-old-name.pdf'],
            'import_hash' => FileUpload::hashContent('the merged bytes of the previous batch'),
            'source_hashes' => [
                FileUpload::hashContent('unrelated first file bytes'),
                FileUpload::hashContent($content),
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('segment-renamed.pdf', $content);

        $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['batches' => [[$file]]]);

        $payload = app(TaskController::class)->upload($request);

        $this->assertSame('error', $payload[0]['status']);
        $this->assertFalse(
            FileUpload::where('company_id', $company->id)
                ->whereJsonContains('source_files', 'segment-renamed.pdf')
                ->exists(),
            'A source file whose content already exists under a different batch/name must be caught, never merged into a new FileUpload row.'
        );
    }

    public function test_merge_branch_allows_same_filename_with_different_content(): void
    {
        // This reaches the ACTUAL `iio\libmergepdf\Merger::addFile()` call (unlike the
        // duplicate-caught test above, which short-circuits before ever invoking the merger),
        // so the new upload's content must be a real, parseable PDF -- a real fixture already
        // vendored in this repo, not arbitrary bytes.
        [$user, $company] = $this->makeCompanyUser('W6IFixCoFour');
        $supplier = Supplier::factory()->create(['name' => 'TBO Air']);
        $this->trackStorageDir($company, $supplier);

        FileUpload::create([
            'file_name' => 'samename.pdf',
            'destination_path' => 'irrelevant/path',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
            'source_files' => ['samename.pdf'],
            'import_hash' => FileUpload::hashContent('the original batch content -- never actually stored on disk'),
            'source_hashes' => [FileUpload::hashContent('the original batch content -- never actually stored on disk')],
        ]);

        $newContent = file_get_contents(base_path('vendor/tecnickcom/tcpdf/examples/example_012.pdf'));
        $file = UploadedFile::fake()->createWithContent('samename.pdf', $newContent);

        $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['batches' => [[$file]]]);

        $payload = app(TaskController::class)->upload($request);

        $this->assertSame(
            'success',
            $payload[0]['status'],
            'A same-named source file with genuinely different content must no longer be rejected in the merge-supplier branch.'
        );

        $created = FileUpload::where('company_id', $company->id)
            ->whereJsonContains('source_files', 'samename.pdf')
            ->where('id', '!=', FileUpload::where('file_name', 'samename.pdf')->orderBy('id')->first()->id)
            ->first();

        $this->assertNotNull($created, 'The new merged-batch row must be created despite the filename collision.');
        $this->assertSame(FileUpload::hashContent($newContent), $created->import_hash);
        $this->assertSame([FileUpload::hashContent($newContent)], $created->source_hashes);
    }

    public function test_merge_branch_two_different_source_files_in_one_batch_are_both_recorded(): void
    {
        // Sanity check that the restructured hash bookkeeping still produces a correct
        // `source_hashes` array (order-independent) for a genuine multi-file merge, not just
        // the single-file-in-batch shortcut path.
        [$user, $company] = $this->makeCompanyUser('W6IFixCoFive');
        $supplier = Supplier::factory()->create(['name' => 'TBO Air']);
        $this->trackStorageDir($company, $supplier);

        // Both files must be real, parseable PDFs -- this test reaches the actual
        // `iio\libmergepdf\Merger::addFile()`/`merge()` calls for a genuine 2-file batch.
        $contentA = file_get_contents(base_path('docs/GDS Accoutning Files/ADm sample/adm_2001763796.pdf'));
        $contentB = file_get_contents(base_path('docs/GDS Accoutning Files/ADm sample/adm_2001763797.pdf'));

        $fileA = UploadedFile::fake()->createWithContent('part-a.pdf', $contentA);
        $fileB = UploadedFile::fake()->createWithContent('part-b.pdf', $contentB);

        $request = new Request(['supplier_id' => $supplier->id], [], [], [], ['batches' => [[$fileA, $fileB]]]);

        $payload = app(TaskController::class)->upload($request);

        $this->assertSame('success', $payload[0]['status']);

        $created = FileUpload::where('company_id', $company->id)
            ->whereJsonContains('source_files', 'part-a.pdf')
            ->first();

        $this->assertNotNull($created);
        $this->assertCount(2, $created->source_hashes);
        $this->assertContains(FileUpload::hashContent($contentA), $created->source_hashes);
        $this->assertContains(FileUpload::hashContent($contentB), $created->source_hashes);
    }
}
