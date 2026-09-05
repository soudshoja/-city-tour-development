<?php

declare(strict_types=1);

namespace App\Services\Accounting\FixedAssets;

use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * accounting-builds T2/T4 (Lane B, L7/L8): register CRUD, optional capitalisation posting, NBV
 * derivation, straight-line schedule preview, and disposal posting for {@see FixedAsset}.
 *
 * NBV (L8): {@see self::nbv()} is ALWAYS `cost − Σ posted depreciation credit lines on the
 * asset's own FA_ACCUM_DEP_{class} contra leaf, filtered by journal_entries.task_id = the
 * asset's id` — read fresh from `journal_entries` on every call, never a stored column. Every
 * document this service (and {@see DepreciationRunService}) posts for a given asset stamps every
 * line's `LineDraft::$taskId` with that asset's id specifically so this one filter can find them
 * all, on both the depreciation contra and (during disposal) the same contra's clearing line.
 *
 * Engine OFF (L2): every posting method here routes through {@see PostingSeam::post()} with a
 * `$legacy` closure that ONLY logs `accounting.feature_skipped_engine_off` and returns `null` —
 * there is no pre-existing legacy fixed-asset posting to fall back to, so the OFF path is a
 * logged no-op, never a raw write. Every caller must tolerate a `null` return.
 */
