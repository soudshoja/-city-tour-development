<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedBigInteger('profit_account_id')->nullable()->after('type_id');
            $table->unsignedBigInteger('loss_account_id')->nullable()->after('profit_account_id');
            $table->foreign('profit_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('loss_account_id')->references('id')->on('accounts')->onDelete('set null');
        });

        foreach (DB::table('agents')->orderBy('id')->get() as $agent) {
            $branch = DB::table('branches')->find($agent->branch_id);
            if (!$branch) continue;

            DB::table('agents')->where('id', $agent->id)->update([
                'profit_account_id' => $this->createProfitAccount($agent, $branch->company_id, $agent->branch_id),
                'loss_account_id' => $this->createLossAccount($agent, $branch->company_id, $agent->branch_id),
            ]);
        }
    }

    private function createProfitAccount($agent, int $companyId, int $branchId): int
    {
        // Liabilities -> Accrued Expenses -> Agent Profit Payable (2230) -> [Agent]
        $accruedExpenses = $this->findAccount($companyId, ['Liabilities', 'Accrued Expenses']);

        $profitGroup = $this->getOrCreateAccount(
            $accruedExpenses,
            'Agent Profit Payable',
            '2230',
            $companyId,
            isGroup: true
        );

        return $this->getOrCreateAccountId(
            $profitGroup,
            $agent->name ?? "Agent #{$agent->id}",
            $this->getNextCode($profitGroup),
            $companyId,
            $branchId,
            $agent->id
        );
    }

    private function createLossAccount($agent, int $companyId, int $branchId): int
    {
        // Assets -> AR -> Company -> Agent -> Agent Loss Receivable
        $ar = $this->findAccount($companyId, ['Assets', 'Accounts Receivable']);
        $company = DB::table('companies')->find($companyId);

        // Get or create Company group under AR
        $companyGroup = $this->getOrCreateAccount(
            $ar,
            $company->name,
            $this->getNextCode($ar),
            $companyId,
            isGroup: true
        );

        // Get or create Agent group under Company
        $agentGroup = $this->getOrCreateAccount(
            $companyGroup,
            $agent->name ?? "Agent #{$agent->id}",
            $this->getNextCode($companyGroup),
            $companyId,
            $branchId,
            $agent->id,
            isGroup: true
        );

        // Create "Agent Loss Receivable" leaf account (final account to bind)
        return $this->getOrCreateAccountId(
            $agentGroup,
            'Agent Loss Receivable',
            $this->getNextCode($agentGroup),
            $companyId,
            $branchId,
            $agent->id
        );
    }

    private function findAccount(int $companyId, array $path)
    {
        $account = DB::table('accounts')
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->where('name', $path[0])
            ->first();

        for ($i = 1; $i < count($path); $i++) {
            $account = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('parent_id', $account->id)
                ->where('name', $path[$i])
                ->first();
        }

        return $account;
    }

    private function getOrCreateAccount(
        $parent,
        string $name,
        string $code,
        int $companyId,
        ?int $branchId = null,
        ?int $agentId = null,
        bool $isGroup = false
    ) {
        $query = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('parent_id', $parent->id)
            ->where('name', $name);

        if ($agentId) $query->where('agent_id', $agentId);

        $existing = $query->first();
        if ($existing) {
            if ($isGroup) DB::table('accounts')->where('id', $existing->id)->update(['is_group' => 1]);
            return $existing;
        }

        $accountId = DB::table('accounts')->insertGetId([
            'code' => $code,
            'name' => $name,
            'company_id' => $companyId,
            'root_id' => $parent->root_id ?? $parent->id,
            'parent_id' => $parent->id,
            'branch_id' => $branchId,
            'agent_id' => $agentId,
            'account_type' => $parent->account_type,
            'report_type' => $parent->report_type ?? 'balance_sheet',
            'level' => ($parent->level ?? 0) + 1,
            'is_group' => $isGroup ? 1 : 0,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'currency' => 'KWD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('accounts')->find($accountId);
    }

    private function getOrCreateAccountId(
        $parent,
        string $name,
        string $code,
        int $companyId,
        ?int $branchId = null,
        ?int $agentId = null
    ): int {
        $query = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('parent_id', $parent->id)
            ->where('name', $name);

        if ($agentId) $query->where('agent_id', $agentId);

        $existing = $query->first();
        if ($existing) return $existing->id;

        return DB::table('accounts')->insertGetId([
            'code' => $code,
            'name' => $name,
            'company_id' => $companyId,
            'root_id' => $parent->root_id ?? $parent->id,
            'parent_id' => $parent->id,
            'branch_id' => $branchId,
            'agent_id' => $agentId,
            'account_type' => $parent->account_type,
            'report_type' => $parent->report_type ?? 'balance_sheet',
            'level' => ($parent->level ?? 0) + 1,
            'is_group' => 0,
            'disabled' => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'currency' => 'KWD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getNextCode($parent): string
    {
        $last = DB::table('accounts')
            ->where('parent_id', $parent->id)
            ->orderByDesc('code')
            ->value('code');

        return $last && is_numeric($last)
            ? (string)((int)$last + 1)
            : (is_numeric($parent->code) ? (string)((int)$parent->code + 1) : $parent->code . '-001');
    }

    public function down(): void
    {
        $ids = DB::table('agents')
            ->where(function ($query) {
                $query->whereNotNull('profit_account_id')
                      ->orWhereNotNull('loss_account_id');
            })
            ->get()
            ->flatMap(fn($a) => [$a->profit_account_id, $a->loss_account_id])
            ->filter()
            ->unique()
            ->toArray();

        DB::table('agents')->update(['profit_account_id' => null, 'loss_account_id' => null]);
        if ($ids) DB::table('accounts')->whereIn('id', $ids)->delete();

        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['profit_account_id']);
            $table->dropForeign(['loss_account_id']);
            $table->dropColumn(['profit_account_id', 'loss_account_id']);
        });
    }
};
