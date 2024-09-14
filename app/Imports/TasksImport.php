<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\Task;
use App\Models\Item;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
class TasksImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
if (!is_null($row['description'])) {

$existingItem = Item::where('item_id', $row['contract_id'])->first();
if (!$existingItem) {               // Create a new User
    $item = Item::create([
            'description'=> $row['description'],
            'item_status'=> 'active',
            'item_id'=> $row['contract_id'],
            'item_code'=> $row['contract_code'],
            'time_signed'=> $row['time_signed'],
            'client_email'=> $row['client_email'],
            'agent_email'=> $row['agent_email'],
            'total_price' => $row['total_price'],
            'payment_date' => $row['payment_date'],
            'paid'=> $row['paid'],
            'payment_time'=> $row['payment_time'],
            'payment_amount'=> $row['payment_amount'],
            'refunded' => $row['refunded'],
            'trip_name'=> $row['trip_name'],
            'trip_code'=> $row['trip_code']
        ]);
    }

$existingClient = Client::where('email', $row['client_email'])->first();

if (!$existingClient) {
    $client = Client::create([
        'name' => $row['client_name'], 
        'email' => $row['client_email'], 
        'status' => 'active', 
        'phone'  => $row['client_phone'],
                ]);
       }

   $existingTask = Task::where('task_id', $row['task_id'])->first();
        // Create a new company
   if (!$existingTask) {
        $task = Task::create([
            'description' => $row['description'],
            'task_id' => $row['task_id'],
            'task_type' => $row['task_type'],
            'item_id' => $row['contract_id'],
            'status' => $row['status'],
            'agent_email' => $row['agent_email'],
            'client_email' => $row['client_email'],
        ]);
      }
    }
  }
}