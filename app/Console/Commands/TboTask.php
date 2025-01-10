<?php

namespace App\Console\Commands;

use App\Http\Controllers\TBOController;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskHotelDetail;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Query\IndexHint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TboTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tbo-task';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        logger('TBO task is running');
       $tboController = new TBOController();

        $bookingDetailsToday = $tboController->bookingDetailByDate(
            new Request([
                'startDate' => '2025-01-04',
                'endDate' => '2025-01-04'
            ])
        );

        if(isset($bookingDetailsToday['error']))
        {
            logger('TBO Task Error: '. $bookingDetailsToday['error']);
            return;
        }


        logger('TBO Task: ', $bookingDetailsToday);

        foreach($bookingDetailsToday as $booking)
        {
            $agent = Agent::where('tbo_reference', $booking['ClientReferenceNumber'])->first();

            if(!$agent){
                logger('TBO Task Error: Client Reference Number does not register with any agent. Client Reference Number: ' . $booking['ClientReferenceNumber']);
                return;
            }

            $checkInDate = new \DateTime($booking['CheckInDate']);
            $checkOutDate = new \DateTime($booking['CheckOutDate']);
            $interval = $checkInDate->diff($checkOutDate);
            $hours = $interval->days * 24 + $interval->h;

            $details = $tboController->bookingDetail(
                new Request([
                    'confirmationNumber' => $booking['ConfirmationNo']
                ])
            );

            logger('TBO Task Details: ', $details);

            if(!isset($details['Rooms'])){
                logger('TBO Task Error: No rooms found');
                return;
            }

            if(count($details['Rooms']) < 1){
                logger('TBO Task Error: No rooms found');
                return;
            }

            foreach($details['Rooms'] as $room){
                
                if(!isset($room['CustomerDetails'])){
                    logger('TBO Task Error: No customer details found');
                    return;
                }

                if(count($room['CustomerDetails']) < 1){
                    logger('TBO Task Error: No customer details found');
                    return;
                }

                foreach($room['CustomerDetails'][0]['CustomerNames'] as $key => $customer){
                    $client = Client::updateOrCreate([
                        'name' => $customer['FirstName'] . ' ' . $customer['LastName'],
                    ]);

                    if(!$client){
                        logger('TBO Task Error: Client failed to create');
                        return;
                    }

                    logger('TBO Task Client: '. $client->name . ' created');

                    if($key == 0 ){
                        $leaderCustomer = $client;

                        logger('TBO Task : Leader Customer: '. $leaderCustomer->name);
                    }
                }
                try{
                    $task = Task::create([
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'type' => 'hotel',
                        'status' => strtolower($booking['BookingStatus']),
                        'client_name' => $leaderCustomer->name,
                        'reference' => null,
                        'duration' => $hours,
                        'payment_type ' => null,
                        'price' => $room['TotalFare'],
                        'tax' => $room['TotalTax'],
                        'surcharge' => null,
                        'total' => $room['TotalFare'],
                        'cancellation_policy' => json_encode($room['CancelPolicies']),
                        'additional_info' => null,
                        'supplier_id' => 1,
                        'venue' =>  $details['HotelDetails']['City'],
                        'invoice_price' => null,
                        'voucher_status' => (string)$details['VoucherStatus'],
                    ]);
                } catch(Exception $e){
                    logger('TBO Task Error: ' . $e->getMessage());
                    return;
                }

                try{
                    $hotelRating = 0.0;

                    switch($details['HotelDetails']['Rating']) {
                        case 'OneStar':
                            $hotelRating = 1.0;
                            break;
                        case 'TwoStar':
                            $hotelRating = 2.0;
                            break;
                        case 'ThreeStar':
                            $hotelRating = 3.0;
                            break;
                        case 'FourStar':
                            $hotelRating = 4.0;
                            break;
                        case 'All':
                            $hotelRating = 5.0;
                            break;
                        default:
                            $hotelRating = 0.0;
                            break;
                    }

                    $taskHotelDetails = TaskHotelDetail::create([
                        'task_id' => $task->id,
                        'hotel_id' => 1,
                        'booking_time' => Date('Y-m-d H:i:s', strtotime($booking['BookingDate'])),
                        'check_In' => Date('Y-m-d H:i:s', strtotime($booking['CheckInDate'])),
                        'check_out' => Date('Y-m-d H:i:s', strtotime($booking['CheckOutDate'])),
                        'room_amount' => 1,
                        'room_type' => json_encode($room['Name']),
                        'room_details' => $room['Inclusion'],
                        'room_promotion' => $room['RoomPromotion'],
                        'rate' => $hotelRating,
                        'meal_type' => $room['MealType'],
                        'is_refundable' => $room['IsRefundable'],
                        'supplements' => json_encode($room['Supplements']),
                    ]);

                    logger('task with id: ' . $task->id . ' and task hotel details with id: ' . $taskHotelDetails->id . ' has been created');

                } catch(Exception $e){
                    logger('TBO Task Error: ' . $e->getMessage());
                    Task::find($task->id)->delete();
                }
            }
        }

        logger('TBO task is done');
    }
}
