<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Http\JsonResponse;
use Illuminate\Console\Command;
use App\Services\IataEasyPayService;
use App\Console\Command\Request;
use App\Models\Company;
use App\Models\Wallet;
use Exception;


class RecordWalletBalance extends Command
{   
    protected $signature = 'record:wallet-balance
                            {--companyId= : The ID of the company}
                            {--dry-run : Show expected process without making changes}
                            {--proceed : Skip dry run and make changes onto database}
                            ';
    protected $description = 'Record IATA Wallet balance from the API';

    public function handle() 
    {
        $dryRun = $this->option('dry-run');
        $proceed = $this->option('proceed');
        $companyId = $this->option('companyId');

        if (!$companyId) {
            $this->error('Company ID is required when using this command');
            return;
        }

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting to record IATA wallet balance for the day');

        try {
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("Company with ID {$companyId} not found.");
                return 1;
            }

            $response = $this->getCompanyWallets($company);

            $wallets = $response['wallets']; 
            if ($wallets->isEmpty()) {
                $this->info('No wallet data found from the API');
                return 0;
            }

            if ($dryRun) {
                $this->table(
                    ['Wallet ID', 'IATA Number', 'Currency', 'Wallet Name', 'Wallet Balance', 'Opening Balance', 'Closing Balance'],
                    $wallets->map(function ($wallet) {
                    
                        $walletId = $wallet['id'];

                        if (preg_match('/- (\d+) -/', $wallet['name'], $matches)) {
                            $iataNumber = $matches[1];
                        } else {
                            $iataNumber = null;
                        }
                        
                        $walletCurrency = $wallet['currency'];

                        return [
                            'Wallet ID'       => $walletId,
                            'IATA Number'     => $iataNumber,
                            'Currency'        => $walletCurrency,
                            'Wallet Name'     => $wallet['name'],
                            'Wallet Balance'  => $wallet['balance'],
                            'Opening Balance' => number_format(0.000, 3),
                            'Closing Balance' => number_format(0.000, 3),
                        ];
                    })->toArray()
                );

                $this->info("Dry Run completed - no changes has been made");

            } elseif ($proceed) {
                $firstWallet = $wallets->first();

                $walletId = $firstWallet['id'] ?? null;
                $walletName = $firstWallet['name'] ?? null;
                $walletCurrency = $firstWallet['currency'] ?? null;
                $iataBalance = $firstWallet['balance'] ?? 0;

                if (preg_match('/- (\d+) -/', $walletName, $matches)) {
                    $iataNumber = $matches[1];
                } else {
                    $iataNumber = null;
                }

                Wallet::create([
                    'wallet_id' => $walletId,
                    'iata_number' => $iataNumber,
                    'currency' => $walletCurrency,
                    'wallet_balance' => $iataBalance,
                ]);

                $this->info("Wallet balance has been recorded for company ID {$companyId}: Wallet Balance -> {$walletCurrency} {$iataBalance}");
            }

        } catch (\Exception $e) {
            $this->error('Error fetching wallet balance: ' . $e->getMessage());
        }
    }

    private function getCompanyWallets($company)
    {
        $wallets = collect();
        $iataBalance = 0;
        $walletName = 'N/A';
        $error = null;

        try {
            if (!$company || !$company->iata_code || !$company->iata_client_id || !$company->iata_client_secret) {
                Log::warning('Missing IATA credentials for company ID: ' . ($company->id ?? 'N/A'));
                throw new \Exception('Missing IATA credentials. Please update your company profile with the IATA Code, Client ID, and Client Secret.');
            }

            $service = new IataEasyPayService(
                $company->iata_client_id,
                $company->iata_client_secret
            );

            $data = $service->getWalletBalanceByCompany($company->iata_code, 'KWD');

            Log::info('IATA wallet data retrieved for company ID ' . $company->id . ': ' . json_encode($data));

            $wallets = collect($data['wallets'] ?? [])->where('status', 'OPEN')->values();
            $iataBalance = $wallets->sum('balance');
            $walletName = $wallets->pluck('name')->join(', ');

        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        Log::info('Returning data' , [
            'wallets' => $wallets,
            'iataBalance' => $iataBalance,
            'iataWalletName' => $walletName,
            'iataErrorMessage' => $error,
        ]);

        return [
            'wallets' => $wallets,
            'iataBalance' => $iataBalance,
            'iataWalletName' => $walletName,
            'iataErrorMessage' => $error,
        ];
    }
}