<?php

namespace App\Services\Vouchers;

use App\Http\Controllers\ResayilController;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskPackage;
use App\Models\TravelVoucher;
use App\Services\Vouchers\Exceptions\VoucherCompanyMismatchException;
use App\Support\Modules;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * WhatsApp send for an issued voucher (Step 4 item 4, plan section 10.2). A
 * sibling of PaymentReceiptService -- that class stays completely
 * untouched (it is not on the section 2 forbidden list, but the plan is
 * explicit the send flow lives in NEW files, section 2.1). Reuses exactly the
 * same cached-file-id -> ResayilController::document() pipeline as
 * PaymentReceiptService::getCachedFileOrUpload(): resolveFileId() below
 * checks the voucher's own cached `resayil_file_id` via getFileInfo() and
 * only uploads when it is missing/inactive, persisting the id THE MOMENT
 * a new upload succeeds -- deliberately not left to document()'s own
 * internal re-upload branch, which only reports a fresh file_id back on
 * overall success. Verified live 2026-08-27: without this, a send that
 * uploads fine but then fails for an unrelated reason (e.g. this dev
 * environment's empty PHONE_LOCAL) would silently drop the uploaded
 * file_id, so a retry re-uploads identical bytes and gets a 409 "file
 * already exists" from Resayil's own dedupe-by-hash. Caching immediately
 * makes every retry reuse the same file, matching the plan's own words:
 * "the cached-file-id -> ResayilController::document() pipeline."
 *
 * Gated on the company's module.resayil (plan section 10.2, section 14.17) --
 * issuing/downloading a voucher needs no module, only this send path does.
 *
 * BLOCKER B2 -- an ARB voucher never has a PDF to attach at all
 * (VoucherService::renderPdf() is a deliberate no-op for language ARB,
 * restoring plan section 12: "PDF attachment = EN templates only in v1"). For
 * one, send() below sends the public link as a plain WhatsApp text
 * message instead -- an intentional format switch, not the pdf_missing
 * failure path (that stays reserved for an EN voucher whose file really
 * is unexpectedly gone).
 */
class VoucherSendService
{
    public function send(TravelVoucher $voucher, int $companyId, ?int $senderId, ?string $phoneOverride = null, ?string $countryCodeOverride = null): array
    {
        if ((int) $voucher->company_id !== $companyId) {
            throw VoucherCompanyMismatchException::forSubject('travel_voucher', $voucher->id, $voucher->company_id, $companyId);
        }

        $company = Company::find($companyId);
        if (! $company || ! $company->hasModule(Modules::RESAYIL)) {
            return $this->failure('module_disabled', 'WhatsApp sending is not enabled for this company.');
        }

        // A cancelled/void_pending/superseded voucher must never go back
        // out over WhatsApp -- same dead-status list the public route
        // enforces (plan section 11.1, TravelVoucher::PUBLICLY_DEAD_STATUSES).
        if (! $voucher->isPubliclyAvailable()) {
            return $this->failure('voucher_unavailable', 'This voucher is no longer available and cannot be sent.');
        }

        $client = $this->resolveClient($voucher);
        $phone = $phoneOverride ?? $client?->phone;
        $countryCode = $countryCodeOverride ?? $client?->country_code;

        // Plan section 10.4: ~66% of tasks (59% of hotels) have no client_id.
        // The voucher itself always issues/links/downloads regardless; only
        // THIS button requires a client, and the caller (VoucherController)
        // is what surfaces the attach-client prompt on this specific error.
        if (! $client || ! $phone) {
            return $this->failure('no_client', 'No client phone number is attached to this booking yet.');
        }

        $caption = $this->caption($voucher, $companyId, $client);

        // BLOCKER B2 -- Arabic: text the working public link, never a PDF
        // (plan section 12, restored -- see this class's own docblock).
        if ($voucher->language === TravelVoucher::LANGUAGE_AR) {
            $response = (new ResayilController)->message(
                $phone,
                $countryCode ?? '+965',
                $caption,
                null,
                null,
                null,
                true // same non-production PHONE_LOCAL guard as the document() branch below
            );

            if (! ($response['success'] ?? false)) {
                Log::error('VoucherSendService: arabic link send failed', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'error' => $response['error'] ?? 'unknown',
                ]);

                return $this->failure('send_failed', $response['error'] ?? 'Failed to send the voucher via WhatsApp.');
            }

            return $this->markSent($voucher, $countryCode, $phone, $senderId);
        }

        if (! $voucher->pdf_path || ! Storage::disk('local')->exists($voucher->pdf_path)) {
            return $this->failure('pdf_missing', 'The voucher PDF could not be found. Try re-issuing the voucher.');
        }

        $pdfAbsolutePath = Storage::disk('local')->path($voucher->pdf_path);

        $resayil = new ResayilController;
        $fileId = $this->resolveFileId($resayil, $voucher, $pdfAbsolutePath);

        if (! $fileId) {
            return $this->failure('upload_failed', 'Could not prepare the voucher PDF for WhatsApp.');
        }

        $response = $resayil->document(
            phone: $phone,
            country_code: $countryCode ?? '+965',
            filePath: $pdfAbsolutePath,
            caption: $caption,
            isDummyNumber: true, // same non-production PHONE_LOCAL guard as PaymentReceiptService/document() itself
            fileId: $fileId
        );

        if (! ($response['success'] ?? false)) {
            Log::error('VoucherSendService: send failed', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error' => $response['error'] ?? 'unknown',
            ]);

            return $this->failure('send_failed', $response['error'] ?? 'Failed to send the voucher via WhatsApp.');
        }

        if (! empty($response['new_file_id'])) {
            $voucher->resayil_file_id = $response['new_file_id'];
        }

        return $this->markSent($voucher, $countryCode, $phone, $senderId);
    }

    /**
     * Shared "record the send" tail for both the Arabic text-link branch
     * and the EN PDF-document branch above -- persists sent_to_phone/
     * sent_at/sent_by (any resayil_file_id update the caller made to
     * $voucher is already staged on the instance, so this single save()
     * covers both) and returns the same success shape either way.
     */
    protected function markSent(TravelVoucher $voucher, ?string $countryCode, string $phone, ?int $senderId): array
    {
        $voucher->sent_to_phone = ($countryCode ?? '').$phone;
        $voucher->sent_at = now();
        $voucher->sent_by = $senderId;
        $voucher->save();

        return [
            'success' => true,
            'message' => "Voucher sent to {$voucher->sent_to_phone}",
            'sent_to_phone' => $voucher->sent_to_phone,
            'sent_at' => $voucher->sent_at->toIso8601String(),
        ];
    }

    /**
     * PaymentReceiptService::getCachedFileOrUpload()'s pattern, applied to
     * `travel_vouchers.resayil_file_id` instead of a separate cache table:
     * reuse the cached id if Resayil still reports it active, else upload
     * and persist the new id IMMEDIATELY (not deferred to send() succeeding
     * as a whole) so a later retry never re-uploads identical bytes.
     */
    protected function resolveFileId(ResayilController $resayil, TravelVoucher $voucher, string $absolutePath): ?string
    {
        if ($voucher->resayil_file_id) {
            $info = $resayil->getFileInfo($voucher->resayil_file_id);

            if (($info['success'] ?? false) && ($info['is_active'] ?? false)) {
                return $voucher->resayil_file_id;
            }
        }

        $upload = $resayil->uploadFile($absolutePath);

        if (! ($upload['success'] ?? false)) {
            Log::error('VoucherSendService: upload failed', [
                'voucher_id' => $voucher->id,
                'error' => $upload['error'] ?? 'unknown',
            ]);

            return null;
        }

        $voucher->forceFill(['resayil_file_id' => $upload['file_id']])->save();

        return $upload['file_id'];
    }

    protected function resolveClient(TravelVoucher $voucher)
    {
        $subject = $voucher->subject;

        if ($subject instanceof Task || $subject instanceof TaskPackage) {
            return $subject->client;
        }

        return null;
    }

    /**
     * `voucher.whatsapp_caption` settings key with {client}/{reference}/{link}
     * placeholders (plan section 10.2, section 14.15 -- default copy needs owner
     * sign-off, so a plain functional default ships until then). Same raw
     * settings-row read VoucherDataRepository::voucherSetting() uses.
     */
    protected function caption(TravelVoucher $voucher, int $companyId, $client): string
    {
        $custom = Setting::where('company_id', $companyId)->where('key', 'voucher.whatsapp_caption')->value('value');
        $reference = $voucher->subject->reference ?? $voucher->voucher_number;
        $link = route('travel-voucher.show', ['companyId' => $companyId, 'token' => $voucher->token]);

        if (! $custom) {
            return "Hello {$client->full_name}, here is your voucher {$voucher->voucher_number} ({$reference}). View it anytime: {$link}";
        }

        return strtr($custom, [
            '{client}' => $client->full_name,
            '{reference}' => $reference,
            '{link}' => $link,
        ]);
    }

    protected function failure(string $error, string $message): array
    {
        return ['success' => false, 'error' => $error, 'message' => $message];
    }
}