final class FixedAssetService
{
    public function __construct(
        private readonly PostingSeam $seam,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Create a draft asset register row. Throws \InvalidArgumentException on any validation
     * failure (MP-2-2: an unknown asset_class must throw here, not silently map to nothing).
     */
    public function create(array $data): FixedAsset
    {
        $this->validate($data);

        $data['status'] = $data['status'] ?? FixedAsset::STATUS_DRAFT;
        $data['method'] = $data['method'] ?? FixedAsset::METHOD_STRAIGHT_LINE;

        return FixedAsset::create($data);
    }

    public function update(FixedAsset $asset, array $data): FixedAsset
    {
        $merged = array_merge($asset->only(['asset_class', 'cost', 'salvage', 'useful_life_months']), $data);
        $this->validate($merged);

        $this->assertBasisNotFrozen($asset, $data);

        $asset->fill($data);
        $asset->save();

        return $asset->refresh();
    }

    /**
     * Post the (optional) acquisition document: Dr FA_COST_{class} for the asset's cost, Cr
     * either a caller-named purpose code (e.g. a payable) or an explicit bank/cash leaf (verified
     * under the bank group via {@see AccountResolver::assertUnderBankGroup()}). An asset whose
     * cost was already posted through an existing PV/JV before it was registered here should
     * simply never call this method — the register row alone (with `acquisition_transaction_id`
     * left null) is a complete, valid asset for depreciation/disposal purposes.
     *
     * Idempotent: `fa-acq:{id}`. A second call for an already-capitalised asset returns the
     * existing posted document without posting again.
     */
    public function capitalise(
        FixedAsset $asset,
        ?string $counterpartPurposeCode = null,
        ?int $counterpartAccountId = null,
        ?int $partyAccountRef = null,
        ?int $userId = null,
    ): ?PostedDocument {
        if ($asset->acquisition_transaction_id !== null) {
            $existing = $this->reconstructPosted((int) $asset->acquisition_transaction_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        if ($counterpartAccountId === null && ($counterpartPurposeCode === null || $counterpartPurposeCode === '')) {
            throw new \InvalidArgumentException(
                'capitalise() requires either $counterpartPurposeCode or an explicit $counterpartAccountId.'
            );
        }

        $this->classConfig($asset->asset_class); // throws on an unknown class
        $cost = (float) $asset->cost;
        $branchId = $this->resolveBranchId($asset);

        if ($counterpartAccountId !== null) {
            $counterpartAccount = $this->accountResolver->assertUnderBankGroup($counterpartAccountId, $asset->company_id);
            $counterpartLine = new LineDraft(
                purposeCode: '',
                accountId: $counterpartAccount->id,
                side: 'credit',
                amount: $cost,
                currency: 'KWD',
                originalAmount: $cost,
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_ACQUISITION',
                partyAccountRef: $partyAccountRef,
                description: "Acquisition of fixed asset #{$asset->id} ({$asset->name})",
                taskId: $asset->id,
            );
        } else {
            $counterpartLine = new LineDraft(
                purposeCode: (string) $counterpartPurposeCode,
                accountId: null,
                side: 'credit',
                amount: $cost,
                currency: 'KWD',
                originalAmount: $cost,
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_ACQUISITION',
                partyAccountRef: $partyAccountRef,
                description: "Acquisition of fixed asset #{$asset->id} ({$asset->name})",
                taskId: $asset->id,
            );
        }

        $costLine = new LineDraft(
            purposeCode: "FA_COST_{$asset->asset_class}",
            accountId: null,
            side: 'debit',
            amount: $cost,
            currency: 'KWD',
            originalAmount: $cost,
            exchangeRate: 1.0,
            transactionType: 'FIXED_ASSET_ACQUISITION',
            description: "Acquisition of fixed asset #{$asset->id} ({$asset->name})",
            taskId: $asset->id,
        );

        $draft = new DocumentDraft(
            companyId: $asset->company_id,
            branchId: $branchId,
            docType: 'JV',
            subType: null,
            docDate: Carbon::parse($asset->acquisition_date),
            narration: "Capitalisation of fixed asset #{$asset->id} ({$asset->name}).",
            lines: [$costLine, $counterpartLine],
            idempotencyKey: "fa-acq:{$asset->id}",
            userId: $userId,
        );

        $result = $this->postOrSkip($draft, 'fixed-assets.capitalise', ['fixed_asset_id' => $asset->id]);

        if ($result instanceof PostedDocument) {
            $asset->acquisition_transaction_id = $result->transaction->id;
            if ($asset->status === FixedAsset::STATUS_DRAFT) {
                $asset->status = FixedAsset::STATUS_ACTIVE;
            }
            $asset->save();
        }

        return $result;
    }

    /**
     * L8: cost minus the sum of posted depreciation credit lines on this asset's own
     * FA_ACCUM_DEP_{class} contra leaf, filtered by `journal_entries.task_id = $asset->id`.
     * Always re-derived from `journal_entries` — never a stored/cached figure (MP-2-1).
     */
    public function nbv(FixedAsset $asset): float
    {
        // VERIFIER FIX (adversarial pass, defect D3): a disposed asset carries NO book value. Its
        // `DSP` document credits the full cost off FA_COST_{class} AND debits the accumulated
        // depreciation off the contra — which nets this method's own contra sum back to zero, so
        // the derivation below would report the disposed asset at its FULL ORIGINAL COST. Gated
        // on exactly the same pair `dispose()`'s own idempotency short-circuit uses (status +
        // a recorded disposal_transaction_id), so the two can never disagree: dispose() returns
        // the existing document before ever reaching this line. (A future reversal flow that
        // un-disposes an asset must clear `disposal_transaction_id` and the status together —
        // the same pair dispose() keys on.)
        if ($asset->status === FixedAsset::STATUS_DISPOSED && $asset->disposal_transaction_id !== null) {
            return 0.0;
        }

        $classConfig = $this->classConfig($asset->asset_class);

        $contraAccount = Account::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->where('code', $classConfig['accum_dep_code'])
            ->first();

        if ($contraAccount === null) {
            // Contra leaf never minted for this company (e.g. the L7 guard refused it) —
            // nothing can ever have been posted against it, so accumulated depreciation is
            // definitionally zero.
            return round((float) $asset->cost, 3);
        }

        $totals = DB::table('journal_entries')
            ->where('account_id', $contraAccount->id)
            ->where('task_id', $asset->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(credit),0) as c, COALESCE(SUM(debit),0) as d')
            ->first();

        $accumulated = (float) $totals->c - (float) $totals->d;

        return round((float) $asset->cost - $accumulated, 3);
    }

    /**
     * Preview-only straight-line schedule (never persisted, never posted) — cost minus salvage
     * spread evenly across useful_life_months, with the FINAL month absorbing whatever residual
     * rounding leaves so the schedule's own total is exactly `cost − salvage` (see
     * {@see DepreciationRunService}'s docblock for the identical rule applied at posting time).
     *
     * @return list<array{year:int, month:int, amount:float}>
     */
    public function scheduleFor(FixedAsset $asset): array
    {
        $this->classConfig($asset->asset_class);

        $life = (int) $asset->useful_life_months;
        $depreciableBase = round((float) $asset->cost - (float) $asset->salvage, 3);
        $base = $life > 0 ? round($depreciableBase / $life, 3) : 0.0;

        // VERIFIER FIX (adversarial pass, defect D1): for a sub-fils monthly charge (a
        // depreciable base small relative to life² — e.g. base 0.003 over 5 months, or 0.004 over
        // 7) `round()` can round the monthly amount UP hard enough that `(life − 1) × base`
        // already exceeds the whole depreciable base, leaving the final month a NEGATIVE residual
        // (−0.001 in the 0.003/5 case). The sum stayed exact, but DepreciationRunService skips any
        // month whose amount is `<= 0`, so the negative month was silently dropped and the asset
        // over-depreciated below salvage. Flooring at 3dp can never overshoot — `(life − 1) ×
        // floor(D/L)` is strictly below D — so the residual month is always ≥ base and never
        // negative, and Σ is still exactly `cost − salvage`. Only the pathological case falls
        // back; every normal schedule keeps the round()-plus-residual shape unchanged.
        if ($life > 1 && round($base * ($life - 1), 3) > $depreciableBase) {
            $base = floor(($depreciableBase / $life) * 1000) / 1000;
        }

        $inService = Carbon::parse($asset->in_service_date)->startOfMonth();
        $schedule = [];
        $running = 0.0;

        for ($i = 1; $i <= $life; $i++) {
            $period = $inService->copy()->addMonthsNoOverflow($i - 1);
            $amount = $i === $life ? round($depreciableBase - $running, 3) : $base;
            $running = round($running + $amount, 3);

            $schedule[] = ['year' => $period->year, 'month' => $period->month, 'amount' => $amount];
        }

        return $schedule;
    }

    /**
     * Disposal (T4): one `DSP` document —
     *   Dr proceeds (explicit bank/cash leaf, RECEIVABLE_CONTROL on credit, or CASH_IN_HAND)
     *   Dr FA_ACCUM_DEP_{class} for accumulated depreciation to date
     *   Cr FA_COST_{class} for the asset's full cost
     *   balancing Dr ASSET_DISPOSAL_LOSS or Cr ASSET_DISPOSAL_GAIN for `proceeds − NBV`
     * keyed `fa-dsp:{id}`. Idempotent (MP-4-3): a second call for an already-disposed asset with
     * a recorded `disposal_transaction_id` returns the existing document without posting again.
     */
    public function dispose(
        FixedAsset $asset,
        \DateTimeInterface $date,
        float $proceeds,
        ?int $proceedsAccountId = null,
        ?int $partyAccountRef = null,
        ?int $userId = null,
    ): ?PostedDocument {
        if ($asset->status === FixedAsset::STATUS_DISPOSED && $asset->disposal_transaction_id !== null) {
            $existing = $this->reconstructPosted((int) $asset->disposal_transaction_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        if (! in_array($asset->status, [FixedAsset::STATUS_ACTIVE, FixedAsset::STATUS_FULLY_DEPRECIATED, FixedAsset::STATUS_DISPOSED], true)) {
            throw new \InvalidArgumentException("Fixed asset #{$asset->id} is not in a disposable status ({$asset->status}).");
        }

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $this->classConfig($asset->asset_class);
        $nbv = $this->nbv($asset);
        $accumulated = round((float) $asset->cost - $nbv, 3);
        $branchId = $this->resolveBranchId($asset);

        $lines = [];

        if ($proceeds > $tolerance) {
            $lines[] = $this->proceedsLine($asset, $proceeds, $proceedsAccountId, $partyAccountRef);
        }

        if ($accumulated > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: "FA_ACCUM_DEP_{$asset->asset_class}",
                accountId: null,
                side: 'debit',
                amount: $accumulated,
                currency: 'KWD',
                originalAmount: $accumulated,
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_DISPOSAL',
                description: "Accumulated depreciation cleared on disposal of fixed asset #{$asset->id}",
                taskId: $asset->id,
            );
        }

        $lines[] = new LineDraft(
            purposeCode: "FA_COST_{$asset->asset_class}",
            accountId: null,
            side: 'credit',
            amount: (float) $asset->cost,
            currency: 'KWD',
            originalAmount: (float) $asset->cost,
            exchangeRate: 1.0,
            transactionType: 'FIXED_ASSET_DISPOSAL',
            description: "Cost removed on disposal of fixed asset #{$asset->id}",
            taskId: $asset->id,
        );

        $diff = round($proceeds - $nbv, 3);

        if ($diff > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: 'ASSET_DISPOSAL_GAIN',
                accountId: null,
                side: 'credit',
                amount: $diff,
                currency: 'KWD',
                originalAmount: $diff,
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_DISPOSAL',
                description: "Gain on disposal of fixed asset #{$asset->id}",
                taskId: $asset->id,
            );
        } elseif ($diff < -$tolerance) {
            $lines[] = new LineDraft(
                purposeCode: 'ASSET_DISPOSAL_LOSS',
                accountId: null,
                side: 'debit',
                amount: abs($diff),
                currency: 'KWD',
                originalAmount: abs($diff),
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_DISPOSAL',
                description: "Loss on disposal of fixed asset #{$asset->id}",
                taskId: $asset->id,
            );
        }

        $draft = new DocumentDraft(
            companyId: $asset->company_id,
            branchId: $branchId,
            docType: 'DSP',
            subType: null,
            docDate: $date,
            narration: "Disposal of fixed asset #{$asset->id} ({$asset->name}).",
            lines: $lines,
            idempotencyKey: "fa-dsp:{$asset->id}",
            userId: $userId,
        );

        $result = $this->postOrSkip($draft, 'fixed-assets.dispose', ['fixed_asset_id' => $asset->id]);

        if ($result instanceof PostedDocument) {
            $asset->status = FixedAsset::STATUS_DISPOSED;
            $asset->disposal_date = $date;
            $asset->disposal_proceeds = $proceeds;
            $asset->disposal_transaction_id = $result->transaction->id;
            $asset->save();
        }

        return $result;
    }

    private function proceedsLine(FixedAsset $asset, float $proceeds, ?int $proceedsAccountId, ?int $partyAccountRef): LineDraft
    {
        if ($proceedsAccountId !== null) {
            $proceedsAccount = $this->accountResolver->assertUnderBankGroup($proceedsAccountId, $asset->company_id);

            return new LineDraft(
                purposeCode: '',
                accountId: $proceedsAccount->id,
                side: 'debit',
                amount: $proceeds,
                currency: 'KWD',
                originalAmount: $proceeds,
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_DISPOSAL',
                partyAccountRef: $partyAccountRef,
                description: "Disposal proceeds for fixed asset #{$asset->id} ({$asset->name})",
                taskId: $asset->id,
            );
        }

        if ($partyAccountRef !== null) {
            return new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $proceeds,
                currency: 'KWD',
                originalAmount: $proceeds,
                exchangeRate: 1.0,
                transactionType: 'FIXED_ASSET_DISPOSAL',
                partyAccountRef: $partyAccountRef,
                description: "Disposal proceeds (on credit) for fixed asset #{$asset->id} ({$asset->name})",
                taskId: $asset->id,
            );
        }

        return new LineDraft(
            purposeCode: 'CASH_IN_HAND',
            accountId: null,
            side: 'debit',
            amount: $proceeds,
            currency: 'KWD',
            originalAmount: $proceeds,
            exchangeRate: 1.0,
            transactionType: 'FIXED_ASSET_DISPOSAL',
            description: "Disposal proceeds (cash) for fixed asset #{$asset->id} ({$asset->name})",
            taskId: $asset->id,
        );
    }

    /** @return array{label: string, cost_code: string, accum_dep_code: string} */
    private function classConfig(string $classKey): array
    {
        $classes = (array) config('accounting.purpose_codes.fixed_asset_classes', []);

        if (! array_key_exists($classKey, $classes)) {
            throw new \InvalidArgumentException("Unknown fixed-asset class '{$classKey}'.");
        }

        return $classes[$classKey];
    }

    /**
     * POST-FIX RE-VERIFIER (second adversarial pass, defect D4): the DEPRECIATION BASIS is FROZEN
     * once any depreciation has been posted for the asset.
     *
     * {@see self::scheduleFor()} re-derives the WHOLE schedule from the asset's CURRENT
     * cost/salvage/life/in-service date every time it is called, and {@see DepreciationRunService}
     * charges whatever the current schedule says for the month being run — it never reconciles
     * against what was already posted. So lowering `cost` mid-life made the remaining months post
     * against the new, smaller base ON TOP OF the old, larger charges: cost 1000 over 5 months
     * with 3 months (600.000) already posted, then `cost` edited to 500.000, still posted 100.000
     * for month 4 — driving NBV to **−200.000** (a negative book value, accumulated depreciation
     * exceeding cost, and 200.000 KWD of overstated depreciation expense) and flipping the asset
     * to `fully_depreciated` on the way.
     *
     * Refusing the edit is the minimal guard that closes the hole without inventing scope: a real
     * basis revision (impairment, life re-estimate, salvage re-estimate) has to re-spread the
     * REMAINING depreciable base over the REMAINING months prospectively, which the plan does not
     * specify and no caller needs yet — this exception is where that feature will hang when it is
     * specified. Everything descriptive (name, code, notes, supplier, branch, status) stays
     * editable at any time, and the basis itself stays editable right up until the first `DEP`
     * document posts.
     */
    private function assertBasisNotFrozen(FixedAsset $asset, array $data): void
    {
        $changed = [];

        foreach (['asset_class', 'method'] as $key) {
            if (array_key_exists($key, $data) && (string) $data[$key] !== (string) $asset->{$key}) {
                $changed[] = $key;
            }
        }

        foreach (['cost', 'salvage'] as $key) {
            if (array_key_exists($key, $data) && round((float) $data[$key], 3) !== round((float) $asset->{$key}, 3)) {
                $changed[] = $key;
            }
        }

        if (array_key_exists('useful_life_months', $data)
            && (int) $data['useful_life_months'] !== (int) $asset->useful_life_months) {
            $changed[] = 'useful_life_months';
        }

        if (array_key_exists('in_service_date', $data)
            && Carbon::parse($data['in_service_date'])->toDateString() !== Carbon::parse($asset->in_service_date)->toDateString()) {
            $changed[] = 'in_service_date';
        }

        if ($changed === []) {
            return;
        }

        $posted = FixedAssetDepreciation::where('fixed_asset_id', $asset->id)
            ->where('status', FixedAssetDepreciation::STATUS_POSTED)
            ->exists();

        if ($posted) {
            throw new \InvalidArgumentException(sprintf(
                'Fixed asset #%d already has posted depreciation; its depreciation basis (%s) can no longer be changed. Reverse the posted depreciation first, or add a prospective revision flow.',
                (int) $asset->id,
                implode(', ', $changed)
            ));
        }
    }

    private function validate(array $data): void
    {
        $classKey = $data['asset_class'] ?? null;

        if (! is_string($classKey) || $classKey === '') {
            throw new \InvalidArgumentException('asset_class is required.');
        }

        $this->classConfig($classKey); // throws on an unknown class (MP-2-2)

        $cost = (float) ($data['cost'] ?? 0);
        $salvage = (float) ($data['salvage'] ?? 0);
        $life = (int) ($data['useful_life_months'] ?? 0);

        if ($cost <= 0) {
            throw new \InvalidArgumentException('cost must be greater than zero.');
        }

        if ($salvage < 0) {
            throw new \InvalidArgumentException('salvage must not be negative.');
        }

        if ($salvage >= $cost) {
            throw new \InvalidArgumentException('salvage must be less than cost.');
        }

        if ($life < 1) {
            throw new \InvalidArgumentException('useful_life_months must be at least 1.');
        }
    }

    private function resolveBranchId(FixedAsset $asset): ?int
    {
        return $asset->branch_id ?? Company::find($asset->company_id)?->branches()->first()?->id;
    }

    private function postOrSkip(DocumentDraft $draft, string $feederKey, array $logContext): ?PostedDocument
    {
        $legacy = function () use ($feederKey, $logContext) {
            // L2: no legacy behaviour exists for a brand-new feature — the OFF path is a logged
            // no-op, never a raw write.
            Log::info('accounting.feature_skipped_engine_off', $logContext + ['feeder' => $feederKey]);

            return null;
        };

        $result = $this->seam->post($draft, $legacy, $feederKey);

        return $result instanceof PostedDocument ? $result : null;
    }

    private function reconstructPosted(int $transactionId): ?PostedDocument
    {
        $transaction = Transaction::withoutGlobalScopes()->whereNull('deleted_at')->find($transactionId);

        if ($transaction === null) {
            return null;
        }

        $lines = JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->all();

        return new PostedDocument($transaction, $lines);
    }
}
