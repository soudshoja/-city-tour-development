<?php

namespace App\Console\Commands;

use App\Services\ErrorAlertService;
use Illuminate\Console\Command;

class CheckErrorThresholds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:check-errors
                            {--clear-cooldowns : Clear all alert cooldowns}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check error thresholds and send alerts if needed (ERR-05)';

    /**
     * Execute the console command.
     */
    public function handle(ErrorAlertService $alertService): int
    {
        $this->info('Checking error thresholds...');

        // Clear cooldowns if requested
        if ($this->option('clear-cooldowns')) {
            $alertService->clearCooldowns();
            $this->info('✓ Alert cooldowns cleared');
        }

        // Check thresholds
        $result = $alertService->checkThresholds();

        if (!$result['checked']) {
            $this->warn("⚠ {$result['reason']}");
            return Command::SUCCESS;
        }

        if (empty($result['alerts'])) {
            $this->info('✓ No alerts triggered - all systems normal');
            return Command::SUCCESS;
        }

        // Display alerts
        $this->warn("⚠ {$result['alert_count']} alert(s) triggered:");

        foreach ($result['alerts'] as $alert) {
            $this->newLine();
            $this->line("  Alert Type: {$alert['type']}");
            $this->line("  Severity: {$alert['severity']}");
            $this->line("  Message: {$alert['message']}");

            if (isset($alert['current_rate'])) {
                $this->line("  Current Rate: {$alert['current_rate']}%");
                $this->line("  Threshold: {$alert['threshold']}%");
            }

            if (isset($alert['consecutive_count'])) {
                $this->line("  Consecutive Failures: {$alert['consecutive_count']}");
                $this->line("  Error Codes: " . implode(', ', $alert['error_codes']));
            }
        }

        $this->newLine();
        $this->info('✓ Alerts have been logged');

        return Command::SUCCESS;
    }
}
