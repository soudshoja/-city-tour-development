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

        $this->createAgentSubAccounts();
    }

    private function createAgentSubAccounts(): void
    {
        $agents = DB::table('agents')->get();

        foreach ($agents as $agent) {
            $agentMainAccount = DB::table('accounts')->where('agent_id', $agent->id)->first();
            if (!$agentMainAccount) continue;

            DB::table('accounts')->where('id', $agentMainAccount->id)->update(['is_group' => 1]);

            // Create unique codes by appending suffixes
            $profitAccId = $this->getOrCreateSubAccount($agentMainAccount, 'Profit', $agent->id, '-P');
            $lossAccId = $this->getOrCreateSubAccount($agentMainAccount, 'Debit Loss', $agent->id, '-L');

            DB::table('agents')->where('id', $agent->id)->update([
                'profit_account_id' => $profitAccId,
                'loss_account_id'   => $lossAccId,
            ]);
        }
    }

    private function getOrCreateSubAccount($parentAccount, $name, $agentId, $suffix)
    {
        $existing = DB::table('accounts')->where('parent_id', $parentAccount->id)->where('name', $name)->first();
        if ($existing) return $existing->id;

        return DB::table('accounts')->insertGetId([
            'code'           => $parentAccount->code . $suffix,
            'name'           => $name,
            'company_id'     => $parentAccount->company_id,
            'root_id'        => $parentAccount->root_id,
            'parent_id'      => $parentAccount->id,
            'branch_id'      => $parentAccount->branch_id,
            'agent_id'       => $agentId,
            'account_type'   => $parentAccount->account_type,
            'report_type'    => $parentAccount->report_type,
            'level'          => ($parentAccount->level ?? 0) + 1,
            'is_group'       => 0,
            'disabled'       => 0,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance'       => 0,
            'currency'       => $parentAccount->currency ?? 'KWD',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function down(): void
    {
        $boundAccounts = DB::table('agents')->select('profit_account_id', 'loss_account_id')
            ->whereNotNull('profit_account_id')->orWhereNotNull('loss_account_id')->get();

        $idsToDelete = [];
        foreach ($boundAccounts as $row) {
            if ($row->profit_account_id) $idsToDelete[] = $row->profit_account_id;
            if ($row->loss_account_id) $idsToDelete[] = $row->loss_account_id;
        }

        DB::table('agents')->update(['profit_account_id' => null, 'loss_account_id' => null]);
        if (!empty($idsToDelete)) DB::table('accounts')->whereIn('id', array_unique($idsToDelete))->delete();

        Schema::table('agents', function (Blueprint $table) {
            if (Schema::hasColumn('agents', 'profit_account_id')) {
                $table->dropForeign(['profit_account_id']);
                $table->dropColumn('profit_account_id');
            }
            if (Schema::hasColumn('agents', 'loss_account_id')) {
                $table->dropForeign(['loss_account_id']);
                $table->dropColumn('loss_account_id');
            }
        });
    }
};
