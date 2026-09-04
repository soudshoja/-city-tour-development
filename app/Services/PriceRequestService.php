<?php

namespace App\Services;

use App\Models\PriceRequest;
use App\Models\Task;
use App\Models\Agent;
use App\Models\AgentNotificationSetting;
use App\Http\Controllers\ResayilController;
use Illuminate\Support\Facades\Log;

class PriceRequestService
{
    /** Enqueue a price ask when a no-fare email ticket loads at 0. Idempotent. */
    public function enqueueForTask(Task $task): void
    {
        try {
            if (!in_array($task->status, ['issued', 'reissued'], true)) {
                return;
            }
            if ((float) $task->price != 0.0) {
                return; // already priced
            }
            // Turkish NDC: auto-fill the cost from a captured "Shared Content"
            // fare email before asking the agent on WhatsApp (works even when the
            // booking was broadcast and has no attributed agent).
            try {
                if (app(\App\Services\TurkishFareResolver::class)->applyStashedTo($task)) {
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('[PriceRequest] turkish fare resolve failed: ' . $e->getMessage());
            }
            if (empty($task->agent_id)) {
                return; // no one to ask
            }
            if (empty($task->file_name)) {
                return; // email/AIR-sourced only (not manual UI entry)
            }
            if (PriceRequest::where('task_id', $task->id)->whereIn('status', ['pending', 'asked'])->exists()) {
                return; // one open request per task
            }
            $agent = $task->agent;
            if (!$agent || empty($agent->phone_number)) {
                return;
            }
            PriceRequest::create([
                'task_id'      => $task->id,
                'company_id'   => $task->company_id,
                'agent_id'     => $task->agent_id,
                'pnr'          => $task->gds_reference ?: $task->reference,
                'phone'        => $agent->phone_number,
                'country_code' => $agent->country_code,
                'status'       => PriceRequest::STATUS_PENDING,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PriceRequest] enqueue failed', ['task_id' => $task->id, 'err' => $e->getMessage()]);
        }
    }

    /** Send the next pending ask per agent — at most ONE outstanding ask per agent. */
    public function sendNext(): int
    {
        $sent = 0;
        $agentIds = PriceRequest::where('status', PriceRequest::STATUS_PENDING)->distinct()->pluck('agent_id');
        foreach ($agentIds as $aid) {
            if (PriceRequest::where('agent_id', $aid)->where('status', PriceRequest::STATUS_ASKED)->exists()) {
                continue; // wait for the current ask to be answered
            }
            $req = PriceRequest::where('agent_id', $aid)->where('status', PriceRequest::STATUS_PENDING)->orderBy('id')->first();
            if (!$req) {
                continue;
            }
            $task = $req->task;
            if (!$task || (float) $task->price != 0.0) {
                $req->update(['status' => PriceRequest::STATUS_CANCELLED]);
                continue;
            }
            if (!$this->agentOptedIn($req)) {
                $req->update(['status' => PriceRequest::STATUS_CANCELLED]);
                continue;
            }
            $pax = $task->passenger_name ? ' (' . $task->passenger_name . ')' : '';
            $msg = "Booking {$req->pnr}{$pax} loaded with no price.\nReply with the COST price in KWD — e.g. 125.5";
            (new ResayilController())->message($req->phone, $req->country_code, $msg, null, null, null, $this->dummy());
            $req->update(['status' => PriceRequest::STATUS_ASKED, 'asked_at' => now()]);
            $sent++;
        }
        return $sent;
    }

    /** Apply an agent's numeric reply to their single open ask. Returns result or null. */
    public function applyReply(Agent $agent, string $body): ?array
    {
        $req = PriceRequest::where('agent_id', $agent->id)->where('status', PriceRequest::STATUS_ASKED)->orderBy('id')->first();
        if (!$req) {
            return null; // not answering a price ask
        }
        if (!preg_match('/-?\d+(?:\.\d+)?/', $body, $m)) {
            return ['ok' => false, 'pnr' => $req->pnr]; // non-numeric -> caller reprompts
        }
        // A phone number is not a price ("+96557771332" once overflowed
        // tasks.price and 500'd the webhook): +/00 prefix or >6 integer digits
        // means this message isn't answering the ask — leave it open and let
        // the normal pipeline (e.g. client-phone flow) handle the message.
        $intDigits = strlen(preg_replace('/\D/', '', explode('.', $m[0])[0]));
        if (preg_match('/^\s*(?:\+|00)\d/', $body) || $intDigits > 6) {
            return null;
        }
        $amount = round((float) $m[0], 3);
        if ($amount < 0) {
            return ['ok' => false, 'pnr' => $req->pnr]; // caller reprompts
        }
        $task = $req->task;
        if ($task) {
            $task->price = $amount;
            $task->total = $amount; // placeholder until the sell-price flow exists
            $task->save();
        }
        $req->update(['status' => PriceRequest::STATUS_ANSWERED, 'answered_at' => now(), 'amount' => $amount]);
        return ['ok' => true, 'pnr' => $req->pnr, 'amount' => $amount];
    }

    /** Cancel asks whose task got priced; remind once after 4h; expire after 8h. */
    public function sweepReminders(): void
    {
        foreach (PriceRequest::whereIn('status', ['pending', 'asked'])->with('task')->get() as $r) {
            if (!$r->task || (float) $r->task->price != 0.0) {
                $r->update(['status' => PriceRequest::STATUS_CANCELLED]);
            }
        }
        foreach (PriceRequest::where('status', PriceRequest::STATUS_ASKED)->get() as $r) {
            if (!$r->asked_at) {
                continue;
            }
            $age = $r->asked_at->diffInHours(now());
            if ($age >= 8) {
                $r->update(['status' => PriceRequest::STATUS_EXPIRED]);
                continue;
            }
            if ($age >= 4 && !$r->reminded_at) {
                (new ResayilController())->message(
                    $r->phone,
                    $r->country_code,
                    "Reminder: booking {$r->pnr} still needs its cost price — reply with the amount, e.g. 125.5",
                    null,
                    null,
                    null,
                    $this->dummy()
                );
                $r->update(['reminded_at' => now()]);
            }
        }
    }

    /** Default ON unless the agent explicitly turned price_request off / to a non-WhatsApp channel. */
    private function agentOptedIn(PriceRequest $req): bool
    {
        $s = AgentNotificationSetting::getForAgent(
            $req->agent_id,
            $req->company_id,
            AgentNotificationSetting::TYPE_PRICE_REQUEST
        );
        if (!$s->exists) {
            return true; // no explicit row -> default on
        }
        return $s->is_active && in_array($s->channel, ['whatsapp', 'both'], true);
    }

    /** Resayil reroutes to a test number on non-prod unless this is false. Override via config. */
    private function dummy(): bool
    {
        return (bool) config('price_request.dummy', app()->environment() !== 'production');
    }
}
