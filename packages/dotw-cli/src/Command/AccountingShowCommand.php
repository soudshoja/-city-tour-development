<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:accounting:show — chart-of-accounts ledger view.
 *
 * Aggregates accounting_entries by account_code showing:
 *   - Account code and name
 *   - Total debits
 *   - Total credits
 *   - Running balance (credits - debits)
 *
 * Optional filters:
 *   --booking=<ref>   show only entries for this booking
 *   --from=<date>     filter from date (YYYY-MM-DD)
 *   --to=<date>       filter to date (YYYY-MM-DD)
 *
 * All filter values are bound via PDO prepared statements (T-28-16 mitigated).
 */
#[AsCommand(
    name: 'dotw:accounting:show',
    description: 'Show chart-of-accounts ledger (debits, credits, balances)',
)]
class AccountingShowCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('booking', null, InputOption::VALUE_REQUIRED, 'Filter by booking reference')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Filter from date (YYYY-MM-DD)')
            ->addOption('to',   null, InputOption::VALUE_REQUIRED, 'Filter to date (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $bookingFilter = $input->getOption('booking');
        $fromFilter    = $input->getOption('from');
        $toFilter      = $input->getOption('to');

        // Build WHERE clause with named bindings (T-28-16: no raw interpolation of user values)
        $conditions = ['1=1'];
        $bindings   = [];

        if ($bookingFilter) {
            $conditions[] = 'booking_ref = :booking_ref';
            $bindings[':booking_ref'] = $bookingFilter;
        }
        if ($fromFilter) {
            $conditions[] = 'DATE(created_at) >= :from_date';
            $bindings[':from_date'] = $fromFilter;
        }
        if ($toFilter) {
            $conditions[] = 'DATE(created_at) <= :to_date';
            $bindings[':to_date'] = $toFilter;
        }

        $where = implode(' AND ', $conditions);

        // Ledger grouped by account
        $ledgerSql = "
            SELECT
                account_code,
                account_name,
                currency,
                SUM(debit)  AS total_debit,
                SUM(credit) AS total_credit,
                (SUM(credit) - SUM(debit)) AS balance
            FROM accounting_entries
            WHERE {$where}
            GROUP BY account_code, account_name, currency
            ORDER BY account_code ASC
        ";
        $ledgerStmt = $this->db->pdo()->prepare($ledgerSql);
        $ledgerStmt->execute($bindings);
        $ledger = $ledgerStmt->fetchAll();

        // Detail entries (always fetched; shown when --booking filter is active)
        $detailSql = "
            SELECT id, booking_ref, entry_type, account_code, account_name,
                   debit, credit, currency, description, created_at
            FROM accounting_entries
            WHERE {$where}
            ORDER BY created_at ASC, id ASC
        ";
        $detailStmt = $this->db->pdo()->prepare($detailSql);
        $detailStmt->execute($bindings);
        $entries = $detailStmt->fetchAll();

        // Grand totals
        $grandDebit  = array_sum(array_column($ledger, 'total_debit'));
        $grandCredit = array_sum(array_column($ledger, 'total_credit'));

        if ($this->jsonMode) {
            return $this->success($output, [
                'ledger'       => $ledger,
                'entries'      => $entries,
                'grand_debit'  => $grandDebit,
                'grand_credit' => $grandCredit,
                'net_balance'  => $grandCredit - $grandDebit,
            ]);
        }

        // Human-readable table
        $output->writeln('');
        $output->writeln('<info>Chart of Accounts — Ledger</info>');
        if ($bookingFilter) {
            $output->writeln("Booking: {$bookingFilter}");
        }
        if ($fromFilter || $toFilter) {
            $output->writeln("Period: " . ($fromFilter ?? '*') . ' → ' . ($toFilter ?? '*'));
        }
        $output->writeln('');
        $output->writeln(sprintf(
            '%-6s  %-28s  %-4s  %12s  %12s  %12s',
            'Code', 'Account', 'CCY', 'Debit', 'Credit', 'Balance'
        ));
        $output->writeln(str_repeat('─', 78));

        foreach ($ledger as $row) {
            $output->writeln(sprintf(
                '%-6s  %-28s  %-4s  %12s  %12s  %12s',
                $row['account_code'],
                substr($row['account_name'], 0, 28),
                $row['currency'],
                number_format((float) $row['total_debit'],  3),
                number_format((float) $row['total_credit'], 3),
                number_format((float) $row['balance'],      3)
            ));
        }

        $output->writeln(str_repeat('─', 78));
        $output->writeln(sprintf(
            '%-6s  %-28s  %-4s  %12s  %12s  %12s',
            'TOTAL', '', '',
            number_format($grandDebit,  3),
            number_format($grandCredit, 3),
            number_format($grandCredit - $grandDebit, 3)
        ));
        $output->writeln('');

        // Show detail rows when filtered by booking (for journal audit trail)
        if ($bookingFilter && !empty($entries)) {
            $output->writeln('<info>Journal Entries:</info>');
            foreach ($entries as $e) {
                $output->writeln(sprintf(
                    '  %s | %-6s %-22s | Dr %10s  Cr %10s | %s',
                    $e['created_at'],
                    $e['account_code'],
                    substr($e['account_name'], 0, 22),
                    number_format((float) $e['debit'],  3),
                    number_format((float) $e['credit'], 3),
                    $e['description']
                ));
            }
            $output->writeln('');
        }

        return self::EXIT_SUCCESS;
    }
}
