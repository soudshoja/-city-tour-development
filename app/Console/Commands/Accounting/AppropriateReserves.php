<?php

declare(strict_types=1);

namespace App\Console\Commands\Accounting;

use App\Exceptions\Accounting\PostingException;
use App\Exceptions\Accounting\ReserveAppropriationException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Services\Accounting\ReserveAppropriationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;

/**
 * XBRL X9 (PLAN.md L13, X9; H-M3): the year-end reserve appropriation. Run it after the year-end
 * adjusting journals and BEFORE December is locked and the YEC; while December is soft-closed for
 * the audit, pass `--reason=` (and act as a user holding `accounting.period.post-soft-closed`).
 * Re-run it whenever the year's profit changes (it posts only the difference, and nothing when
 * nothing changed). A loss or zero-profit year posts nothing and records a nil appropriation.
 *
 * Approval-gated: the acting `--user=` must hold `accounting.period.close` (the year-end tier).
 * `--voluntary-rate=` is the voluntary reserve rate in percent of net profit (OD-3; 0 to 10,
 * up to two decimals); omitted, the year keeps the rate it last recorded, else the configured
 * default (0%). Thin wrapper; the logic is {@see ReserveAppropriationService::appropriate()}.
 */
class AppropriateReserves extends Command
{
    protected $signature = 'accounting:appropriate-reserves
                            {company : Company id}
                            {year : The fiscal year, e.g. 2026}
                            {--user= : Acting user id; must hold accounting.period.close}
                            {--reason= : Required while December is soft-closed for the audit}
                            {--voluntary-rate= : Voluntary reserve rate in percent of net profit (0 to 10)}';

    protected $description = 'Post the year-end statutory (10%, capped at 50% of capital) and voluntary reserve appropriation, or record a nil one for a loss year.';

    public function handle(ReserveAppropriationService $service): int
    {
        $companyId = (int) $this->argument('company');
        $year = (int) $this->argument('year');
        $userOption = $this->option('user');

        if ($userOption === null || $userOption === '') {
            $this->error('--user= is required (a console run has no authenticated user).');

            return self::FAILURE;
        }

        $rate = null;
        $rateOption = $this->option('voluntary-rate');
        if ($rateOption !== null && $rateOption !== '') {
            if (preg_match('/^\d{1,2}(\.\d{1,2})?$/', (string) $rateOption) !== 1) {
                $this->error("--voluntary-rate must be a percentage such as 5 or 2.5; got '{$rateOption}'. Nothing was posted.");

                return self::FAILURE;
            }
            [$whole, $fraction] = array_pad(explode('.', (string) $rateOption, 2), 2, '');
            $rate = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        }

        try {
            $result = $service->appropriate($companyId, $year, (int) $userOption, $this->option('reason'), $rate);
        } catch (ReserveAppropriationException|AuthorizationException|PostingException $e) {
            $this->error("The FY{$year} reserve appropriation was NOT recorded; nothing was changed. ".$e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['steps'] as $i => $step) {
            $this->line(sprintf('%d. %s', $i + 1, $step));
        }

        return self::SUCCESS;
    }
}
