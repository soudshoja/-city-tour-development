<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use Illuminate\Support\Facades\Log;

class ProfitCalculationService
{
    /**
     * Calculate profit for an invoice detail (task) considering charge bearer settings.
     * 
     * Formula:
     * - Base markup = task_price (invoice price) - supplier_price
     * - Extra charges = Gateway charges (from invoice_partial) + Supplier surcharges
     * - Agent charge deduction = extra_charges * agent_percentage based on settings
     * - Profit = markup - agent_charge_deduction
     * 
     * Example scenarios (task_price=100, supplier_price=80, extra_charges=1.35):
     * - Company bears all: profit = 100 - 80 - 0 = 20
     * - Agent bears all: profit = 100 - 80 - 1.35 = 18.65
     * - Split 50/50: profit = 100 - 80 - 0.675 = 19.325
     * 
     * @param InvoiceDetail $invoiceDetail The invoice detail to calculate profit for
     * @param float|null $gatewayCharge Override gateway charge (if not provided, calculated from invoice)
     * @param float|null $supplierSurcharge Override supplier surcharge
     * @return array Detailed calculation result
     */
    public function calculateProfit(InvoiceDetail $invoiceDetail, ?float $gatewayCharge = null, ?float $supplierSurcharge = null): array
    {
        $invoice = $invoiceDetail->invoice;
        $task = $invoiceDetail->task;
        $agent = $invoice->agent ?? $task?->agent;

        if (!$agent) {
            Log::warning('ProfitCalculationService: No agent found for invoice detail', [
                'invoice_detail_id' => $invoiceDetail->id,
            ]);
            return $this->buildResult($invoiceDetail, 0, 0, $invoiceDetail->markup_price ?? 0, null);
        }

        $companyId = $agent->branch?->company_id;

        if (!$companyId) {
            Log::warning('ProfitCalculationService: No company found for agent', [
                'agent_id' => $agent->id,
            ]);
            return $this->buildResult($invoiceDetail, 0, 0, $invoiceDetail->markup_price ?? 0, null);
        }

        // Get charge bearer settings for this agent
        $settings = AgentCharge::getForAgent($agent->id, $companyId);

        // Calculate base markup
        $taskPrice = (float) $invoiceDetail->task_price;
        $supplierPrice = (float) $invoiceDetail->supplier_price;
        $baseMarkup = (float) ($invoiceDetail->markup_price ?? ($taskPrice - $supplierPrice));

        // Get gateway charge if not provided (distributed from invoice partials)
        if ($gatewayCharge === null) {
            $gatewayCharge = $this->getGatewayChargeForDetail($invoice, $invoiceDetail);
        }

        // Get supplier surcharge if not provided
        if ($supplierSurcharge === null) {
            $supplierSurcharge = $this->getSupplierSurchargeForTask($task, $companyId);
        }

        // Total extra charges
        $totalExtraCharge = $gatewayCharge + $supplierSurcharge;

        // Calculate how much agent bears based on settings
        $agentChargeDeduction = $settings->calculateAgentChargeDeduction($totalExtraCharge);

        // Calculate final profit
        $profit = $baseMarkup - $agentChargeDeduction;

        Log::info('ProfitCalculationService: Calculated profit', [
            'invoice_detail_id' => $invoiceDetail->id,
            'agent_id' => $agent->id,
            'task_price' => $taskPrice,
            'supplier_price' => $supplierPrice,
            'base_markup' => $baseMarkup,
            'gateway_charge' => $gatewayCharge,
            'supplier_surcharge' => $supplierSurcharge,
            'total_extra_charge' => $totalExtraCharge,
            'charge_bearer' => $settings->charge_bearer,
            'agent_percentage' => $settings->getAgentPercentageToApply(),
            'agent_charge_deduction' => $agentChargeDeduction,
            'profit' => $profit,
        ]);

        return $this->buildResult(
            $invoiceDetail,
            $totalExtraCharge,
            $agentChargeDeduction,
            $profit,
            $settings
        );
    }

    /**
     * Calculate profit for all invoice details in an invoice.
     * 
     * @param Invoice $invoice The invoice
     * @return array Array of profit calculations per detail
     */
    public function calculateInvoiceProfit(Invoice $invoice): array
    {
        $results = [];
        $totalProfit = 0;

        // Get total gateway charge from paid partials
        $totalGatewayCharge = $this->getTotalGatewayChargeForInvoice($invoice);

        // Get number of details to distribute gateway charge
        $detailCount = $invoice->invoiceDetails->count();

        foreach ($invoice->invoiceDetails as $detail) {
            // Distribute gateway charge proportionally based on task price
            $gatewayChargeForDetail = $this->getGatewayChargeForDetail($invoice, $detail, $totalGatewayCharge);

            $result = $this->calculateProfit($detail, $gatewayChargeForDetail);
            $results[] = $result;
            $totalProfit += $result['profit'];
        }

        return [
            'details' => $results,
            'total_profit' => round($totalProfit, 3),
            'total_gateway_charge' => $totalGatewayCharge,
            'detail_count' => $detailCount,
        ];
    }

