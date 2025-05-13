<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClearTaskRelatedSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('tasks')->truncate();
        DB::table('task_flight_details')->truncate();
        DB::table('task_hotel_details')->truncate();
        
        DB::table('invoice_details')->truncate();
        DB::table('invoice_partials')->truncate();
        DB::table('invoice_sequence')->truncate();

        // $invoices = Invoice::with('client')
        //     ->where('is_client_credit', 1)
        //     ->where('status', 'paid')
        //     ->get();

        // foreach ($invoices as $invoice) {
        //     $invoice->client->credit += $invoice->amount;
        //     $invoice->client->save();
        //     $invoice->delete();            
        // }

        $clients = Client::all();
        foreach ($clients as $client) {
            $client->credit = 0;
            $client->save();
        }

        DB::table('invoices')->truncate();
        DB::table('payments')->truncate();
        DB::table('transactions')->truncate();
        DB::table('journal_entries')->truncate();
        DB::table('invoice_details')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
