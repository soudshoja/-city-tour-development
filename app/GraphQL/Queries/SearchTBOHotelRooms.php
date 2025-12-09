<?php

namespace App\GraphQL\Queries;

use App\Services\TBOHolidayService;
use App\Models\TBO;
use App\Models\TBORoom;
use App\Models\Country;
use App\Http\Traits\CurrencyExchangeTrait;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SearchTBOHotelRooms
{
    use CurrencyExchangeTrait;
    
    protected $tboService;
    protected $logger;

    public function __construct()
    {
        $this->tboService = new TBOHolidayService();
        $this->logger = Log::channel('tbo');
    }

    public function __invoke($_, array $args)
    {
        $input = $args['input'];

        $validator = Validator::make($input, [
            'telephone' => 'required|string',
            'hotelCode' => 'nullable|integer',
            'hotel' => 'nullable|string',
            'city' => 'required|string',
            'guestNationality' => 'required|string',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'occupancy' => 'required',
            'noOfRooms' => 'nullable|integer|min:1',
            'refundable' => 'nullable|boolean',
            'mealType' => 'nullable|string|in:All,WithMeal,RoomOnly',
            'priceMin' => 'nullable|numeric|min:0',
            'priceMax' => 'nullable|numeric|min:0',
            'bookingType' => 'required|string|in:b2b,b2c',
            'minRating' => 'nullable|integer|min:1|max:5',
            'maxRating' => 'nullable|integer|min:1|max:5',
        ], [
            'telephone.required' => 'Telephone number is required.',
            'city.required_with' => 'City name is required when searching by hotel name.',
            'guestNationality.required' => 'Guest nationality is required.',
            'checkIn.required' => 'Check-in date is required.',
            'checkIn.after_or_equal' => 'Check-in date must be today or later.',
            'checkOut.required' => 'Check-out date is required.',
            'checkOut.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'Occupancy is required.',
            'mealType.in' => 'Meal type must be one of: All, WithMeal, RoomOnly.',
            'bookingType.required' => 'Booking type is required.',
            'bookingType.in' => 'Booking type must be either b2b or b2c.',
        ]);

        $this->logger->info('TBO hotel search input', ['input' => $input]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status' => 'validation_error',
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        try {
            $guestNationalityInput = $input['guestNationality'];
            $guestNationalityCode = null;

            if (strlen($guestNationalityInput) === 2) {
                $guestNationalityCode = strtoupper($guestNationalityInput);
            } else {
                $country = Country::where('name', 'LIKE', '%' . $guestNationalityInput . '%')
                    ->orWhere('iso_code', strtoupper($guestNationalityInput))
                    ->first();

                if (!$country) {
                    $allCountries = Country::all();
                    $bestMatch = null;
                    $lowestDistance = PHP_INT_MAX;
                    $threshold = 3;

                    foreach ($allCountries as $c) {
                        $distance = levenshtein(
                            strtolower($guestNationalityInput),
                            strtolower($c->name)
                        );

                        if ($distance < $lowestDistance) {
                            $lowestDistance = $distance;
                            $bestMatch = $c;
                        }
                    }

                    if ($bestMatch && $lowestDistance <= $threshold) {
                        $country = $bestMatch;
                        
                        $this->logger->info('Found country using Levenshtein distance', [
                            'input' => $guestNationalityInput,
                            'matched_country' => $country->name,
                            'distance' => $lowestDistance
                        ]);
                    } else {
                        return [
                            'success' => false,
                            'status' => 'country_not_found',
                            'message' => "Country '{$guestNationalityInput}' not found. " . 
                                ($bestMatch ? "Did you mean '{$bestMatch->name}'?" : "Please provide a valid country name or 2-letter ISO code."),
                            'data' => null,
                            'hotelOptions' => null,
                        ];
                    }
                }

                $guestNationalityCode = $country->iso_code;
                
                $this->logger->info('Resolved guest nationality', [
                    'input' => $guestNationalityInput,
                    'resolved_code' => $guestNationalityCode,
                    'country_name' => $country->name
                ]);
            }

            $hotelCode = $input['hotelCode'] ?? null;
            
            if (!$hotelCode) {
                $findCodeResponse = $this->tboService->findHotelCodeByName(
                    hotelName: $input['hotel'] ?? null,
                    cityName: $input['city'] 
                );

                if ($findCodeResponse['status'] === 'multiple_hotels_found') {
                    $hotelOptions = $findCodeResponse['data'];
                    
                    $minRating = $input['minRating'] ?? null;
                    $maxRating = $input['maxRating'] ?? null;
                    
                    // When no hotel name AND no rating filters provided, default to 4-star and 5-star only
                    if (empty($input['hotel']) && $minRating === null && $maxRating === null) {
                        $minRating = 4;
                        $maxRating = 5;
                    }
                    
                    if ($minRating !== null || $maxRating !== null) {
                        $hotelOptions = array_filter($hotelOptions, function($hotel) use ($minRating, $maxRating) {
                            $hotelRating = $hotel['rating'] ?? '';
                            $ratingInt = $this->mapRatingToInteger($hotelRating);
                            
                            if ($minRating !== null && $ratingInt < $minRating) {
                                return false;
                            }
                            if ($maxRating !== null && $ratingInt > $maxRating) {
                                return false;
                            }
                            return true;
                        });
                        
                        $hotelOptions = array_values($hotelOptions);
                    }
                    
                    if (empty($input['hotel'])) {
                        $hotelOptions = $this->sampleHotelsByRating($hotelOptions, 2);
                    }
                    
                    return [
                        'success' => false,
                        'status' => 'multiple_hotels_found',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => $hotelOptions
                    ];
                }

                // Handle city required
                if ($findCodeResponse['status'] === 'city_required') {
                    return [
                        'success' => false,
                        'status' => 'city_required',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle city not found
                if ($findCodeResponse['status'] === 'city_not_found') {
                    return [
                        'success' => false,
                        'status' => 'city_not_found',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle no hotels in city
                if ($findCodeResponse['status'] === 'no_hotels_in_city') {
                    return [
                        'success' => false,
                        'status' => 'no_hotels_in_city',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle hotel not found
                if ($findCodeResponse['status'] === 'hotel_not_found') {
                    return [
                        'success' => false,
                        'status' => 'hotel_not_found',
                        'message' => $findCodeResponse['message'],
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }

                // Handle hotel found
                if ($findCodeResponse['status'] === 'hotel_found') {
                    $hotelCode = $findCodeResponse['data'];
                    
                    $this->logger->info('Found hotel code for hotel name', [
                        'hotel_name' => $input['hotel'],
                        'city' => $input['city'] ?? null,
                        'hotel_code' => $hotelCode
                    ]);
                } else {
                    // Unexpected status
                    $this->logger->error('Unexpected status from findHotelCodeByName', [
                        'hotel_name' => $input['hotel'],
                        'city' => $input['city'] ?? null,
                        'response' => $findCodeResponse
                    ]);

                    return [
                        'success' => false,
                        'status' => $findCodeResponse['status'] ?? 'error',
                        'message' => $findCodeResponse['message'] ?? 'Unable to retrieve hotel code.',
                        'data' => null,
                        'hotelOptions' => null
                    ];
                }
            }

            $rooms = $this->parseOccupancy($input['occupancy']);

            $result = $this->searchTBOHotelRooms(
                $hotelCode,
                $guestNationalityCode,
                $input['checkIn'],
                $input['checkOut'],
                $rooms,
                $input['noOfRooms'] ?? null,
                $input['refundable'] ?? null,
                $input['mealType'] ?? 'All',
                $input['priceMin'] ?? null,
                $input['priceMax'] ?? null,
                $input['bookingType'],
                $input['minRating'] ?? null,
                $input['maxRating'] ?? null
            );

            return $result;
        } catch (Exception $e) {
            $this->logger->error('TBO hotel search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Hotel search failed: ' . $e->getMessage(),
                'data' => null,
                'hotelOptions' => null,
            ];
        }
    }

    /**
     * Parse occupancy data from either string (Magic Holiday format) or array (TBO format)
     *
     * @param mixed $occupancy
     * @return array
     */
    protected function parseOccupancy($occupancy): array
    {
        // If occupancy is already an array of rooms
        if (is_array($occupancy) && isset($occupancy[0]) && is_array($occupancy[0])) {
            // Convert new format [{'adults':1,'childrenAges':[]}] to TBO format
            return array_map(function($room) {
                $childrenAges = $room['childrenAges'] ?? $room['childAges'] ?? [];
                return [
                    'adults' => $room['adults'],
                    'children' => count($childrenAges), // Infer children count from ages array
                    'childAges' => $childrenAges
                ];
            }, $occupancy);
        }

        // If occupancy has 'rooms' key with string value (Magic Holiday format)
        if (is_array($occupancy) && isset($occupancy['rooms']) && is_string($occupancy['rooms'])) {
            return $this->parseRoomsString($occupancy['rooms']);
        }

        // If occupancy is a string directly
        if (is_string($occupancy)) {
            // Try to decode JSON format first: "[{'adults':1,'childrenAges':[]}]"
            $decoded = json_decode(str_replace("'", '"', $occupancy), true);
            if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                // Parse the decoded array using the array logic
                return array_map(function($room) {
                    $childrenAges = $room['childrenAges'] ?? $room['childAges'] ?? [];
                    return [
                        'adults' => $room['adults'],
                        'children' => count($childrenAges),
                        'childAges' => $childrenAges
                    ];
                }, $decoded);
            }
            
            // Otherwise, use old format: "2,1|1,0"
            return $this->parseRoomsString($occupancy);
        }

        // Default: try to use as TBO format
        return $occupancy;
    }

    /**
     * Parse Magic Holiday rooms string format (e.g., "2,1|1,0")
     * Format: adults,children|adults,children
     *
     * @param string $roomsString
     * @return array
     */
    protected function parseRoomsString(string $roomsString): array
    {
        $rooms = [];
        $roomsData = explode('|', $roomsString);

        foreach ($roomsData as $roomData) {
            $parts = explode(',', $roomData);
            $adults = (int)($parts[0] ?? 1);
            $children = (int)($parts[1] ?? 0);

            $rooms[] = [
                'adults' => $adults,
                'children' => $children,
                'childAges' => []
            ];
        }

        return $rooms;
    }

    protected function searchTBOHotelRooms(
        int $hotelCode,
        string $guestNationality,
        string $checkIn,
        string $checkOut,
        array $rooms,
        ?int $noOfRooms = null,
        ?bool $refundable = null,
        string $mealType = 'All',
        ?float $priceMin = null,
        ?float $priceMax = null,
        string $bookingType,
        ?int $minRating = null,
        ?int $maxRating = null
    ): array {
        $hasPriceFilter = ($priceMin !== null || $priceMax !== null);
        
        // Convert price filter from KWD to USD if currency conversion is enabled
        $priceMinUSD = $priceMin;
        $priceMaxUSD = $priceMax;
        
        if ($hasPriceFilter && env('TBO_ENABLE_CURRENCY_CONVERSION', false)) {
            $companyId = 1; // Default company ID
            $exchangeRate = $this->getExchangeRate($companyId, 'USD', 'KWD');
            
            if ($exchangeRate) {
                // For B2C: User sees marked-up prices, so remove 20% markup first before converting
                // For B2B: No markup applied, use prices as-is
                $adjustedPriceMin = $priceMin;
                $adjustedPriceMax = $priceMax;
                
                if ($bookingType === 'b2c') {
                    // Remove 20% markup: price / 1.20
                    if ($priceMin !== null) {
                        $adjustedPriceMin = $priceMin / 1.20;
                    }
                    if ($priceMax !== null) {
                        $adjustedPriceMax = $priceMax / 1.20;
                    }
                }
                
                // Convert KWD prices back to USD for filtering TBO results
                // If rate is 0.30522 (USD to KWD), then to convert back: divide by rate
                if ($adjustedPriceMin !== null) {
                    $priceMinUSD = $adjustedPriceMin / $exchangeRate;
                }
                if ($adjustedPriceMax !== null) {
                    $priceMaxUSD = $adjustedPriceMax / $exchangeRate;
                }
                
                $this->logger->info('Converted price filter from KWD to USD', [
                    'booking_type' => $bookingType,
                    'original_min_kwd' => $priceMin,
                    'original_max_kwd' => $priceMax,
                    'adjusted_min_kwd' => $adjustedPriceMin,
                    'adjusted_max_kwd' => $adjustedPriceMax,
                    'exchange_rate' => $exchangeRate,
                    'converted_min_usd' => $priceMinUSD,
                    'converted_max_usd' => $priceMaxUSD,
                    'markup_removed' => $bookingType === 'b2c' ? '20%' : 'none'
                ]);
            }
        }
        
        $this->logger->info('Starting TBO hotel search', [
            'hotel_code' => $hotelCode,
            'guest_nationality' => $guestNationality,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms' => $rooms,
            'filters' => [
                'noOfRooms' => $noOfRooms,
                'refundable' => $refundable,
                'mealType' => $mealType,
                'priceMin_kwd' => $priceMin,
                'priceMax_kwd' => $priceMax,
                'priceMin_usd' => $priceMinUSD,
                'priceMax_usd' => $priceMaxUSD,
                'hasPriceFilter' => $hasPriceFilter,
                'minRating' => $minRating,
                'maxRating' => $maxRating,
            ],
        ]);

        $paxRooms = array_map(function ($room) {
            return [
                'Adults' => (string)$room['adults'],
                'Children' => $room['children'],
                'ChildrenAges' => $room['childAges'] ?? [],
            ];
        }, $rooms);

        $filters = [];
        
        if (!$hasPriceFilter) {
            $filters['NoOfRooms'] = $noOfRooms ?? 1;
        }
        
        if ($refundable !== null) {
            $filters['Refundable'] = $refundable;

            if(app()->environment() !== 'production' && app()->environment() !== 'staging') {
                $filters['Refundable'] = true;
            }
        }
        
        if ($mealType !== null) {
            $filters['MealType'] = $mealType;
        }

        $searchData = [
            'CheckIn' => $checkIn,
            'CheckOut' => $checkOut,
            'HotelCodes' => $hotelCode,
            'GuestNationality' => $guestNationality,
            'PaxRooms' => $paxRooms,
            'ResponseTime' => 23,
            'IsDetailedResponse' => false,
            'Filters' => $filters
        ];

        $response = $this->tboService->search($searchData);

        if (!isset($response['Status']) || $response['Status']['Code'] !== 200) {
            $errorMessage = $response['Status']['Description'] ?? 'Unknown error from TBO API';
            throw new Exception($errorMessage);
        }

        if (!isset($response['HotelResult']) || empty($response['HotelResult'])) {
            return [
                'success' => false,
                'status' => 'no_results',
                'message' => 'No hotels found for the specified criteria.',
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        $hotelResults = $response['HotelResult'];
        $allRooms = [];

        foreach ($hotelResults as $hotel) {
            if (!isset($hotel['Rooms']) || empty($hotel['Rooms'])) {
                continue;
            }

            foreach ($hotel['Rooms'] as $room) {
                $allRooms[] = [
                    'hotel' => $hotel,
                    'room' => $room,
                    'total_fare' => $room['TotalFare'] ?? 0,
                ];
            }
        }

        if (empty($allRooms)) {
            return [
                'success' => false,
                'status' => 'no_rooms',
                'message' => 'No available rooms found.',
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        if ($hasPriceFilter) {
            $allRooms = array_filter($allRooms, function($roomData) use ($priceMinUSD, $priceMaxUSD) {
                $price = $roomData['total_fare'];
                if ($priceMinUSD !== null && $price < $priceMinUSD) {
                    return false;
                }
                if ($priceMaxUSD !== null && $price > $priceMaxUSD) {
                    return false;
                }
                return true;
            });

            if (empty($allRooms)) {
                return [
                    'success' => false,
                    'status' => 'no_rooms_in_price_range',
                    'message' => 'No rooms found within the specified price range.',
                    'data' => null,
                    'hotelOptions' => null,
                ];
            }
        }
      
        // Apply rating filter if provided
        if (!$hotelCode && ($minRating !== null || $maxRating !== null)) {
            $allRooms = array_filter($allRooms, function($roomData) use ($minRating, $maxRating) {
                $hotelRating = $roomData['hotel']['HotelRating'] ?? '';
                $ratingInt = $this->mapRatingToInteger($hotelRating);
                
                if ($minRating !== null && $ratingInt < $minRating) {
                    return false;
                }
                if ($maxRating !== null && $ratingInt > $maxRating) {
                    return false;
                }
                return true;
            });

            if (empty($allRooms)) {
                return [
                    'success' => false,
                    'status' => 'no_rooms_in_rating_range',
                    'message' => 'No rooms found within the specified rating range.',
                    'data' => null,
                    'hotelOptions' => null,
                ];
            }
        }

        usort($allRooms, fn($a, $b) => $a['total_fare'] <=> $b['total_fare']);

        $roomsToPrebook = array_slice($allRooms, 0, $noOfRooms ?? 1);
        
        // Get hotel details once
        $firstRoom = $roomsToPrebook[0];
        $hotel = $firstRoom['hotel'];
        $hotelDetails = $this->tboService->getHotelDetails($hotel['HotelCode']);
        $hotelName = $hotelDetails['HotelDetails']['HotelName'] ?? 'Hotel ' . $hotel['HotelCode'];

        // Prebook all selected rooms
        $prebookedRooms = [];
        
        foreach ($roomsToPrebook as $roomData) {
            $room = $roomData['room'];
            
            $prebookResponse = $this->tboService->preBook($room['BookingCode']);

            if (!isset($prebookResponse['Status']) || $prebookResponse['Status']['Code'] !== 200) {
                $this->logger->warning('Prebook failed for one room', [
                    'booking_code' => $room['BookingCode'],
                    'error' => $prebookResponse['Status']['Description'] ?? 'Unknown error'
                ]);
                continue; // Skip this room and continue with others
            }

            $prebookHotel = $prebookResponse['HotelResult'][0] ?? null;
            $prebookRoom = $prebookHotel['Rooms'][0] ?? null;

            if (!$prebookRoom) {
                $this->logger->warning('Prebook response missing room details', [
                    'booking_code' => $room['BookingCode']
                ]);
                continue;
            }

            // Generate unique prebook key (same format as Magic Holiday)
            $prebookKey = 'PB-' . strtoupper(substr(uniqid(), -8));

            $this->logger->info('Generated prebook key for TBO', [
                'prebook_key' => $prebookKey,
                'booking_code' => $prebookRoom['BookingCode'],
                'hotel_code' => $hotel['HotelCode']
            ]);

            // Currency conversion logic
            $originalCurrency = $hotel['Currency'];
            $originalTotalFare = $prebookRoom['TotalFare'];
            $originalTotalTax = $prebookRoom['TotalTax'];
            $convertedTotalFare = $originalTotalFare;
            $convertedTotalTax = $originalTotalTax;
            $finalCurrency = $originalCurrency;
            $exchangeRate = 1;

            // Check if currency conversion is enabled via env
            if (env('TBO_ENABLE_CURRENCY_CONVERSION', false) && $originalCurrency !== 'KWD') {
                try {
                    $companyId = 1; // Default company ID
                    $conversionResult = $this->convert($companyId, $originalCurrency, 'KWD', $originalTotalFare);
                    
                    if ($conversionResult['status'] === 'success') {
                        $exchangeRate = $conversionResult['exchange_rate'];
                        $convertedTotalFare = $conversionResult['converted_amount'];
                        $finalCurrency = 'KWD';
                        
                        // Convert tax as well
                        $taxConversion = $this->convert($companyId, $originalCurrency, 'KWD', $originalTotalTax);
                        if ($taxConversion['status'] === 'success') {
                            $convertedTotalTax = $taxConversion['converted_amount'];
                        }
                        
                        $this->logger->info('TBO currency conversion applied', [
                            'original_currency' => $originalCurrency,
                            'original_fare' => $originalTotalFare,
                            'exchange_rate' => $exchangeRate,
                            'converted_fare' => $convertedTotalFare,
                            'target_currency' => 'KWD'
                        ]);
                    } else {
                        $this->logger->warning('TBO currency conversion failed, using original currency', [
                            'original_currency' => $originalCurrency,
                            'message' => $conversionResult['message'] ?? 'Unknown error'
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('TBO currency conversion exception', [
                        'error' => $e->getMessage(),
                        'original_currency' => $originalCurrency
                    ]);
                }
            }

            // Apply B2C markup (20%)
            $priceBeforeMarkup = $convertedTotalFare;
            $taxBeforeMarkup = $convertedTotalTax;
            $markupPercentage = 0;
            
            if ($bookingType === 'b2c') {
                $markupPercentage = 0.20; // 20% markup for B2C
                $convertedTotalFare = ceil($convertedTotalFare * (1 + $markupPercentage));
                $convertedTotalTax = ceil($convertedTotalTax * (1 + $markupPercentage));
                
                $this->logger->info('TBO B2C markup applied', [
                    'booking_type' => 'b2c',
                    'markup_percentage' => ($markupPercentage * 100) . '%',
                    'price_before_markup' => $priceBeforeMarkup,
                    'price_after_markup' => $convertedTotalFare,
                    'currency' => $finalCurrency
                ]);
            }

            $tboPrebook = TBO::create([
                'prebook_key' => $prebookKey,
                'booking_code' => $prebookRoom['BookingCode'],
                'booking_type' => $bookingType,
                'markup_percentage' => $markupPercentage,
                'hotel_code' => $hotel['HotelCode'],
                'hotel_name' => $hotelName,
                'room_quantity' => count($rooms),
                'room_name' => json_encode($prebookRoom['Name']),
                'currency' => $finalCurrency,
                'original_currency' => $originalCurrency,
                'exchange_rate' => $exchangeRate,
                'inclusion' => $prebookRoom['Inclusion'] ?? '',
                'day_rates' => 'day rates',
                'total_fare' => $convertedTotalFare,
                'total_tax' => $convertedTotalTax,
                'price_before_markup' => $priceBeforeMarkup,
                'tax_before_markup' => $taxBeforeMarkup,
                'original_total_fare' => $originalTotalFare,
                'original_total_tax' => $originalTotalTax,
                'extra_guest_charges' => $prebookRoom['ExtraGuestCharges'] ?? '0',
                'room_promotion' => json_encode($prebookRoom['RoomPromotion'] ?? []),
                'meal_type' => $prebookRoom['MealType'] ?? '',
                'is_refundable' => $prebookRoom['IsRefundable'] ?? false,
                'with_transfer' => $prebookRoom['WithTransfer'] ?? false,
            ]);

            foreach ($rooms as $index => $roomInput) {
                TBORoom::create([
                    'tbo_id' => $tboPrebook->id,
                    'room_name' => $prebookRoom['Name'][$index] ?? 'Room ' . ($index + 1),
                    'adult_quantity' => $roomInput['adults'],
                    'child_quantity' => $roomInput['children'],
                ]);
            }

            $roomDetails = array_map(function ($roomInput, $index) use ($prebookRoom, $convertedTotalFare, $finalCurrency) {
                return [
                    'room_name' => $prebookRoom['Name'][$index] ?? 'Room ' . ($index + 1),
                    'board_basis' => $prebookRoom['MealType'] ?? '',
                    'non_refundable' => !($prebookRoom['IsRefundable'] ?? false),
                    'price' => $convertedTotalFare / count($prebookRoom['Name']),
                    'currency' => $finalCurrency,
                    'info' => $prebookRoom['Inclusion'] ?? null,
                    'occupancy' => [
                        'adults' => $roomInput['adults'],
                        'children' => $roomInput['children'],
                        'childAges' => $roomInput['childAges'] ?? [],
                    ],
                ];
            }, $rooms, array_keys($rooms));

            $prebookedRooms[] = [
                'success' => true,
                'error' => null,
                'room' => $roomDetails,
                'prebook' => [
                    'prebookKey' => $prebookKey,
                    'tboId' => $tboPrebook->id,
                    'bookingCode' => $prebookRoom['BookingCode'],
                    'serviceDates' => [
                        'checkIn' => $checkIn,
                        'checkOut' => $checkOut,
                    ],
                    'package' => [
                        'price' => [
                            'selling' => [
                                'value' => $convertedTotalFare,
                                'currency' => $finalCurrency,
                            ],
                        ],
                    ],
                    'totalFare' => $convertedTotalFare,
                    'totalTax' => $convertedTotalTax,
                    'currency' => $finalCurrency,
                    'originalCurrency' => $originalCurrency,
                    'exchangeRate' => $exchangeRate,
                    'mealType' => $prebookRoom['MealType'] ?? '',
                    'isRefundable' => $prebookRoom['IsRefundable'] ?? false,
                    'inclusion' => $prebookRoom['Inclusion'] ?? '',
                    'cancelPolicies' => $prebookRoom['CancelPolicies'] ?? [],
                    'amenities' => $prebookRoom['Amenities'] ?? [],
                    'dayRates' => $prebookRoom['DayRates'] ?? [],
                    'rateConditions' => $prebookRoom['RateConditions'] ?? [],
                ],
            ];
        }

        if (empty($prebookedRooms)) {
            return [
                'success' => false,
                'status' => 'prebook_failed',
                'message' => 'All prebook attempts failed.',
                'data' => null,
                'hotelOptions' => null,
            ];
        }

        return [
            'success' => true,
            'status' => 'hotel_found',
            'message' => 'TBO hotel search completed successfully.',
            'data' => [
                'hotel_code' => $hotel['HotelCode'],
                'hotel_name' => $hotelName,
                'room_count' => count($prebookedRooms),
                'rooms' => $prebookedRooms,
            ],
        ];
    }

    /**
     * Map hotel rating string from TBO API to integer value
     *
     * @param string $hotelRating Rating string from TBO (e.g., 'OneStar', 'FourStar')
     * @return int Integer rating (1-5), 0 if unrecognized
     */
    protected function mapRatingToInteger(string $hotelRating): int
    {
        $ratingMap = [
            'OneStar' => 1,
            'TwoStar' => 2,
            'ThreeStar' => 3,
            'FourStar' => 4,
            'FiveStar' => 5,
        ];
        
        return $ratingMap[$hotelRating] ?? 0;
    }

    /**
     * Sample random hotels from each rating group
     * Returns exactly 2 random hotels per rating (or all if less than 2)
     * Maintains rating order from low to high
     *
     * @param array $hotels Array of hotels with 'rating' field
     * @param int $samplesPerRating Number of hotels to sample per rating (default: 2)
     * @return array Sampled hotels ordered by rating
     */
    protected function sampleHotelsByRating(array $hotels, int $samplesPerRating = 2): array
    {
        $groupedByRating = [];
        
        foreach ($hotels as $hotel) {
            $hotelRating = $hotel['rating'] ?? '';
            $ratingInt = $this->mapRatingToInteger($hotelRating);
            
            if ($ratingInt === 0) {
                continue;
            }
            
            if (!isset($groupedByRating[$ratingInt])) {
                $groupedByRating[$ratingInt] = [];
            }
            
            $groupedByRating[$ratingInt][] = $hotel;
        }
        
        ksort($groupedByRating);
        
        $sampledHotels = [];
        
        foreach ($groupedByRating as $rating => $ratingHotels) {
            $hotelCount = count($ratingHotels);
            
            if ($hotelCount <= $samplesPerRating) {
                $sampledHotels = array_merge($sampledHotels, $ratingHotels);
            } else {
                shuffle($ratingHotels);
                $sampledHotels = array_merge($sampledHotels, array_slice($ratingHotels, 0, $samplesPerRating));
            }
        }
        
        return $sampledHotels;
    }
}
