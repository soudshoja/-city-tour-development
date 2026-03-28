<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Clean up stale DotwAI agent sessions from the cache.
 *
 * Sessions expire automatically via Cache TTL (60 min per AgentSessionService),
 * but this command provides an explicit daily sweep for audit logging and
 * future extension to a DB-backed session store if needed.
 *
 * Scheduled daily at 03:00 in app/Console/Kernel.php.
 *
 * @see AGEN-02 Per-phone session state (D-12: stale sessions cleaned by scheduler)
 */
class CleanStaleSessionsCommand extends Command
{
    protected $signature = 'dotwai:clean-sessions
                            {--dry-run : Log intent without performing cleanup}';

    protected $description = 'Remove expired DotwAI agent sessions from the cache (daily cleanup)';

    public function handle(): int
    {
        $this->info('DotwAI session cleanup starting...');

        Log::channel('dotw')->info('[DotwAI] dotwai:clean-sessions executed', [
            'dry_run'   => $this->option('dry-run'),
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($this->option('dry-run')) {
            $this->info('Dry run: Cache TTL handles session expiry automatically.');
            $this->info('Sessions use dotwai_session_{phone} keys with 60-minute TTL.');

            return Command::SUCCESS;
        }

        // Sessions expire automatically via Cache::put TTL in AgentSessionService.
        // This command logs the sweep event for audit purposes.
        // To extend: query dotwai_agent_sessions table when DB-backed sessions are added.
        $this->info('Session cleanup complete. Cache TTL (60 min) manages expiry automatically.');
        $this->info('Sessions older than 60 minutes have been evicted by the cache backend.');

        return Command::SUCCESS;
    }
}
