<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Agent;
use App\Http\Controllers\AgentController;
use App\Models\AgentMonthlyCommissions;
use Carbon\Carbon;

class ProcessAgentCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-agent-commission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store monthly commission per agent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = Carbon::createFromDate(date('Y'), date('m'), 1)->subMonth();
        $agents = Agent::whereIn('type_id', [3, 4])->get();

        foreach ($agents as $agent) {
            $summary = app(AgentController::class)->calculateMonthlySummary($agent, $month);

            AgentMonthlyCommissions::updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'month' => $month->month,
                    'year' => $month->year,
                ],
                [
                    'salary' => $agent->salary,
                    'target' => $agent->target,
                    'commission_rate' => $agent->commission,
                    'total_commission' => $summary['commission'],
                    'total_profit' => $summary['profit'],
                ]
            );
        }

        $this->info('Agent monthly commissions calculated.');
    }
}
