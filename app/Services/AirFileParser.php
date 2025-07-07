<?php

namespace App\Services;

use Carbon\Carbon;

class AirFileParser
{
    private $content;
    private $lines;
    
    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        
        $this->content = file_get_contents($filePath);
        $this->lines = explode("\n", $this->content);
    }
    
    /**
     * Parse the AIR file and extract task schema data
     */
    public function parseTaskSchema()
    {
        $data = [
            'additional_info' => $this->extractAdditionalInfo(),
            'ticket_number' => $this->extractTicketNumber(),
            'gds_reference' => $this->extractGdsReference(),
            'airline_reference' => $this->extractAirlineReference(),
            'status' => $this->extractStatus(),
            'supplier_status' => $this->extractStatus(), // Same as status
            'refund_date' => $this->extractRefundDate(),
            'price' => $this->extractPrice(),
            'exchange_currency' => $this->extractExchangeCurrency(),
            'original_price' => $this->extractOriginalPrice(),
            'original_currency' => $this->extractOriginalCurrency(),
            'total' => $this->extractTotal(),
            'surcharge' => $this->extractSurcharge(),
            'penalty_fee' => $this->extractPenaltyFee(),
            'tax' => $this->extractTax(),
            'taxes_record' => $this->extractTaxesRecord(),
            'refund_charge' => $this->extractRefundCharge(),
            'reference' => $this->extractReference(),
            'created_by' => $this->extractCreatedBy(),
            'issued_by' => $this->extractIssuedBy(),
            'type' => 'flight', // Always flight for AIR files
            'agent_name' => $this->extractAgentName(),
            'agent_email' => $this->extractAgentEmail(),
            'agent_amadeus_id' => $this->extractAgentAmadeusId(),
            'client_name' => $this->extractClientName(),
            'supplier_name' => $this->extractSupplierName(),
            'supplier_country' => $this->extractSupplierCountry(),
            'cancellation_policy' => $this->extractCancellationPolicy(),
            'venue' => $this->extractVenue(),
            'created_at' => $this->extractCreatedAt(),
            'task_flight_details' => $this->parseFlightDetails(),
        ];
        
        return $data;
    }
    
    /**
     * Parse flight details from the AIR file
     */
    public function parseFlightDetails()
    {
        return [
            'farebase' => $this->extractFarebase(),
            'departure_time' => $this->extractDepartureTime(),
            'country_id_from' => $this->extractDepartureCountry(),
            'airport_from' => $this->extractDepartureAirport(),
            'terminal_from' => $this->extractDepartureTerminal(),
            'arrival_time' => $this->extractArrivalTime(),
            'duration_time' => $this->extractDurationTime(),
            'country_id_to' => $this->extractArrivalCountry(),
            'airport_to' => $this->extractArrivalAirport(),
            'terminal_to' => $this->extractArrivalTerminal(),
            'airline_id' => $this->extractAirlineName(),
            'flight_number' => $this->extractFlightNumber(),
            'class_type' => $this->extractClassType(),
            'baggage_allowed' => $this->extractBaggageAllowed(),
            'equipment' => $this->extractEquipment(),
            'ticket_number' => $this->extractTicketNumber(),
            'flight_meal' => $this->extractFlightMeal(),
            'seat_no' => $this->extractSeatNumber(),
        ];
    }
    
    /**
     * Find a line that matches a pattern
     */
    private function findLine($pattern)
    {
        foreach ($this->lines as $line) {
            if (preg_match($pattern, $line, $matches)) {
                return $matches;
            }
        }
        return null;
    }
    
    /**
     * Find all lines that match a pattern
     */
    private function findLines($pattern)
    {
        $results = [];
        foreach ($this->lines as $line) {
            if (preg_match($pattern, $line, $matches)) {
                $results[] = $matches;
            }
        }
        return $results;
    }
    
    /**
     * Extract ticket number from T-K line
     * Format: T-K229-2833184683 -> take last 10 digits
     */
    private function extractTicketNumber()
    {
        $match = $this->findLine('/^T-K\d+-(\d+)$/');
        if ($match) {
            $fullNumber = $match[1];
            return substr($fullNumber, -10); // Last 10 digits
        }
        return '';
    }
    
    /**
     * Extract GDS reference from MUC1A line
     * Format: MUC1A 7GS6BK020 -> take 6 characters after MUC1A
     */
    private function extractGdsReference()
    {
        $match = $this->findLine('/^MUC1A\s+([A-Z0-9]{6})/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract airline reference (last 6 characters from GDS line)
     */
    private function extractAirlineReference()
    {
        $match = $this->findLine('/^MUC1A\s+[A-Z0-9]+.*?([A-Z0-9]{6})$/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract status from AMD line
     * Look for VOID, RF (refund), FO (reissued), EMD indicators
     */
    private function extractStatus()
    {
        // Check for VOID
        if ($this->findLine('/VOID/')) {
            return 'void';
        }
        
        // Check for refund indicators
        if ($this->findLine('/RF/')) {
            return 'refund';
        }
        
        // Check for reissued (FO + original ticket)
        if ($this->findLine('/FO/')) {
            return 'reissued';
        }
        
        // Check for EMD
        if ($this->findLine('/EMD/')) {
            return 'emd';
        }
        
        // Default to issued
        return 'issued';
    }
    
    /**
     * Extract refund date if status is refund
     */
    private function extractRefundDate()
    {
        if ($this->extractStatus() === 'refund') {
            // Look for date patterns in the file
            $match = $this->findLine('/(\d{2}[A-Z]{3}\d{2})/');
            if ($match) {
                try {
                    $date = Carbon::createFromFormat('dMy', $match[1]);
                    return $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // If parsing fails, return null
                }
            }
        }
        return null;
    }
    
    /**
     * Extract price from K line (fare line)
     * Format: K-FKWD90.000     ;;;;;;;;;;;;KWD143.400    ;;;
     */
    private function extractPrice()
    {
        // Look specifically for K line with fare information
        // Pattern handles: K-F[CURRENCY][AMOUNT][SPACES/SEMICOLONS][CURRENCY][AMOUNT]
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;*\s*([A-Z]{3})([\d.]+)/');
        if ($match) {
            // Return the second amount (total fare including taxes)
            return (float) $match[4]; // KWD143.400 in your example
        }
        
        // Fallback: Look for any price pattern on K line (base fare only)
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // Base fare if no total found
        }
        
        return 0.0;
    }
    
    /**
     * Extract exchange currency from K line
     */
    private function extractExchangeCurrency()
    {
        // Look for currency on K line
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;;;;;;;;;;;;([A-Z]{3})([\d.]+)/');
        if ($match) {
            return $match[3]; // Second currency (KWD in your example)
        }
        
        $match = $this->findLine('/^K-F([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency as fallback
        }
        
        return 'KWD'; // Default currency
    }
    
    /**
     * Extract original price (base fare from K line)
     * Format: K-FKWD90.000 (this is the base fare before taxes)
     */
    private function extractOriginalPrice()
    {
        // Look for base fare on K line
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // KWD90.000 in your example
        }
        
        return null;
    }
    
    /**
     * Extract original currency from K line
     */
    private function extractOriginalCurrency()
    {
        // Look for base fare currency on K line
        $match = $this->findLine('/^K-F([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency (KWD in your example)
        }
        
        return 'KWD'; // Default currency
    }
    
    /**
     * Extract total amount from K line or TAX line
     */
    private function extractTotal()
    {
        // First try to get total from K line (total fare including taxes)
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;;;;;;;;;;;;([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[4]; // Total fare (KWD143.400 in your example)
        }
        
        // Look for explicit TOTAL line
        $match = $this->findLine('/TOTAL\s+([A-Z]{3})\s+([\d.]+)/i');
        if ($match) {
            return (float) $match[2];
        }
        
        // If no explicit total, calculate: base fare + taxes
        $baseFare = $this->extractOriginalPrice();
        $taxes = $this->extractTax();
        
        if ($baseFare !== null && $taxes > 0) {
            return $baseFare + $taxes;
        }
        
        // Fallback to price
        return $this->extractPrice();
    }
    
    /**
     * Extract surcharge
     */
    private function extractSurcharge()
    {
        $match = $this->findLine('/SURCHARGE\s+([\d.]+)/i');
        if ($match) {
            return (float) $match[1];
        }
        return 0.0;
    }
    
    /**
     * Extract penalty fee
     */
    private function extractPenaltyFee()
    {
        $match = $this->findLine('/PENALTY\s+([\d.]+)/i');
        if ($match) {
            return (float) $match[1];
        }
        return 0.0;
    }
    
    /**
     * Extract tax amount from KFTF line or TAX line
     * KFTF line format: KFTF; KWD24.000   YQ AC; KWD4.000    YR VB; etc.
     * TAX line format: TAX-KWD24.000   YQ ;KWD4.000    YR ;KWD25.400   XT ;
     */
    private function extractTax()
    {
        $totalTax = 0.0;
        
        // First try KFTF line (Kuwait Airways format)
        $match = $this->findLine('/^KFTF;(.+)/');
        if ($match) {
            $taxString = $match[1];
            // Parse individual tax components: KWD24.000   YQ AC; KWD4.000    YR VB;
            if (preg_match_all('/([A-Z]{3})([\d.]+)\s+([A-Z0-9]{2})\s*[A-Z]*/', $taxString, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $taxMatch) {
                    $totalTax += (float) $taxMatch[2];
                }
                return $totalTax;
            }
        }
        
        // Try TAX line format
        $match = $this->findLine('/^TAX-(.+)/');
        if ($match) {
            $taxString = $match[1];
            // Parse: KWD24.000   YQ ;KWD4.000    YR ;KWD25.400   XT ;
            if (preg_match_all('/([A-Z]{3})([\d.]+)\s+([A-Z0-9]{2})/', $taxString, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $taxMatch) {
                    $totalTax += (float) $taxMatch[2];
                }
                return $totalTax;
            }
        }
        
        // Fallback: Sum all tax components from KRF line
        $taxesRecord = $this->extractTaxesRecord();
        if ($taxesRecord) {
            $taxes = explode(',', $taxesRecord);
            foreach ($taxes as $tax) {
                if (strpos($tax, ':') !== false) {
                    list($code, $amount) = explode(':', $tax);
                    $totalTax += (float) $amount;
                }
            }
            return $totalTax;
        }
        
        return 0.0;
    }
    
    /**
     * Extract taxes record from KFTF line or KRF line
     */
    private function extractTaxesRecord()
    {
        // Try KFTF line first (Kuwait Airways format)
        $match = $this->findLine('/^KFTF;(.+)/');
        if ($match) {
            return $match[1];
        }
        
        // Try TAX line
        $match = $this->findLine('/^TAX-(.+)/');
        if ($match) {
            return $match[1];
        }
        
        // Fallback to KRF line
        $match = $this->findLine('/KRF:(.+)/');
        if ($match) {
            return $match[1];
        }
        
        return '';
    }
    
    /**
     * Extract refund charge (non-refundable taxes)
     */
    private function extractRefundCharge()
    {
        $taxesRecord = $this->extractTaxesRecord();
        if ($taxesRecord) {
            $total = 0.0;
            $taxes = explode(',', $taxesRecord);
            foreach ($taxes as $tax) {
                if (strpos($tax, ':') !== false) {
                    list($code, $amount) = explode(':', $tax);
                    // YQ, YR, YX are typically non-refundable
                    if (in_array($code, ['YQ', 'YR', 'YX'])) {
                        $total += (float) $amount;
                    }
                }
            }
            return $total;
        }
        return 0.0;
    }
    
    /**
     * Extract reference (same as ticket number)
     */
    private function extractReference()
    {
        return $this->extractTicketNumber();
    }
    
    /**
     * Extract created by (first GDS office ID)
     */
    private function extractCreatedBy()
    {
        $match = $this->findLine('/^MUC1A\s+[A-Z0-9]+;\d+;([A-Z0-9]+);/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract issued by (last GDS office ID)
     */
    private function extractIssuedBy()
    {
        $match = $this->findLine('/^MUC1A.*?;([A-Z0-9]+);[^;]*$/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract agent name
     */
    private function extractAgentName()
    {
        // Look for agent name in contact information
        $match = $this->findLine('/COMO TRAVEL AND TOURISM/');
        if ($match) {
            return 'COMO TRAVEL AND TOURISM';
        }
        return '';
    }
    
    /**
     * Extract agent email
     */
    private function extractAgentEmail()
    {
        $match = $this->findLine('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract agent Amadeus ID from C line
     */
    private function extractAgentAmadeusId()
    {
        $match = $this->findLine('/C-\d+\/\s*\d+[A-Z]+-(\d{4}[A-Z]{2})[A-Z]+-/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract client name from I line
     */
    private function extractClientName()
    {
        $match = $this->findLine('/I-\d+;\d+([^;]+);/');
        if ($match) {
            return trim($match[1]);
        }
        return '';
    }
    
    /**
     * Extract supplier name
     */
    private function extractSupplierName()
    {
        return 'Amadeus'; // Default for AIR files
    }
    
    /**
     * Extract supplier country
     */
    private function extractSupplierCountry()
    {
        return 'Kuwait'; // Default based on office codes
    }
    
    /**
     * Extract cancellation policy
     */
    private function extractCancellationPolicy()
    {
        if ($this->extractStatus() === 'void') {
            return 'Non-refundable after issue';
        }
        return '';
    }
    
    /**
     * Extract venue (airports)
     */
    private function extractVenue()
    {
        $departure = $this->extractDepartureAirport();
        $arrival = $this->extractArrivalAirport();
        
        if ($departure && $arrival) {
            return "{$departure} to {$arrival}";
        }
        
        return 'Kuwait International Airport'; // Default
    }
    
    /**
     * Extract created at date
     */
    private function extractCreatedAt()
    {
        // Look for date patterns like TKOK12FEB
        $match = $this->findLine('/T[A-Z]{3}(\d{2}[A-Z]{3})/');
        if ($match) {
            try {
                $date = Carbon::createFromFormat('dM', $match[1]);
                $date->year = Carbon::now()->year; // Assume current year
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, return null
            }
        }
        return null;
    }
    
    /**
     * Extract additional info
     */
    private function extractAdditionalInfo()
    {
        $status = $this->extractStatus();
        switch ($status) {
            case 'void':
                return 'Voided ticket';
            case 'refund':
                return 'Refunded ticket';
            case 'reissued':
                return 'Reissued ticket';
            case 'emd':
                return 'EMD document';
            default:
                return 'Flight ticket';
        }
    }
    
    // Flight Details Extraction Methods
    
    private function extractFarebase()
    {
        return $this->extractPrice();
    }
    
    private function extractDepartureTime()
    {
        // Look for departure time patterns
        $match = $this->findLine('/(\d{4})\s+(\d{4})/');
        if ($match) {
            $time = $match[1];
            $hour = substr($time, 0, 2);
            $minute = substr($time, 2, 2);
            return Carbon::today()->setTime($hour, $minute)->format('Y-m-d H:i:s');
        }
        return null;
    }
    
    private function extractDepartureCountry()
    {
        return 'Kuwait'; // Default based on KWI airport
    }
    
    private function extractDepartureAirport()
    {
        // Look for airport codes in flight segments
        $match = $this->findLine('/([A-Z]{3})\s+([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First airport code
        }
        return 'KWI'; // Default
    }
    
    private function extractDepartureTerminal()
    {
        $match = $this->findLine('/TERMINAL\s+(\d+)/i');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    private function extractArrivalTime()
    {
        $match = $this->findLine('/(\d{4})\s+(\d{4})/');
        if ($match) {
            $time = $match[2];
            $hour = substr($time, 0, 2);
            $minute = substr($time, 2, 2);
            return Carbon::today()->setTime($hour, $minute)->format('Y-m-d H:i:s');
        }
        return null;
    }
    
    private function extractDurationTime()
    {
        // Calculate from departure and arrival times
        $departure = $this->extractDepartureTime();
        $arrival = $this->extractArrivalTime();
        
        if ($departure && $arrival) {
            $dep = Carbon::parse($departure);
            $arr = Carbon::parse($arrival);
            $diff = $dep->diff($arr);
            return $diff->format('%hh %im');
        }
        
        return '';
    }
    
    private function extractArrivalCountry()
    {
        // Default based on common routes
        return 'United Arab Emirates';
    }
    
    private function extractArrivalAirport()
    {
        $match = $this->findLine('/([A-Z]{3})\s+([A-Z]{3})/');
        if ($match) {
            return $match[2]; // Second airport code
        }
        return 'DXB'; // Default
    }
    
    private function extractArrivalTerminal()
    {
        return '';
    }
    
    private function extractAirlineName()
    {
        // Look for airline codes like KU
        $match = $this->findLine('/([A-Z]{2})\s+\d+/');
        if ($match) {
            $code = $match[1];
            switch ($code) {
                case 'KU':
                    return 'Kuwait Airways';
                case 'EK':
                    return 'Emirates';
                case 'QR':
                    return 'Qatar Airways';
                default:
                    return $code . ' Airlines';
            }
        }
        return 'Kuwait Airways'; // Default
    }
    
    private function extractFlightNumber()
    {
        $match = $this->findLine('/([A-Z]{2})\s*(\d+)/');
        if ($match) {
            return $match[1] . '-' . $match[2];
        }
        return '';
    }
    
    private function extractClassType()
    {
        // Look for class codes
        $match = $this->findLine('/\s([YFCJ])\s/');
        if ($match) {
            $class = $match[1];
            switch ($class) {
                case 'Y':
                    return 'economy';
                case 'C':
                    return 'business';
                case 'F':
                    return 'first';
                case 'J':
                    return 'business';
                default:
                    return 'economy';
            }
        }
        return 'economy';
    }
    
    private function extractBaggageAllowed()
    {
        $match = $this->findLine('/(\d+)KG/i');
        if ($match) {
            return $match[1] . 'kg';
        }
        return '30kg'; // Default
    }
    
    private function extractEquipment()
    {
        $match = $this->findLine('/(A\d{3}|B\d{3})/');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    private function extractFlightMeal()
    {
        $match = $this->findLine('/MEAL\s*:\s*([A-Z]+)/i');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    private function extractSeatNumber()
    {
        $match = $this->findLine('/SEAT\s*:\s*(\d+[A-Z])/i');
        if ($match) {
            return $match[1];
        }
        return '';
    }
    
    /**
     * Extract base fare specifically from K line
     * This is the fare before taxes and fees
     */
    private function extractBaseFare()
    {
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // Base fare amount
        }
        
        return 0.0;
    }
    
    /**
     * Extract total fare specifically from K line  
     * This is the fare including taxes and fees
     */
    private function extractTotalFare()
    {
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;;;;;;;;;;;;([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[4]; // Total fare amount
        }
        
        return $this->extractBaseFare(); // Fallback to base fare
    }
}