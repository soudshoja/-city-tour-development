<?php

namespace App\Console\Commands;

use App\Models\PaymentMethod;
use App\Models\PaymentMethodGroup;
use Illuminate\Console\Command;

class GroupPaymentMethod extends Command
{
    protected $signature = 'payment-method:group';

    protected $description = 'Assign payment methods to their respective groups based on their names';

    public function handle()
    {
        $paymentMethods = PaymentMethod::all();
        $paymentMethodsGroup = PaymentMethodGroup::all();

        foreach($paymentMethods as $method){

            if($method->payment_method_group_id){
                $this->info('Payment method ' . $method->english_name . ' of company ' . $method->company->name . ' is already assigned to a group. Skipping.');
                continue;
            }

            $assigned = false;
            $methodName = strtoupper(trim($method->english_name));

            // Try exact or partial matching first
            foreach($paymentMethodsGroup as $group){
                // Normalize group name for comparison (remove spaces, slashes, underscores)
                $groupNameNormalized = str_replace(['_', '/', ' '], '', strtoupper($group->name));
                $methodNameNormalized = str_replace(['_', '/', ' ', '(', ')'], '', $methodName);
                
                // Check if method name contains the group name
                if (str_contains($methodNameNormalized, $groupNameNormalized)){
                    $method->payment_method_group_id = $group->id;
                    $method->save();

                    $this->info('Assigned ' . $method->english_name . ' of company ' . $method->company->name . ' to group ' . $group->name);
                    $assigned = true;
                    break;
                }
            }

            // If not assigned, check for card-related terms that should go to VISA/MASTER
            if (!$assigned) {
                if (str_contains($methodName, 'VISA') || str_contains($methodName, 'MASTER') || 
                    str_contains($methodName, 'CREDIT') || str_contains($methodName, 'DEBIT') || 
                    str_contains($methodName, 'CARD')) {
                    
                    $visaMasterGroup = $paymentMethodsGroup->firstWhere('name', 'VISA/MASTER');
                    if ($visaMasterGroup) {
                        $method->payment_method_group_id = $visaMasterGroup->id;
                        $method->save();
                        $this->info('Assigned ' . $method->english_name . ' of company ' . $method->company->name . ' to group ' . $visaMasterGroup->name);
                        $assigned = true;
                    }
                }
            }

            if (!$assigned) {
                $this->warn('No matching group found for payment method: ' . $method->english_name);
            }
        }
    }
}
