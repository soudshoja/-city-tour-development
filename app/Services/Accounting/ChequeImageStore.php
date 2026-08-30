<?php

namespace App\Services\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared cheque-image upload/serving helper for {@see \App\Http\Controllers\ReceiptVoucherController}
 * (RV) and {@see \App\Http\Controllers\BankPaymentController} (PV) -- both let an accountant attach
 * a scanned cheque image to a voucher.
 *
 * Security fix: the two controllers used to each carry their own copy of this logic, which trusted
 * `$file->getClientOriginalExtension()` (attacker-controlled -- the client can name an upload
 * anything) for the stored filename's extension and wrote the result to the PUBLIC disk under a
 * predictable, unauthenticated path (`storage/uploads/cheques/...`, reachable via the `/storage`
 * symlink by anyone with the URL). That is an unrestricted upload with a client-controlled
 * extension written under the public webroot -- a potential RCE if the web server ever executes
 * the stored file, plus unauthenticated disclosure of every cheque image regardless of company.
 *
 * This class closes both holes:
 *
 *  - The stored extension is derived ONLY from the file's server-detected MIME type
 *    ({@see UploadedFile::getMimeType()} -- for a real upload this is Symfony's finfo/content-based
 *    guess, never the client-supplied filename or `Content-Type` header), checked against a closed
 *    whitelist ({@see self::ALLOWED_MIMES}). A file whose real content does not match one of the
 *    three allowed types is rejected with a 422 {@see ValidationException} regardless of what
 *    extension or Content-Type the client claimed -- e.g. a `.php` file whose real bytes are a PNG
 *    is stored as `.png`; a `.jpg`-named file whose real bytes are PHP source is rejected outright.
 *  - The file is written to the PRIVATE `local` disk (`storage/app/...`, never `storage/app/public`)
 *    under `cheques/{company_id}/{yyyy}/{mm}/{uuid}.{ext}` -- not reachable via the `/storage`
 *    symlink at all -- and is only ever read back through {@see self::streamResponse()}, which each
 *    controller's own authenticated + tenant-checked `chequeImage()` download action calls.
 */
class ChequeImageStore
{
    public const DISK = 'local';

    private const BASE_PATH = 'cheques';

    /** @var array<string, string> detected MIME type => stored file extension */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    /**
     * RV's shape: the file lives directly on the request under a known field name. Returns null
     * (no-op) when the field carries no file -- callers fall back to whatever path (if any) was
     * already stored, matching the pre-existing "don't erase a prior upload on an unrelated field
     * edit" behaviour.
     */
    public function storeFromRequest(Request $request, int $companyId, string $field = 'cheque_image'): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $this->store($request->file($field), $companyId);
    }

    /**
     * PV's shape: `store()`'s per-item batch validator has already pulled the resolved
     * `UploadedFile` (or null) off `items.{index}.cheque_image` before this is called.
     */
    public function storeUploadedFile(?UploadedFile $file, int $companyId): ?string
    {
        if ($file === null) {
            return null;
        }

        return $this->store($file, $companyId);
    }

    /**
     * Streams a previously-stored cheque image back with its real, stored MIME type and an
     * `inline` Content-Disposition ({@see \Illuminate\Filesystem\FilesystemAdapter::response()}'s
     * own default). Callers are each controller's own authenticated + tenant-checked download
     * action -- this is never exposed behind a bare, unauthenticated public URL.
     */
    public function streamResponse(string $path): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        if ($path === '' || ! $disk->exists($path)) {
            abort(404, 'Cheque image not found.');
        }

        return $disk->response($path);
    }

    /**
     * The one place a file actually gets written. Never trusts
     * `getClientOriginalExtension()`/`getClientMimeType()` (both attacker-controlled -- an upload
     * named `x.php` with a forged `Content-Type: image/png` header would satisfy either check) --
     * only `getMimeType()`, which for a real (non-test-faked) `UploadedFile` sniffs the file's
     * actual bytes via Symfony's finfo-backed guesser.
     */
    private function store(UploadedFile $file, int $companyId): string
    {
        $mime = $file->getMimeType();
        $extension = is_string($mime) ? (self::ALLOWED_MIMES[$mime] ?? null) : null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'cheque_image' => 'The cheque image must be a real JPG, PNG, or PDF file (detected type: '
                    .($mime ?? 'unknown').').',
            ]);
        }

        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = self::BASE_PATH.'/'.$companyId.'/'.now()->format('Y').'/'.now()->format('m').'/'.$fileName;

        $stream = fopen($file->getRealPath(), 'rb');

        try {
            Storage::disk(self::DISK)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }
}
