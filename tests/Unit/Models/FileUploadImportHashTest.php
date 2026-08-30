<?php

namespace Tests\Unit\Models;

use App\Models\FileUpload;
use Tests\TestCase;

/**
 * W6.I "Importer contract" item 3 (w6-brief.md) -- `FileUpload::hashContent()`, replacing the
 * filename-only `(supplier_id, company_id, file_name)` dedupe with a real content hash.
 */
class FileUploadImportHashTest extends TestCase
{
    public function test_hash_is_deterministic_for_identical_content(): void
    {
        $a = FileUpload::hashContent('the exact same bytes');
        $b = FileUpload::hashContent('the exact same bytes');

        $this->assertSame($a, $b);
    }

    public function test_hash_differs_for_different_content(): void
    {
        $a = FileUpload::hashContent('content one');
        $b = FileUpload::hashContent('content two');

        $this->assertNotSame($a, $b);
    }

    public function test_hash_is_a_64_char_sha256_hex_digest(): void
    {
        $hash = FileUpload::hashContent('anything');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertSame(hash('sha256', 'anything'), $hash);
    }

    public function test_same_content_under_a_different_filename_hashes_identically(): void
    {
        // This is the exact gap the brief names: "a same-content re-upload under a renamed file
        // is now caught" -- the hash depends only on bytes, never on the filename.
        $contentBytes = "PNR-12345\nSome ticket data\n";

        $this->assertSame(
            FileUpload::hashContent($contentBytes),
            FileUpload::hashContent($contentBytes)
        );
    }

    /**
     * W6.I residual fix round -- the verify-2 finding: neither this file nor
     * TaskController.php declares strict_types=1, so a caller feeding an unguarded
     * file_get_contents() failure (bool(false)) straight into hashContent() would have it
     * silently coerced to '' by PHP's weak scalar typing, producing a valid-looking hash of
     * empty content instead of surfacing the read failure. Rejecting '' turns that into a
     * thrown exception every call site's try/catch already has to handle.
     */
    public function test_hash_content_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FileUpload::hashContent('');
    }

    /**
     * W6.I residual fix round -- hashFile() must not throw and must not hash a lie: a
     * nonexistent (or otherwise unreadable) path must yield null, never a hash of an empty
     * string standing in for the failed read.
     */
    public function test_hash_file_returns_null_for_a_nonexistent_path(): void
    {
        $path = sys_get_temp_dir() . '/w6i-hashfile-does-not-exist-' . uniqid() . '.pdf';
        $this->assertFileDoesNotExist($path);

        $this->assertNull(FileUpload::hashFile($path));
    }

    /**
     * W6.I residual fix round -- the successful-read counterpart: hashFile() on a real,
     * readable file must return exactly the same digest hashContent() would produce for that
     * file's bytes, so it is a safe drop-in replacement at every file_get_contents()
     * ->hashContent() call site.
     */
    public function test_hash_file_matches_hash_content_for_a_readable_file(): void
    {
        $path = sys_get_temp_dir() . '/w6i-hashfile-readable-' . uniqid() . '.txt';
        $bytes = "real bytes on disk for hashFile()\n";
        file_put_contents($path, $bytes);

        try {
            $this->assertSame(FileUpload::hashContent($bytes), FileUpload::hashFile($path));
        } finally {
            @unlink($path);
        }
    }

    /**
     * W6.I residual fix round -- a genuinely empty (zero-byte) file is a successful read of
     * empty content, which hashContent() now rejects; hashFile() must absorb that as null
     * rather than letting the InvalidArgumentException escape.
     */
    public function test_hash_file_returns_null_for_an_empty_file(): void
    {
        $path = sys_get_temp_dir() . '/w6i-hashfile-empty-' . uniqid() . '.txt';
        file_put_contents($path, '');

        try {
            $this->assertNull(FileUpload::hashFile($path));
        } finally {
            @unlink($path);
        }
    }
}
