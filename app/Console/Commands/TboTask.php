<?php

namespace App\Console\Commands;

use App\Http\Controllers\TBOController;
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
                'startDate' => '2025-01-05',
                'endDate' => '2025-01-05'
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

            try {
                $task = Task::create([
                    'client_id' => 34,
                    'agent_id' => 16,
                    'type' => 'hotel',
                    'status' => strtolower($booking['BookingStatus']),
                    'client_name' => $details['Rooms'][0]['CustomerDetails'][0]['CustomerNames'][0]['FirstName'] . ' ' . $details['Rooms'][0]['CustomerDetails'][0]['CustomerNames'][0]['LastName'],
                    'reference' => null,
                    'duration' => $hours,
                    'payment_type ' => null,
                    'price' => $booking['BookingPrice'],
                    'tax' => $details['Rooms'][0]['TotalTax'],
                    'surcharge' => null,
                    'total' => $details['Rooms'][0]['TotalFare'],
                    'cancellation_policy' => json_encode($details['Rooms'][0]['CancelPolicies']),
                    'additional_info' => null,
                    'supplier_id' => 1,
                    'venue' =>  $details['HotelDetails']['City'],
                    'invoice_price' => null,
                    'voucher_status' => (string)$details['VoucherStatus'],
                ]);
            } catch (Exception $e) {
                logger('TBO Task Error: ' . $e->getMessage());
                return;
            }
           
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

            try {
                $taskHotelDetails = TaskHotelDetail::create([
                    'task_id' => $task->id,
                    'hotel_id' => 1,
                    'booking_time' => Date('Y-m-d H:i:s', strtotime($booking['BookingDate'])),
                    'check_In' => Date('Y-m-d H:i:s', strtotime($booking['CheckInDate'])),
                    'check_out' => Date('Y-m-d H:i:s', strtotime($booking['CheckOutDate'])),
                    'room_amount' => $details['NoOfRooms'],
                    'room_type' => json_encode($details['Rooms'][0]['Name']),
                    'room_details' => $details['Rooms'][0]['Inclusion'],
                    'room_promotion' => $details['Rooms'][0]['RoomPromotion'],
                    'rate' => $hotelRating,
                    'meal_type' => $details['Rooms'][0]['MealType'],
                    'is_refundable' => $details['Rooms'][0]['IsRefundable'],
                    'supplements' => json_encode($details['Rooms'][0]['Supplements']),
                ]);

                logger('task with id: ' . $task->id . ' and task hotel details with id: ' . $taskHotelDetails->id . ' has been created');

            } catch (Exception $e) {
                logger('TBO Task Error: ' . $e->getMessage());
                Task::find($task->id)->delete();
            }
        }

        logger('TBO task is done');
    }
}
