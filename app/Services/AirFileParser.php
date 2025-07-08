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
        
        $returnData = [
            'price' => $data['price'],
            'exchange_currency' => $data['exchange_currency'],
            'original_price' => $data['original_price'],
            'original_currency' => $data['original_currency'],
            'total' => $data['total'],
            'status' => $data['status'],
            'agent_name' => $data['agent_name'],
            'agent_email' => $data['agent_email'],
            'agent_amadeus_id' => $data['agent_amadeus_id'],
            'created_by' => $data['created_by'],
            'issued_by' => $data['issued_by'],
        ];
        return $returnData;
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
     * Extract status from AMD line or K line format
     * Look for VOID, RF (refund), FO (reissued), EMD indicators
     * Also check K line format for refund indicators (KN-, KS-)
     */
    private function extractStatus()
    {
        // Check for refund format in K line (KN- or KS- indicates refund)
        if ($this->findLine('/^K[NS]-/')) {
            return 'refund';
        }
        
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
     * Format: 
     * Regular: K-FKWD90.000     ;;;;;;;;;;;;KWD143.400    ;;;
     * Refund: KN-IKWD129.000    ;;;;;;;;;;;;KWD227.750    ;;;
     * Currency Exchange: K-FAED1300 ;KWD109.000 ;;;;;;;;;;;KWD150.900 ;
     */
    private function extractPrice()
    {
        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)\s*;*\s*([A-Z]{3})([\d.]+)/');
        if ($match) {
            // For refunds, determine if there's currency exchange
            if ($this->hasCurrencyExchange()) {
                // 3-pair format: return the final total (6th position)
                $exchangeMatch = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
                if ($exchangeMatch) {
                    return (float) $exchangeMatch[6];
                }
            }
            // 2-pair format: return the second amount
            return (float) $match[4];
        }
        
        // Check for regular format (K-F)
        if ($this->hasCurrencyExchange()) {
            // 3-pair format: K-FAED1300 ;KWD109.000 ;;;;;;;;;;;KWD150.900 ;
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[4]; // Final total amount (KWD150.900)
            }
        } else {
            // 2-pair format: K-FKWD66.000 ;;;;;;;;;;;;KWD194.300 ;;;
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[2]; // Total amount (KWD194.300)
            }
        }
        
        // Fallback: Look for any price pattern on K line (base fare only)
        $match = $this->findLine('/^K[NS]?-[FI]?([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // Base fare if no total found
        }
        
        return 0.0;
    }
    
    /**
     * Extract exchange currency from K line
     * Supports both regular (K-F) and refund (KN-/KS-) formats
     * Handles both currency exchange and same-currency scenarios
     */
    private function extractExchangeCurrency()
    {
        if ($this->hasCurrencyExchange()) {
            // 3-pair format with currency exchange - return the final currency
            $match = $this->findLine('/^K[NS]?-[FI]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[5]; // Final currency (KWD in exchange example)
            }
        } else {
            // 2-pair format - return the second currency
            // Check for refund format first (KN- or KS-)
            $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[3]; // Second currency (KWD in refund example)
            }
            
            // Check for regular format (K-F)
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[3]; // Second currency (KWD in regular example)
            }
        }
        
        // Fallback: Look for any currency on K line
        $match = $this->findLine('/^K[NS]?-[FI]?([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency as fallback
        }
        
        return 'KWD'; // Default currency
    }
    
    /**
     * Extract original price (base fare from K line)
     * Supports both regular and refund formats
     * Returns the first amount in the K-line regardless of currency exchange
     */
    private function extractOriginalPrice()
    {
        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // First amount (base fare)
        }
        
        // Check for regular format (K-F)
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // First amount (base fare)
        }
        
        return null;
    }
    
    /**
     * Extract original currency from K line
     * Supports both regular and refund formats
     * Returns the first currency in the K-line regardless of currency exchange
     */
    private function extractOriginalCurrency()
    {
        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency
        }
        
        // Check for regular format (K-F)
        $match = $this->findLine('/^K-F([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency
        }
        
        return 'KWD'; // Default currency
    }
    
    /**
     * Extract total amount from K line
     * The total is explicitly mentioned in the K line after multiple semicolons
     * Supports both regular and refund formats, and handles currency exchange
     * Example: K-FKWD135.000    ;;;;;;;;;;;;KWD172.850    ;;;
     *          KN-IKWD129.000   ;;;;;;;;;;;;KWD227.750    ;;;
     *          K-FAED1300       ;KWD109.000    ;;;;;;;;;;;KWD150.900    ;0.08368109 ;;
     */
    private function extractTotal()
    {
        // First try to get explicit total from refund format (KN- or KS-)
        if ($this->hasCurrencyExchange()) {
            // 3-pair format with currency exchange
            $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[6]; // Final total fare
            }
        } else {
            // 2-pair format without currency exchange
            $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[4]; // Total fare
            }
        }
        
        // Try regular format (K-F)
        if ($this->hasCurrencyExchange()) {
            // 3-pair format: K-FAED1300 ;KWD109.000 ;;;;;;;;;;;KWD150.900 ;
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[6]; // Final total fare (KWD150.900)
            }
        } else {
            // 2-pair format: K-FKWD66.000 ;;;;;;;;;;;;KWD194.300 ;;;
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[4]; // Total fare (KWD194.300)
            }
        }
        
        // If no explicit total found in K line, fallback to base fare
        $baseFare = $this->extractPrice();
        if ($baseFare !== null) {
            return $baseFare;
        }
        
        return 0.0;
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
     * Extract tax amount from KFTF line, KNTI/KSTI line, or TAX line
     * Supports both regular and refund formats
     * KFTF line format: KFTF; KWD24.000   YQ AC; KWD4.000    YR VB; etc.
     * KNTI line format: KNTI; KWD80.000   YR VA; KWD1.000    GZ SE; etc.
     * TAX line format: TAX-KWD24.000   YQ ;KWD4.000    YR ;KWD25.400   XT ;
     */
    private function extractTax()
    {
        $totalTax = 0.0;
        
        // First try refund format (KNTI or KSTI line)
        $match = $this->findLine('/^K[NS]TI;(.+)/');
        if ($match) {
            $taxString = $match[1];
            // Parse individual tax components: KWD80.000   YR VA; KWD1.000    GZ SE;
            if (preg_match_all('/([A-Z]{3})([\d.]+)\s+([A-Z0-9]{2})\s*[A-Z]*/', $taxString, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $taxMatch) {
                    $totalTax += (float) $taxMatch[2];
                }
                return $totalTax;
            }
        }
        
        // Try regular format (KFTF line)
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
     * Extract taxes record from KFTF line, KNTI/KSTI line, or KRF line
     * Supports both regular and refund formats
     */
    private function extractTaxesRecord()
    {
        // Try refund format first (KNTI or KSTI line)
        $match = $this->findLine('/^K[NS]TI;(.+)/');
        if ($match) {
            return $match[1];
        }
        
        // Try regular format (KFTF line)
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
        // Look for E- prefix followed by email
        $match = $this->findLine('/E-([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/');
        if ($match) {
            return strtolower($match[1]); // Return email without E- prefix
        }
        
        // Fallback: Look for any email pattern without prefix
        $match = $this->findLine('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/');
        if ($match) {
            return strtolower($match[1]);
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
     * Supports both regular and refund formats
     */
    private function extractBaseFare()
    {
        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // Base fare amount
        }
        
        // Check for regular format (K-F)
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // Base fare amount
        }
        
        return 0.0;
    }
    
    /**
     * Extract total fare specifically from K line  
     * This is the fare including taxes and fees
     * Supports both regular and refund formats
     */
    private function extractTotalFare()
    {
        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[I]?([A-Z]{3})([\d.]+)\s*;;;;;;;;;;;;([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[4]; // Total fare amount
        }
        
        // Check for regular format (K-F)
        $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;;;;;;;;;;;;([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[4]; // Total fare amount
        }
        
        return $this->extractBaseFare(); // Fallback to base fare
    }
    
    /**
     * Check if the K-line has currency exchange (3 currency/amount pairs vs 2)
     * Currency exchange format: K-FAED1300 ;KWD109.000 ;;;;;;;;;;;KWD150.900 ;
     * Same currency format: K-FKWD66.000 ;;;;;;;;;;;;KWD194.300 ;;;
     */
    private function hasCurrencyExchange()
    {
        // Look for 3-pair pattern (currency exchange)
        $threePairMatch = $this->findLine('/^K[NS]?-[FI]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
        if ($threePairMatch) {
            // Check if first and second currencies are different (indicating exchange)
            return $threePairMatch[1] !== $threePairMatch[3];
        }
        
        return false;
    }
}