    /**
     * Update invoice detail with calculated profit values.
     * 
     * @param InvoiceDetail $invoiceDetail
     * @param float|null $gatewayCharge
     * @param float|null $supplierSurcharge
     * @return InvoiceDetail Updated invoice detail
     */
    public function updateInvoiceDetailProfit(InvoiceDetail $invoiceDetail, ?float $gatewayCharge = null, ?float $supplierSurcharge = null): InvoiceDetail
    {
        $result = $this->calculateProfit($invoiceDetail, $gatewayCharge, $supplierSurcharge);

        $invoiceDetail->extra_charge = $result['extra_charge'];
        $invoiceDetail->agent_charge_deduction = $result['agent_charge_deduction'];
        $invoiceDetail->profit = $result['profit'];
        $invoiceDetail->charge_bearer = $result['charge_bearer'];
        $invoiceDetail->agent_percentage_applied = $result['agent_percentage_applied'];

        // Calculate commission if agent type requires it (types 2, 3, 4)
        $agent = $invoiceDetail->invoice?->agent ?? $invoiceDetail->task?->agent;
        if ($agent && in_array($agent->type_id, [2, 3, 4])) {
            $commissionRate = (float) ($agent->commission ?? 0.15);
            $invoiceDetail->commission = round($result['profit'] * $commissionRate, 3);
        }

        $invoiceDetail->save();

        return $invoiceDetail;
    }

    /**
     * Get gateway charge distributed to a specific invoice detail.
     * Distributes proportionally based on task_price.
     * 
     * @param Invoice $invoice
     * @param InvoiceDetail $invoiceDetail
     * @param float|null $totalGatewayCharge Pre-calculated total (optional)
     * @return float
     */
    private function getGatewayChargeForDetail(Invoice $invoice, InvoiceDetail $invoiceDetail, ?float $totalGatewayCharge = null): float
    {
        if ($totalGatewayCharge === null) {
            $totalGatewayCharge = $this->getTotalGatewayChargeForInvoice($invoice);
        }

        if ($totalGatewayCharge <= 0) {
            return 0;
        }

        // Distribute proportionally based on task price
        $totalInvoiceAmount = $invoice->invoiceDetails->sum('task_price');

        if ($totalInvoiceAmount > 0) {
            $proportion = $invoiceDetail->task_price / $totalInvoiceAmount;
            return round($totalGatewayCharge * $proportion, 3);
        }

        // Equal distribution if amounts are zero
        $detailCount = $invoice->invoiceDetails->count();
        return $detailCount > 0 ? round($totalGatewayCharge / $detailCount, 3) : 0;
    }

    /**
     * Get total gateway charge from invoice partials.
     * 
     * @param Invoice $invoice
     * @return float
     */
    private function getTotalGatewayChargeForInvoice(Invoice $invoice): float
    {
        // Sum service charges from paid partials
        return (float) InvoicePartial::where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum('service_charge');
    }

    /**
     * Get supplier surcharge for a task.
     * Sums all active surcharges from supplier_surcharges table for the supplier+company.
     * 
     * @param $task
     * @param int $companyId
     * @return float
     */
    private function getSupplierSurchargeForTask($task, int $companyId): float
    {
        if (!$task || !$task->supplier_id) {
            return 0;
        }

        // Get the supplier_company pivot record
        $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (!$supplierCompany) {
            return 0;
        }

        // Sum all surcharges for this supplier_company that apply to this task
        $totalSurcharge = 0;
        $surcharges = SupplierSurcharge::where('supplier_company_id', $supplierCompany->id)->get();

        foreach ($surcharges as $surcharge) {
            if ($surcharge->charge_mode === 'task') {
                // Check if surcharge applies to task's status
                if ($surcharge->canChargeForStatus($task->status)) {
                    $totalSurcharge += $surcharge->amount;
                }
            } elseif ($surcharge->charge_mode === 'reference') {
                // Check if task's reference matches any of the surcharge's references
                foreach ($surcharge->references as $ref) {
                    if ($task->reference === $ref->reference) {
                        // Check charge behavior
                        if ($surcharge->charge_behavior === 'single' && $ref->is_charged) {
                            continue; // Already charged
                        }
                        $totalSurcharge += $surcharge->amount;
                        break; // Only add once per surcharge
                    }
                }
            }
        }

        return (float) $totalSurcharge;
    }

    /**
     * Build standardized result array.
     */
    private function buildResult(
        InvoiceDetail $invoiceDetail,
        float $extraCharge,
        float $agentChargeDeduction,
        float $profit,
        ?AgentCharge $settings
    ): array {
        return [
            'invoice_detail_id' => $invoiceDetail->id,
            'task_id' => $invoiceDetail->task_id,
            'task_price' => (float) $invoiceDetail->task_price,
            'supplier_price' => (float) $invoiceDetail->supplier_price,
            'base_markup' => (float) $invoiceDetail->markup_price,
            'extra_charge' => round($extraCharge, 3),
            'agent_charge_deduction' => round($agentChargeDeduction, 3),
            'profit' => round($profit, 3),
            'charge_bearer' => $settings?->charge_bearer ?? AgentCharge::BEARER_COMPANY,
            'agent_percentage_applied' => $settings?->getAgentPercentageToApply() ?? 0,
        ];
    }

    /**
     * Recalculate commission based on new profit.
     * 
     * @param InvoiceDetail $invoiceDetail
     * @param Agent $agent
     * @return float Commission amount
     */
    public function calculateCommission(InvoiceDetail $invoiceDetail, Agent $agent): float
    {
        // Only certain agent types get commission (2, 3, 4)
        if (!in_array($agent->type_id, [2, 3, 4])) {
            return 0;
        }

        $commissionRate = (float) ($agent->commission ?? 0.15);
        $profit = (float) $invoiceDetail->profit;

        return round($profit * $commissionRate, 3);
    }
}
