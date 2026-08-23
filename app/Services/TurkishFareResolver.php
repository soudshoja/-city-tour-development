<?php

namespace App\Services;

use App\Models\Task;
use App\Models\PriceRequest;
use App\Services\Parsers\TurkishNdcParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Turkish Airlines NDC fare backfill.
 *
 * Turkish sends THREE emails per PNR: "Reservation Information", "E-Ticket"
 * (itinerary — NO fare, this is what loads the tasks at price 0), and
 * "Shared Content Information" (the ONLY one with the price / Payment Details).
 * TurkishNdcParser only recognises the E-Ticket mail, so Turkish NDC tickets
 * always load at price 0.
 *
 * This resolver closes that gap. The two emails can be processed in either
 * order, so it works both ways:
 *   - Shared Content seen first  -> stash the fare by PNR (ingestSharedContent),
 *                                   and price any 0-price tasks that already exist.
 *   - Task created first         -> applyStashedTo() (called from
 *                                   PriceRequestService::enqueueForTask) prices it
 *                                   from the stash before falling back to the
 *                                   WhatsApp "ask the agent" flow.
 *
 * The stash lives in the `turkish_shared_fares` table (created idempotently at
 * deploy). Everything is idempotent — only 0-price tasks are ever touched.
 */
class TurkishFareResolver
{
    private const TABLE    = 'turkish_shared_fares';
    private const SUPPLIER = 'Turkish Airline NDC';

    /**
     * Ingest a "Shared Content Information" email: stash the fare and price any
     * already-loaded 0-price tasks for that PNR.
     * @return array{ok:bool, pnr?:string, per_pax?:float, applied?:int, reason?:string}
     */
    public function ingestSharedContent(string $html, ?string $messageId = null): array
    {
        $fare = TurkishNdcParser::extractSharedContentFare($html);
        if (!$fare) {
            return ['ok' => false, 'reason' => 'no_fare_parsed'];
        }

        if (Schema::hasTable(self::TABLE)) {
            try {
                DB::table(self::TABLE)->updateOrInsert(
                    ['pnr' => $fare['pnr']],
                    [
                        'total'      => $fare['total'],
                        'pax'        => $fare['pax'],
                        'per_pax'    => $fare['per_pax'],
                        'currency'   => $fare['currency'],
                        'tickets'    => json_encode($fare['tickets']),
                        'message_id' => $messageId,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[TurkishFare] stash failed: ' . $e->getMessage(), ['pnr' => $fare['pnr']]);
            }
        }

        $applied = $this->priceTasks($fare['pnr'], $fare['per_pax']);
        return ['ok' => true, 'pnr' => $fare['pnr'], 'per_pax' => $fare['per_pax'], 'applied' => $applied];
    }

    /**
     * On a freshly-loaded 0-price Turkish NDC task, apply a previously-stashed
     * fare if one exists. Returns true if the task was priced.
     */
    public function applyStashedTo(Task $task): bool
    {
        if ((float) $task->price != 0.0) {
            return false;
        }
        if (!$this->isTurkish($task)) {
            return false;
        }
        $pnr = $task->gds_reference ?: $task->reference;
        if (!$pnr || !Schema::hasTable(self::TABLE)) {
            return false;
        }
        $fare = DB::table(self::TABLE)->where('pnr', $pnr)->first();
        if (!$fare || (float) $fare->per_pax <= 0) {
            return false;
        }
        return $this->priceTasks($pnr, (float) $fare->per_pax, $task) > 0;
    }

    /**
     * Price every 0-price Turkish NDC task for a PNR (or just $only, if given).
     * Cancels any open WhatsApp price ask for the priced tasks. Idempotent.
     */
    private function priceTasks(string $pnr, float $perPax, ?Task $only = null): int
    {
        if ($perPax <= 0) {
            return 0;
        }
        $supplierId = DB::table('suppliers')->where('name', self::SUPPLIER)->value('id');

        $query = Task::query()->where('gds_reference', $pnr)->where('price', 0);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($only) {
            $query->where('id', $only->id);
        }

        $count = 0;
        foreach ($query->get() as $task) {
            $task->price = $perPax;
            $task->total = $perPax;   // placeholder until the sell-price flow exists (mirrors PriceRequestService)
            $task->save();
            $count++;

            try {
                PriceRequest::where('task_id', $task->id)
                    ->whereIn('status', [PriceRequest::STATUS_PENDING, PriceRequest::STATUS_ASKED])
                    ->update(['status' => PriceRequest::STATUS_CANCELLED]);
            } catch (\Throwable $e) {
                // price_requests may not exist on every env — non-fatal
            }
        }

        if ($count > 0) {
            Log::info("[TurkishFare] priced {$count} task(s) for {$pnr} at {$perPax} KWD from Shared Content fare");
        }
        return $count;
    }

    private function isTurkish(Task $task): bool
    {
        $supplierId = DB::table('suppliers')->where('name', self::SUPPLIER)->value('id');
        return !$supplierId || (int) $task->supplier_id === (int) $supplierId;
    }
}
