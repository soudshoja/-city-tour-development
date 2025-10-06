<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class TextFileProcessor
{
    public function readAndExtractData(string $filePath)
    {
        // Check if the file exists
        if (!File::exists($filePath)) {
            throw new \Exception("File not found at path: $filePath");
        }

        // Read file contents
        $fileContent = File::get($filePath);
        // $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'auto');

        // Process each line if the file contains multiple lines
        $data = $this->extractData($fileContent);

        return $data;
    }


    function extractData($text)
    {
        $data = [];

        preg_match('/AGENT:\s*(\d+)/', $text, $agentId);
        preg_match('/NAME:\s*([A-Z\/]+ MR)/', $text, $customerName);
        preg_match('/TICKET NUMBER\s*:\s*ETKT\s*([\d\s]+)/', $text, $ticketNumber);
        preg_match('/BOOKING REF\s*:\s*AMADEUS:\s*([A-Z0-9]+),/', $text, $bookingRef);
        preg_match('/ISSUING AIRLINE\s*:\s*([\w\s]+)/', $text, $issuingAirline);
        preg_match('/PAYMENT\s*:\s*(\w+)/', $text, $paymentMethod);
        preg_match('/AIR FARE\s*:\s*KWD\s*([\d.]+)/', $text, $fare);
        preg_match('/TOTAL\s*:\s*KWD\s*([\d.]+)/', $text, $totalCost);
        
        // Outbound flight details
        preg_match('/KUWAIT\s+KUWAIT\s+KU\s+(\d+)\s+(\w)\s+(\d+\w+)\s+(\d{4})\s+.*?ARRIVAL TIME:\s*(\d{4}).*?ARRIVAL DATE:\s*(\d+\w+)/s', $text, $outboundFlight);
        
        // Return flight details
        preg_match('/LONDON HEATHROW\s+KU\s+(\d+)\s+(\w)\s+(\d+\w+)\s+(\d{4})\s+.*?ARRIVAL TIME:\s*(\d{4}).*?ARRIVAL DATE:\s*(\d+\w+)/s', $text, $returnFlight);
        
        // Populate data array with captured details
        $data['Agent ID'] = $agentId[1] ?? 'N/A';
        $data['Customer Name'] = $customerName[1] ?? 'N/A';
        $data['Ticket Number'] = $ticketNumber[1] ?? 'N/A';
        $data['Booking Reference'] = $bookingRef[1] ?? 'N/A';
        $data['Issuing Airline'] = $issuingAirline[1] ?? 'N/A';
        $data['Payment Method'] = $paymentMethod[1] ?? 'N/A';
        $data['Fare'] = $fare[1] ?? 'N/A';
        $data['Total Cost'] = $totalCost[1] ?? 'N/A';
        
        $data['Flight Details'] = [
            'Outbound' => [
                'Flight Number' => $outboundFlight[1] ?? 'N/A',
                'Class' => $outboundFlight[2] ?? 'N/A',
                'Date' => $outboundFlight[3] ?? 'N/A',
                'Departure Time' => $outboundFlight[4] ?? 'N/A',
                'Arrival Time' => $outboundFlight[5] ?? 'N/A',
                'Arrival Date' => $outboundFlight[6] ?? 'N/A'
            ],
            'Return' => [
                'Flight Number' => $returnFlight[1] ?? 'N/A',
                'Class' => $returnFlight[2] ?? 'N/A',
                'Date' => $returnFlight[3] ?? 'N/A',
                'Departure Time' => $returnFlight[4] ?? 'N/A',
                'Arrival Time' => $returnFlight[5] ?? 'N/A',
                'Arrival Date' => $returnFlight[6] ?? 'N/A'
            ]
        ];

        return $data;
    }
}
