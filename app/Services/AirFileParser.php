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
     * Now returns an array of tasks for multiple passengers
     */
    public function parseTaskSchema()
    {
        $passengers = $this->extractAllPassengers();
        
        // If no passengers found, return single task with default data
        if (empty($passengers)) {
            $data = [
                'additional_info' => $this->extractAdditionalInfo(),
                'ticket_number' => $this->extractTicketNumber(),
                'gds_reference' => $this->extractGdsReference(),
                'airline_reference' => $this->extractAirlineReference(),
                'status' => $this->extractStatus(),
                'supplier_status' => $this->extractStatus(), // Same as status
                'refund_date' => $this->extractRefundDate(),
                'price' => $this->extractPrice(),
                'currency' => $this->extractExchangeCurrency(), // Primary currency (same as exchange_currency)
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
                'issued_date' => $this->extractIssuedDate(),
                'task_flight_details' => $this->parseFlightDetails(),
            ];
            
            return [$data];
        }
        
        // Create a task for each passenger
        $tasks = [];
        foreach ($passengers as $passenger) {
            $reference = substr($passenger['ticket_number'], -10); // Use last 10 digits of ticket number as reference
            $data = [
                'additional_info' => $this->extractAdditionalInfo(),
                'ticket_number' => $passenger['ticket_number'],
                'gds_reference' => $this->extractGdsReference(),
                'airline_reference' => $this->extractAirlineReference(),
                'status' => $this->extractStatus(),
                'supplier_status' => $this->extractStatus(), // Same as status
                'refund_date' => $this->extractRefundDate(),
                'price' => $this->extractStatus() === 'emd' ? $passenger['price'] : $this->extractPrice(),
                'currency' => $this->extractExchangeCurrency(), // Primary currency (same as exchange_currency)
                'exchange_currency' => $this->extractExchangeCurrency(),
                'original_price' => $this->extractOriginalPrice(),
                'original_currency' => $this->extractOriginalCurrency(),
                'total' => $this->extractStatus() === 'emd' ? $passenger['price'] : $this->extractTotal(),
                'surcharge' => $this->extractSurcharge(),
                'penalty_fee' => $this->extractPenaltyFee(),
                'tax' => $this->extractTax(),
                'taxes_record' => $this->extractTaxesRecord(),
                'refund_charge' => $this->extractRefundCharge(),
                'reference' => $reference,
                'created_by' => $this->extractCreatedBy(),
                'issued_by' => $this->extractIssuedBy(),
                'type' => 'flight', // Always flight for AIR files
                'agent_name' => $this->extractAgentName(),
                'agent_email' => $this->extractAgentEmail(),
                'agent_amadeus_id' => $this->extractAgentAmadeusId(),
                'client_name' => $passenger['client_name'],
                'supplier_name' => $this->extractSupplierName(),
                'supplier_country' => $this->extractSupplierCountry(),
                'cancellation_policy' => $this->extractCancellationPolicy(),
                'venue' => $this->extractVenue(),
                'issued_date' => $this->extractIssuedDate(),
                'task_flight_details' => $this->parseFlightDetails(),
            ];
            
            $tasks[] = $data;
        }
        
        return $tasks;
    }
    
    /**
     * Parse flight details from the AIR file
     * Extract multiple flight segments from H-lines and return as array
     */
    public function parseFlightDetails()
    {
        $flightSegments = $this->extractFlightSegments();
        
        // If we found H-lines with flight segments, return them
        if (!empty($flightSegments)) {
            $segmentDefaults = [
                'farebase' => $this->extractFarebase(),
                'duration_time' => $this->extractDurationTime(),
                'airline_id' => $this->extractAirlineName(),
                'baggage_allowed' => $this->extractBaggageAllowed(),
                'equipment' => $this->extractEquipment(),
                'ticket_number' => $this->extractTicketNumber(),
                'seat_no' => $this->extractSeatNumber(),
                'country_id_from' => $this->extractDepartureCountry(),
                'country_id_to' => $this->extractArrivalCountry(),
            ];
    
            $detailedSegments = array_map(function ($segment) use ($segmentDefaults) {
                return array_merge($segment, $segmentDefaults);
            }, $flightSegments);
    
            return $detailedSegments;
        }
        
        // Fallback to single flight object for files without H-lines
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
     * Extract flight segments from H-lines
     * Format: H-005;003OKWI;KUWAIT           ;DOH;DOHA             ;QR    1077 S S 30JUL0435 0605 30JUL;OK02;HK02;M ;0;77W;;;30K;1 ;;ET;0130 ;N;351;
     */
    private function extractFlightSegments()
    {
        $segments = [];
        $hLines = $this->findLines('/^[HU]-\d+[A-Z]?;(.+)/'); 
        
        foreach ($hLines as $hLine) {
            $parts = explode(';', $hLine[1]);
            
            if (count($parts) >= 5) {
                // Parse the flight information - corrected indexing
                $fromAirport = trim($parts[0]); // 003OKWI
                $fromCity = trim($parts[1]);    // KUWAIT
                $toAirport = trim($parts[2]);   // DOH
                $toCity = trim($parts[3]);      // DOHA
                $flightInfo = trim($parts[4]);  // QR    1077 S S 30JUL0435 0605 30JUL
                
                // Extract airport codes (remove prefix numbers and letters)
                $fromCode = preg_replace('/^\d+[A-Z]?/', '', $fromAirport); // Remove leading digits and optional letter -> KWI
                $toCode = $toAirport; // DOH is already clean

                $match = $this->findLine('/^(H-\d{3};.+)/');
                if ($match) {
                    $segmentParts = explode(';', $match[1]);
                    if (isset($segmentParts[17])) {
                        $terminalTo = trim($segmentParts[17]);
                    }
                    if (isset($segmentParts[14])) {
                        $terminalFrom = trim($segmentParts[14]);
                    }
                    if (isset($segmentParts[8])) {
                        $flightMeal = trim($segmentParts[8]);
                    }
                }
                
                // Parse flight info: QR    1077 S S 30JUL0435 0605 30JUL
                if (preg_match('/([A-Z]{2})\s+(\d+)\s+[A-Z]\s+[A-Z]\s+(\d{2}[A-Z]{3})(\d{4})\s+(\d{4})\s+(\d{2}[A-Z]{3})/', $flightInfo, $flightMatch)) {
                    $airline = $flightMatch[1];           // QR
                    $flightNumber = $flightMatch[2];      // 1077
                    $departureDate = $flightMatch[3];     // 30JUL
                    $departureTime = $flightMatch[4];     // 0435
                    $arrivalTime = $flightMatch[5];       // 0605
                    $arrivalDate = $flightMatch[6];       // 30JUL
                    
                    // Convert date format from 30JUL to 2025-07-30 and combine with time
                    $departureDateTime = $this->combineDateTimeFormat($departureDate, $departureTime);
                    $arrivalDateTime = $this->combineDateTimeFormat($arrivalDate, $arrivalTime);
                    
                    $segments[] = [
                        'airport_from' => $fromCode,
                        'airport_to' => $toCode,
                        'flight_number' => $airline . $flightNumber,
                        'airline' => $airline,
                        'departure_time' => $departureDateTime,
                        'arrival_time' => $arrivalDateTime,
                        'terminal_from' => $terminalFrom ?? '',
                        'terminal_to' => $terminalTo ?? '',
                        'flight_meal' => $flightMeal ?? '',
                        'class_type' => 'economy', // Default, could be extracted from booking class if needed
                    ];
                }
            }
        }
        
        return $segments;
    }
    
    /**
     * Convert date format from 30JUL to 2025-07-30
     */
    private function convertDateFormat($dateStr)
    {
        $months = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
        ];
        
        if (preg_match('/(\d{2})([A-Z]{3})/', $dateStr, $match)) {
            $day = $match[1];
            $month = $months[$match[2]] ?? '01';
            $year = '2025'; // Default to current year + 1, could be made smarter
            
            return "{$year}-{$month}-{$day}";
        }
        
        return $dateStr;
    }
    
    /**
     * Convert time format from 0435 to 04:35
     */
    private function convertTimeFormat($timeStr)
    {
        if (strlen($timeStr) === 4) {
            return substr($timeStr, 0, 2) . ':' . substr($timeStr, 2, 2);
        }
        
        return $timeStr;
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
     * Format: T-K229-2833184683 -> take last 10 digits of ticket number part
     * For multiple passengers, returns the first ticket number
     */
    private function extractTicketNumber()
    {
        // Look for T-K line with format: T-K[airline_code]-[ticket_number]
        $match = $this->findLine('/^(T-[KE]\d+-\d+)/');
        if ($match) {
            return $match[1];    
        }

        // Look for TMCD line with format: TMCD[airline_code]-[ticket_number]
        $match = $this->findLine('/^TMCD(\d+)-(\d+)/');
        if ($match) {
            return $match[1];    
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
        $match = $this->findLine('/^MUC1A\s+[A-Z0-9]+.*\s+([A-Z0-9]{6})/');

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
        // Check refund only if line starts with AIR-BLK and has second field = RF
        if ($this->findLine('/^AIR-BLK\d+;RF;/')) {
            return 'refund';
        }

        // Check for VOID only if ;VOIDddMMM; is in a specific line format
        if ($this->findLine('/;VOID\d{2}[A-Z]{3};/')) {
            return 'void';
        }

        // Check if line starts with FO + ticket number
        if ($this->findLine('/^FO\d{3}-\d{10}/')) {
            return 'reissued';
        }

        // Check for EMD
        if ($this->findLine('/^EMD\d{3,4};/')) {
            return 'emd';
        }
        return 'issued';
    }
    
    /**
     * Extract refund date if status is refund
     */
    private function extractRefundDate()
    {
        if ($this->extractStatus() !== 'refund') {
            return null;
        }

        if ($match = $this->findLine('/\bD-(\d{6});(\d{6});\d{6}\b/')) {
            try {
                $date = Carbon::createFromFormat('ymd', $match[2]);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // If parsing fails, return null
                return null;
            }
        }        

        return null;
    }
    
    /**
     * Extract void date if status is void
     */
    private function extractVoidDate()
    {
        if ($this->extractStatus() !== 'void') {
            return null;
        }

        // Look for VOID date patterns like VOID03JUL or VOID16JUL, but avoid XX patterns
        $match = $this->findLine('/VOID(\d{2}(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC))/');
        if ($match) {
            try {
                $date = Carbon::createFromFormat('dM', $match[1]);
                $date->year = Carbon::now()->year; // Assume current year
                $date->setTime(0, 0, 0);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, return null
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
        // Check for regular format (K-F)
        if ($this->hasCurrencyExchange()) {
            // 3-pair format: K-FAED1300 ;KWD109.000 ;;;;;;;;;;;KWD150.900 ;
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[4]; // Final total amount (KWD150.900)
            }
        } else {
            // 2-pair format: K-FKWD66.000 ;;;;;;;;;;;;KWD194.300 ;;;
            $match = $this->findLine('/^K-[RF]([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[2]; // Total amount (KWD194.300)
            }
        }

        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)\s*;*\s*([A-Z]{3})([\d.]+)/');
        if ($match) {
            // For refunds, determine if there's currency exchange
            if ($this->hasCurrencyExchange()) {
                // 3-pair format: return the final total (6th position)
                $exchangeMatch = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
                if ($exchangeMatch) {
                    return (float) $exchangeMatch[6];
                }
            }
            // 2-pair format: return the second amount
            return (float) $match[4];
        }
        
        $match = $this->findLine('/^RFD[MFLAC]?\s*;[^;]*;[^;]*;[A-Z]{3}([\d.]+)/');
        if ($match) {
            
            return (float) $match[1];
        }

        // Fallback: Look for any price pattern on K line (base fare only)
        $match = $this->findLine('/^K[NS]?-[RFI]?([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // Base fare if no total found
        }

        $match = $this->findLine('/^EMD\d+;.+?;([A-Z]{3})\s*([\d.]+);N;;([A-Z]{3})\s*([\d.]+)/');
        if ($match) {
            return (float) $match[4];
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
            $match = $this->findLine('/^K[NS]?-[RFI]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[5]; // Final currency (KWD in exchange example)
            }
        } else {
            // 2-pair format - return the second currency
            // Check for refund format first (KN- or KS-)
            $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[3]; // Second currency (KWD in refund example)
            }
        
            // Regular format (K-F) with second currency
            $match = $this->findLine('/^K-[RF]([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[3]; // Second currency (e.g., KWD)
            }

            // Fallback: Just get first currency
            $match = $this->findLine('/^K-[RF]([A-Z]{3})([\d.]+)/');
            if ($match) {
                return $match[1]; // First currency (e.g., EGP)
            }
        }
        
        // Fallback: Look for any currency on K line
        $match = $this->findLine('/^K[NS]?-[RFI]?([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency as fallback
        }

        $match = $this->findLine('/^EMD\d+;.+?;([A-Z]{3})\s*([\d.]+);N;;([A-Z]{3})\s*([\d.]+)/');
        if ($match) {
            return $match[3];
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
        // Check for regular format (K-F)
        $match = $this->findLine('/^K-[RF]([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // First amount (base fare)
        }

        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)/');
        if ($match) {
            return (float) $match[2]; // First amount (base fare)
        }
        
        $match = $this->findLine('/^RFD[MFLAC]?\s*;[^;]*;[^;]*;[A-Z]{3}([\d.]+)/');
        if ($match) {
            
            return (float) $match[1];
        }

        $match = $this->findLine('/^EMD\d+;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;([A-Z]{3})\s*([\d.]+)/');
        if ($match) {
            return (float) $match[2];
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
        // Check for regular format (K-F)
        $match = $this->findLine('/^K-[RF]([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency
        }

        // Check for refund format first (KN- or KS-)
        $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})/');
        if ($match) {
            return $match[1]; // First currency
        }

        $match = $this->findLine('/^EMD\d+;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;([A-Z]{3})\s*([\d.]+)/');
        if ($match) {
            return $match[1];
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
        // Try regular format (K-F)
        if ($this->hasCurrencyExchange()) {
            // 3-pair format: K-FAED1300 ;KWD109.000 ;;;;;;;;;;;KWD150.900 ;
            $match = $this->findLine('/^K-F([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[6]; // Final total fare (KWD150.900)
            }
        } else {
            // 2-pair format: K-FKWD66.000 ;;;;;;;;;;;;KWD194.300 ;;;
            $match = $this->findLine('/^K-[RF]([A-Z]{3})([\d.]+)\s*;(.*)/');
            if ($match) {
                $tail = $match[3];
                preg_match_all('/([A-Z]{3})\s*([\d.]+)/', $tail, $allMatches, PREG_SET_ORDER);
                if (!empty($allMatches)) {
                    $last = end($allMatches);
                    return (float) $last[2];
                }
            }
        }

        // First try to get explicit total from refund format (KN- or KS-)
        if ($this->hasCurrencyExchange()) {
            // 3-pair format with currency exchange
            $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[6]; // Final total fare
            }
        } else {
            // 2-pair format without currency exchange
            $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)\s*;{10,}([A-Z]{3})([\d.]+)/');
            if ($match) {
                return (float) $match[4]; // Total fare
            }
        }
        
        $match = $this->findLine('/^RFD[MFLAC]?[;\s].*;([\d.]+)\s*$/');
        if ($match) {
            return (float) $match[1]; // Last value = total
        }

        // If no explicit total found in K line, fallback to base fare
        $baseFare = $this->extractPrice();
        if ($baseFare !== null) {
            return $baseFare;
        }

        $match = $this->findLine('/^EMD\d+;.+?;([A-Z]{3})\s*([\d.]+);N;;([A-Z]{3})\s*([\d.]+);.*;([A-Z]{3})\s*([\d.]+)/');
        if ($match) {
            return (float) $match[6];
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

        $match = $this->findLine('/^RFD[MFLAC]? *;.*/');
        if ($match && isset($match[0])) {
            $fields = explode(';', $match[0]);
            return (float) trim($fields[8]);
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
        
        $match = $this->findLine('/^KRF\s*;(.*)/');
        if ($match) {
            $taxString = $match[1];
            if (preg_match_all('/Q([A-Z]{3})([\d.]+)\s+([A-Z0-9]{2})/', $taxString, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $totalTax += (float) $m[2];
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
        if ($this->extractStatus() === 'refund' && $match = $this->findLine('/^KRF\s*;(.*)/')) {
            return rtrim($match[1], ";\r\n ");
        }

        // Try refund format first (KNTI or KSTI line)
        $match = $this->findLine('/^K[NS]T[FI];(.+)/');
        if ($match) {
            return rtrim($match[1], "; \r\n");
        }
        
        // Try regular format (KFTF line)
        $match = $this->findLine('/^KFTF;(.+?)(;*)$/');
        if ($match) {
            return rtrim($match[1], "; \r\n");
        }
        
        // Try TAX line
        $match = $this->findLine('/^TAX-(.+)/');
        if ($match) {
            return rtrim($match[1], "; \r\n");
        }
        
        $match = $this->findLine('/^KRF\s*;(.*)/');
        if ($match) {
            $taxString = $match[1];
            if (preg_match_all('/Q([A-Z]{3})([\d.]+)\s+([A-Z0-9]{2})/', $taxString, $matches, PREG_SET_ORDER)) {
                $formatted = [];
                foreach ($matches as $m) {
                    $currency = $m[1];     // e.g., KWD
                    $amount = $m[2];       // e.g., 1.000
                    $code = $m[3];         // e.g., GZ
                    $formatted[] = "{$currency}{$amount}    {$code} XX";
                }
                return implode('; ', $formatted);
            }
        }

        // Fallback to KRF line
        $match = $this->findLine('/KRF:(.+)/');
        if ($match) {
            return rtrim($match[1], "; \r\n");
        }
        
        return '';
    }
    
    /**
     * Extract refund charge (non-refundable taxes)
     */
    private function extractRefundCharge()
    {
        $taxesRecord = $this->extractTaxesRecord();
        if (empty($taxesRecord)) {
            return 0.0;
        }

        $total = 0.0;
        foreach (explode(';', $taxesRecord) as $seg) {
            $seg = trim($seg);
            if (preg_match('/^Q[A-Z]{3}([\d.]+)\s+(YQ|YR|YX)$/', $seg, $m)) {
                $total += (float)$m[1];
            }
        }

        return $total;
    }
    
    /**
     * Extract reference (first ticket number for single passenger, or GDS reference for multiple)
     */
    private function extractReference()
    {
        $ticketNumber = $this->extractTicketNumber();
        return substr($ticketNumber, -10);
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

         $match = $this->findLine('/^MUC1A\s*;\s*\d+;\s*([A-Z0-9]+);/');
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
        $match = $this->findLine('/^MUC1A\s+(.+?)(?:;+)?$/');
        if ($match) {
            // Split by semicolon and get all non-empty parts
            $parts = array_filter(explode(';', $match[1]), function($part) {
                return trim($part) !== '';
            });
            
            // Find the last part that contains 'KWIKT'
            $lastKwiktPart = null;
            foreach ($parts as $part) {
                if (strpos($part, 'KWIKT') !== false) {
                    $lastKwiktPart = $part;
                }
            }
            
            return $lastKwiktPart ?: end($parts); // Return last KWIKT part or fallback to last part
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
     * For multiple passengers, returns the first client name
     */
    private function extractClientName()
    {
        $match = $this->findLine('/^I-(\d+);(\d+)([^;]+);/');
        if ($match) {
            return trim($match[3]);
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
    private function extractIssuedDate()
    {
        // Look for date patterns like TKOK12FEB, but avoid XX patterns
        $match = $this->findLine('/T[A-Z]{3}(\d{2}(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC))/');
        if ($match) {
            try {
                $date = Carbon::createFromFormat('dM', $match[1]);
                $date->year = Carbon::now()->year; // Assume current year
                $date->setTime(0, 0, 0);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, return null
            }
        }
        
        // For refund tasks, if no issued_date found, use refund_date as fallback
        if ($this->extractStatus() === 'refund') {
            $refundDate = $this->extractRefundDate();
            if ($refundDate) {
                return $refundDate;
            }
        }
        
        // For void tasks, if no issued_date found, use void_date as fallback
        if ($this->extractStatus() === 'void') {
            $voidDate = $this->extractVoidDate();
            if ($voidDate) {
                return $voidDate;
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
         // Match pattern like 02APR0435 (day + month + time), but avoid XX patterns
    $match = $this->findLine('/\b(\d{2})(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)(\d{4})\b/');
    if ($match) {
        $day = $match[1];      // "02"
        $month = $match[2];    // "APR"
        $time = $match[3];     // "0435"

        $hour = substr($time, 0, 2);   // "04"
        $minute = substr($time, 2, 2); // "35"

        try {
            // Build a Carbon instance using day, month, and time (assumes current year)
            $datetime = Carbon::createFromFormat('d-M-H:i', "{$day}-{$month}-{$hour}:{$minute}");
            return $datetime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // If parsing fails, return null
            return null;
        }
    }

    return null;
    }
    
    private function extractDepartureCountry()
    {
        return 'Kuwait'; // Default based on KWI airport
    }
    
    private function extractDepartureAirport()
    {
        $match = $this->findLine('/^U-/');
        if ($match) {
            $parts = explode(';', $match[0]);
            if (isset($parts[1])) {
                return substr($parts[1], -3);
            }
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
        // Match pattern like 02APR0435 (day + month + time), but avoid XX patterns
        $match = $this->findLine('/\b(\d{2})(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\d{4}\s+(\d{4})\s+\1\2\b/');
        if ($match) {
            $day = $match[1];      // "02"
            $month = $match[2];    // "APR"
            $time = $match[3];     // "0435"

            $hour = substr($time, 0, 2);   // "04"
            $minute = substr($time, 2, 2); // "35"

            try {
                // Build a Carbon instance using day, month, and time (assumes current year)
                $datetime = Carbon::createFromFormat('d-M-H:i', "{$day}-{$month}-{$hour}:{$minute}");
                return $datetime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, return null
                return null;
            }
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
        $match = $this->findLine('/^(U-\d{3}X;.+)/');
        if ($match) {
            $segments = explode(';', $match[1]);
            if (isset($segments[17])) {
                return trim($segments[17]);
            }
        }
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

        $match = $this->findLine('/^(U-\d{3}X;.+)/');
        if ($match) {
            $segments = explode(';', $match[1]);
            if (isset($segments[8])) {
                return trim($segments[8]);
            }
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
        $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)/');
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
        $match = $this->findLine('/^K[NS]-[RFI]?([A-Z]{3})([\d.]+)\s*;;;;;;;;;;;;([A-Z]{3})([\d.]+)/');
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
        $threePairMatch = $this->findLine('/^K[RNS]?-[FI]?([A-Z]{3})([\d.]+)\s*;([A-Z]{3})([\d.]+)\s*;{5,}([A-Z]{3})([\d.]+)/');
        if ($threePairMatch) {
            // Check if first and second currencies are different (indicating exchange)
            return $threePairMatch[1] !== $threePairMatch[3];
        }
        
        return false;
    }
    
    /**
     * Extract all passengers from the AIR file
     * Returns an array of passenger data with ticket numbers and names
     */
    private function extractAllPassengers()
    {
        $passengers = [];
        
        // Find all I- lines (passenger lines)
        $passengerLines = $this->findLines('/^I-(\d+);(\d+)([^;]+);/');
        
        // Find all T-K lines (ticket lines)
        $ticketLines = $this->findLines('/^T-[KE](\d+)-(\d+)/');

        if (empty($ticketLines)) {
            $ticketLines = $this->findLines('/^TMCD(\d+)-(\d+)/');
        }
        
        // Match passengers with their tickets
        foreach ($passengerLines as $index => $passengerMatch) {
            $passengerNumber = $passengerMatch[1]; // e.g., "001", "002"
            $clientName = trim($passengerMatch[3]); // e.g., "ALZANKI/FAHAD MR"
            
            // Find corresponding ticket (they should be in order)
            $ticketNumber = '';
            if (isset($ticketLines[$index])) {
                $ticketMatch = $ticketLines[$index];
                $ticketNumber = $ticketMatch[0];    // e.g., "T-K012-1234567890"
            }
            
            $passengers[] = [
                'passenger_number' => $passengerNumber,
                'client_name' => $clientName,
                'ticket_number' => $ticketNumber,
                'price' => null,
            ];
        }

        foreach ($this->lines as $line) {
            if (preg_match('/^\s*EMD\d+;.*?;N;;\s*([A-Z]{3})\s*([\d.]+).*?;P(\d+);/i', $line, $m)) {
                $paxIndex = (int) $m[3];
                $price    = (float) $m[2];
        
                if (isset($passengers[$paxIndex - 1])) {
                    $passengers[$paxIndex - 1]['price'] = $price;
                }
                continue;
            }

            if (preg_match('/^EMD\d+;.*?(KWD\s*([\d.]+)).*?;P(\d+);/i', $line, $match)) {
                $price = (float) $match[2];
                $paxIndex = (int) $match[3];
        
                if (isset($passengers[$paxIndex - 1])) {
                    $passengers[$paxIndex - 1]['price'] = $price;
                }
            } 
        }        
        
        return $passengers;
    }

    /**
     * Combine date and time into a single datetime format
     * Converts 30JUL and 0435 to "2025-07-30 04:35:00"
     */
    private function combineDateTimeFormat($dateStr, $timeStr)
    {
        $formattedDate = $this->convertDateFormat($dateStr);
        $formattedTime = $this->convertTimeFormat($timeStr);
        
        return $formattedDate . ' ' . $formattedTime . ':00';
    }
}