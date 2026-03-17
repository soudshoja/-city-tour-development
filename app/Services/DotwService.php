<?php

namespace App\Services;

use App\Exceptions\DotwTimeoutException;
use App\Models\CompanyDotwCredential;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * DOTW V4 API Service
 *
 * Handles all communication with DOTWconnect (DCML) XML-based hotel booking API
 * Version 4 simplified protocol with mandatory dual getRooms pattern
 *
 * Credential resolution:
 * - When constructed with a company_id, credentials are loaded from the
 *   company_dotw_credentials table via CompanyDotwCredential model (B2B path).
 * - When constructed with no arguments (company_id = null), credentials fall
 *   back to config/dotw.php env values for backward compatibility.
 *
 * Key features:
 * - Per-company DB credential resolution (multi-tenant B2B)
 * - XML request builder with <customer> authentication wrapper
 * - MD5 password hashing for security
 * - Gzip compression on all requests/responses
 * - Mandatory dual getRooms pattern (browse + block)
 * - 3-minute allocation expiry tracking
 * - Complete error handling with DOTW error codes
 * - Comprehensive logging to 'dotw' channel (company_id logged, never credentials)
 */
class DotwService
{
    /**
     * API endpoint URL
     */
    private string $baseUrl;

    /**
     * DOTW username
     */
    private string $username;

    /**
     * MD5 hashed password
     */
    private string $passwordMd5;

    /**
     * DOTW company code
     */
    private string $companyCode;

    /**
     * Logger instance for 'dotw' channel
     */
    private $logger;

    /**
     * Request/response timeout in seconds
     */
    private int $timeout;

    /**
     * B2C markup percentage loaded from DB (per-company) or config fallback
     */
    private float $markupPercent;

    /**
     * Company ID for the current B2B context (null = legacy env-based mode)
     */
    private ?int $companyId;

    /**
     * Audit service instance for writing to dotw_audit_logs
     */
    private DotwAuditService $auditService;

    /**
     * Cached salutation ID map from getsalutationsids API.
     * Loaded on first call to getSalutationIds().
     *
     * @var array<string, int>|null
     */
    private ?array $salutationMap = null;

    /**
     * Rate basis code constants
     */
    public const RATE_BASIS_ALL = 1;

    public const RATE_BASIS_ROOM_ONLY = 1331;

    public const RATE_BASIS_BB = 1332;

    public const RATE_BASIS_HB = 1333;

    public const RATE_BASIS_FB = 1334;

    public const RATE_BASIS_AI = 1335;

    public const RATE_BASIS_SC = 1336;

    /**
     * Initialize DOTW service with optional per-company credential resolution.
     *
     * B2B path (company_id provided):
     *   Loads credentials from company_dotw_credentials table via
     *   CompanyDotwCredential. Throws RuntimeException if no active credential
     *   row exists for the given company.
     *
     * Legacy path (company_id = null):
     *   Falls back to config/dotw.php env values for backward compatibility
     *   with existing callers (e.g., SearchDotwHotels job) that do not pass a
     *   company_id.
     *
     * @param  int|null  $companyId  Company ID for B2B credential resolution,
     *                               or null for legacy env-based credentials.
     * @param  DotwAuditService|null  $auditService  Audit service instance, or null to
     *                                               create a default instance (backward compat).
     *
     * @throws \RuntimeException When company_id is provided but no active
     *                           credential row exists for that company.
     */
    public function __construct(?int $companyId = null, ?DotwAuditService $auditService = null)
    {
        $isDev = config('dotw.dev_mode', true);

        $this->baseUrl = $isDev
            ? config('dotw.endpoints.development')
            : config('dotw.endpoints.production');

        $this->timeout = config('dotw.request.timeout', 25);
        $this->logger = Log::channel(config('dotw.log_channel', 'dotw'));
        $this->companyId = $companyId;

        if ($companyId !== null) {
            // B2B path: load per-company credentials from DB
            $credential = CompanyDotwCredential::forCompany($companyId)->first();

            if (! $credential) {
                throw new \RuntimeException(
                    "DOTW credentials not configured for this company (company_id: {$companyId})"
                );
            }

            $this->username = $credential->dotw_username;
            $this->passwordMd5 = md5($credential->dotw_password);
            $this->companyCode = $credential->dotw_company_code;
            $this->markupPercent = (float) $credential->markup_percent;
        } else {
            // Legacy path: fall back to env credentials (backward compat for existing callers)
            $this->username = config('dotw.username', '');
            $this->passwordMd5 = md5(config('dotw.password', ''));
            $this->companyCode = config('dotw.company_code', '');
            $this->markupPercent = (float) config('dotw.b2c_markup_percentage', 20);
        }

        $this->auditService = $auditService ?? new DotwAuditService;

        $this->logger->debug('DOTW Service initialized', [
            'endpoint' => $this->baseUrl,
            'company_id' => $this->companyId,
            'mode' => $isDev ? 'development' : 'production',
        ]);
    }

    /**
     * Apply B2C markup to a raw DOTW fare.
     *
     * Uses the markup_percent loaded from DB (B2B path) or config (legacy path).
     *
     * @param  float  $originalFare  The raw fare returned by the DOTW API
     * @return array{original_fare: float, markup_percent: float, markup_amount: float, final_fare: float}
     */
    public function applyMarkup(float $originalFare): array
    {
        $markupAmount = round($originalFare * ($this->markupPercent / 100), 2);

        return [
            'original_fare' => $originalFare,
            'markup_percent' => $this->markupPercent,
            'markup_amount' => $markupAmount,
            'final_fare' => round($originalFare + $markupAmount, 2),
        ];
    }

    /**
     * Search for hotels with availability
     *
     * Returns only the cheapest rate per meal plan per room type (V4 simplified)
     * No cancellation policy or allocationDetails in this response
     *
     * Must call getRooms() afterwards to get full details and rate block
     *
     * @param  array  $params  Search parameters:
     *                         - fromDate: YYYY-MM-DD
     *                         - toDate: YYYY-MM-DD
     *                         - currency: Currency code (USD, AED, etc.)
     *                         - rooms: Array with occupancy details
     *                         - city: City code
     *                         - filters: Optional filter conditions (rating, price, chain, etc.)
     * @param  string|null  $resayilMessageId  WhatsApp message_id from X-Resayil-Message-ID header (MSG-02)
     * @param  string|null  $resayilQuoteId  Quoted message_id from X-Resayil-Quote-ID header (MSG-03)
     * @param  int|null  $companyId  Company context override (null = use constructor companyId)
     * @return array Parsed response with hotels array
     *
     * @throws Exception If request fails or validation returns error
     */
    public function searchHotels(array $params, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array
    {
        $this->logger->info('DOTW searchHotels request initiated', [
            'from_date' => $params['fromDate'] ?? null,
            'to_date' => $params['toDate'] ?? null,
            'currency' => $params['currency'] ?? null,
        ]);

        $body = $this->buildSearchHotelsBody($params);
        $xml = $this->wrapRequest('searchhotels', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW searchHotels error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW searchHotels error [{$errorCode}]: {$errorDetails}");
        }

        $hotels = $this->parseHotels($response);

        $this->auditService->log(
            DotwAuditService::OP_SEARCH,
            $params,
            $hotels,
            $resayilMessageId,
            $resayilQuoteId,
            $companyId ?? $this->companyId
        );

        $this->logger->info('DOTW searchHotels successful', [
            'hotel_count' => count($hotels),
        ]);

        return $hotels;
    }

    /**
     * Get rooms with rate blocking capability
     *
     * Called TWICE in V4 flow:
     * 1. First without blocking: browse available rates and get details
     * 2. Then with blocking: lock the rate for 3 minutes
     *
     * @param  array  $params  Room search parameters:
     *                         - fromDate: YYYY-MM-DD
     *                         - toDate: YYYY-MM-DD
     *                         - currency: Currency code
     *                         - productId: Hotel ID
     *                         - rooms: Array with room details
     *                         - roomTypeSelected: Only when blocking (includes allocationDetails from first call)
     * @param  bool  $blocking  Whether to perform rate blocking
     * @param  string|null  $resayilMessageId  WhatsApp message_id from X-Resayil-Message-ID header (MSG-02)
     * @param  string|null  $resayilQuoteId  Quoted message_id from X-Resayil-Quote-ID header (MSG-03)
     * @param  int|null  $companyId  Company context override (null = use constructor companyId)
     * @return array Parsed response with rooms and allocationDetails
     *
     * @throws Exception If request fails or rate not available
     */
    public function getRooms(array $params, bool $blocking = false, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array
    {
        $blockingText = $blocking ? 'with blocking' : 'without blocking';

        $this->logger->info('DOTW getRooms request initiated '.$blockingText, [
            'from_date' => $params['fromDate'] ?? null,
            'to_date' => $params['toDate'] ?? null,
            'hotel_id' => $params['productId'] ?? null,
            'blocking' => $blocking,
        ]);

        $body = $this->buildGetRoomsBody($params, $blocking);
        $xml = $this->wrapRequest('getrooms', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getRooms error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
                'blocking' => $blocking,
            ]);

            throw new Exception("DOTW getRooms error [{$errorCode}]: {$errorDetails}");
        }

        // When blocking, verify allocation is locked
        if ($blocking) {
            $this->validateBlockingStatus($response);
        }

        $rooms = $this->parseRooms($response);

        $operationType = $blocking
            ? DotwAuditService::OP_BLOCK
            : DotwAuditService::OP_RATES;

        $this->auditService->log(
            $operationType,
            $params,
            $rooms,
            $resayilMessageId,
            $resayilQuoteId,
            $companyId ?? $this->companyId
        );

        $this->logger->info('DOTW getRooms successful', [
            'room_count' => count($rooms ?? []),
            'blocking' => $blocking,
        ]);

        return $rooms;
    }

    /**
     * Confirm a hotel booking immediately
     *
     * Direct confirmation flow (vs. savebooking + bookitinerary for non-refundable)
     * Uses allocationDetails from blocking getRooms call
     *
     * @param  array  $params  Booking parameters:
     *                         - fromDate: YYYY-MM-DD
     *                         - toDate: YYYY-MM-DD
     *                         - currency: Currency code
     *                         - productId: Hotel ID
     *                         - sendCommunicationTo: Guest email
     *                         - customerReference: Your booking reference
     *                         - rooms: Array with room booking details (includes allocationDetails)
     * @param  string|null  $resayilMessageId  WhatsApp message_id from X-Resayil-Message-ID header (MSG-02)
     * @param  string|null  $resayilQuoteId  Quoted message_id from X-Resayil-Quote-ID header (MSG-03)
     * @param  int|null  $companyId  Company context override (null = use constructor companyId)
     * @return array Confirmation response with booking reference
     *
     * @throws Exception If confirmation fails
     */
    public function confirmBooking(array $params, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array
    {
        $this->logger->info('DOTW confirmBooking request initiated', [
            'hotel_id' => $params['productId'] ?? null,
            'customer_reference' => $params['customerReference'] ?? null,
            'email' => $params['sendCommunicationTo'] ?? null,
        ]);

        $body = $this->buildConfirmBookingBody($params);
        $xml = $this->wrapRequest('confirmbooking', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW confirmBooking error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW confirmBooking error [{$errorCode}]: {$errorDetails}");
        }

        $confirmation = $this->parseConfirmation($response);

        $this->auditService->log(
            DotwAuditService::OP_BOOK,
            $params,
            $confirmation,
            $resayilMessageId,
            $resayilQuoteId,
            $companyId ?? $this->companyId
        );

        $this->logger->info('DOTW confirmBooking successful', [
            'confirmation' => $confirmation,
        ]);

        return $confirmation;
    }

    /**
     * Save a booking for later confirmation
     *
     * Used for Non-Refundable Advance Purchase rates
     * Creates an itinerary that can be confirmed later with bookitinerary
     *
     * Must follow with bookitinerary() to complete the booking
     *
     * @param  array  $params  Same structure as confirmBooking
     * @param  string|null  $resayilMessageId  WhatsApp message_id from X-Resayil-Message-ID header (MSG-02)
     * @param  string|null  $resayilQuoteId  Quoted message_id from X-Resayil-Quote-ID header (MSG-03)
     * @param  int|null  $companyId  Company context override (null = use constructor companyId)
     * @return array Response with itinerary code for later confirmation
     *
     * @throws Exception If save fails
     */
    public function saveBooking(array $params, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array
    {
        $this->logger->info('DOTW saveBooking request initiated', [
            'hotel_id' => $params['productId'] ?? null,
            'customer_reference' => $params['customerReference'] ?? null,
        ]);

        $body = $this->buildSaveBookingBody($params);
        $xml = $this->wrapRequest('savebooking', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW saveBooking error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW saveBooking error [{$errorCode}]: {$errorDetails}");
        }

        $itinerary = $this->parseItinerary($response);

        $this->auditService->log(
            DotwAuditService::OP_BOOK,
            $params,
            $itinerary,
            $resayilMessageId,
            $resayilQuoteId,
            $companyId ?? $this->companyId
        );

        $this->logger->info('DOTW saveBooking successful', [
            'itinerary' => $itinerary,
        ]);

        return $itinerary;
    }

    /**
     * Confirm a previously saved itinerary
     *
     * Used to complete Non-Refundable bookings after saveBooking
     * Converts saved itinerary to confirmed booking
     *
     * @param  string  $bookingCode  Itinerary code from saveBooking response
     * @param  string|null  $resayilMessageId  WhatsApp message_id from X-Resayil-Message-ID header (MSG-02)
     * @param  string|null  $resayilQuoteId  Quoted message_id from X-Resayil-Quote-ID header (MSG-03)
     * @param  int|null  $companyId  Company context override (null = use constructor companyId)
     * @return array Confirmation response
     *
     * @throws Exception If confirmation fails
     */
    public function bookItinerary(string $bookingCode, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array
    {
        $this->logger->info('DOTW bookItinerary request initiated', [
            'booking_code' => $bookingCode,
        ]);

        $body = $this->buildBookItineraryBody($bookingCode);
        $xml = $this->wrapRequest('bookitinerary', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW bookItinerary error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
                'booking_code' => $bookingCode,
            ]);

            throw new Exception("DOTW bookItinerary error [{$errorCode}]: {$errorDetails}");
        }

        $confirmation = $this->parseConfirmation($response);

        $this->auditService->log(
            DotwAuditService::OP_BOOK,
            ['bookingCode' => $bookingCode],
            $confirmation,
            $resayilMessageId,
            $resayilQuoteId,
            $companyId ?? $this->companyId
        );

        $this->logger->info('DOTW bookItinerary successful', [
            'booking_code' => $bookingCode,
            'confirmation' => $confirmation,
        ]);

        return $confirmation;
    }

    /**
     * Cancel an existing booking
     *
     * Two-step cancellation process:
     * 1. Query with confirm=no to get cancellation charge
     * 2. Confirm cancellation with confirm=yes and penaltyApplied amount
     *
     * @param  array  $params  Cancellation parameters:
     *                         - bookingCode: DOTW booking reference
     *                         - penaltyApplied: Charge amount (only on second call with confirm=yes)
     *                         - confirm: 'yes' or 'no'
     * @return array Cancellation response with refund amount
     *
     * @throws Exception If cancellation fails
     */
    public function cancelBooking(array $params): array
    {
        $confirmText = $params['confirm'] ?? 'no';

        $this->logger->info('DOTW cancelBooking request initiated', [
            'booking_code' => $params['bookingCode'] ?? null,
            'confirm' => $confirmText,
            'penalty_applied' => $params['penaltyApplied'] ?? null,
        ]);

        $body = $this->buildCancelBookingBody($params);
        $xml = $this->wrapRequest('cancelbooking', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW cancelBooking error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
                'confirm' => $confirmText,
            ]);

            throw new Exception("DOTW cancelBooking error [{$errorCode}]: {$errorDetails}");
        }

        $result = $this->parseCancellation($response);

        $this->logger->info('DOTW cancelBooking successful', [
            'booking_code' => $params['bookingCode'] ?? null,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Delete a saved (unconfirmed) itinerary
     *
     * Removes an itinerary created by savebooking that has not yet been confirmed
     * via bookitinerary. Only for APR flow — cannot delete confirmed bookings.
     *
     * @param  string  $itineraryCode  Code returned from saveBooking
     * @return array  Deletion result
     *
     * @throws Exception  If deletion fails
     */
    public function deleteItinerary(string $itineraryCode): array
    {
        $this->logger->info('DOTW deleteItinerary request initiated', [
            'itinerary_code' => $itineraryCode,
        ]);

        $body = sprintf(
            '<bookingDetails>
      <bookingCode>%s</bookingCode>
    </bookingDetails>',
            htmlspecialchars($itineraryCode)
        );
        $xml = $this->wrapRequest('deleteitinerary', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) ($response->request->error->code ?? 'UNKNOWN');
            $errorDetails = (string) ($response->request->error->details ?? 'Unknown error');

            $this->logger->error('DOTW deleteItinerary error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
                'itinerary_code' => $itineraryCode,
            ]);

            throw new Exception("DOTW deleteItinerary error [{$errorCode}]: {$errorDetails}");
        }

        $this->logger->info('DOTW deleteItinerary successful', [
            'itinerary_code' => $itineraryCode,
        ]);

        return ['deleted' => true, 'itineraryCode' => $itineraryCode];
    }

    /**
     * Get full details of an existing booking
     *
     * Retrieves complete booking information including guest details,
     * cancellation policies, and current status
     *
     * @param  string  $bookingCode  DOTW booking reference
     * @return array Complete booking details
     *
     * @throws Exception If retrieval fails
     */
    public function getBookingDetail(string $bookingCode): array
    {
        $this->logger->info('DOTW getBookingDetail request initiated', [
            'booking_code' => $bookingCode,
        ]);

        $body = $this->buildGetBookingDetailBody($bookingCode);
        $xml = $this->wrapRequest('getbookingdetails', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getBookingDetail error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
                'booking_code' => $bookingCode,
            ]);

            throw new Exception("DOTW getBookingDetail error [{$errorCode}]: {$errorDetails}");
        }

        $details = $this->parseBookingDetail($response);

        $this->logger->info('DOTW getBookingDetail successful', [
            'booking_code' => $bookingCode,
        ]);

        return $details;
    }

    /**
     * Get list of all countries for passenger nationality/residence
     *
     * Returns country codes required for booking operations
     * Results should be cached for performance
     *
     * @return array List of countries with codes
     *
     * @throws Exception If retrieval fails
     */
    public function getCountryList(): array
    {
        $this->logger->info('DOTW getCountryList request initiated');

        $xml = $this->wrapRequest('getallcountries', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getCountryList error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW getCountryList error [{$errorCode}]: {$errorDetails}");
        }

        $countries = $this->parseCountryList($response);

        $this->logger->info('DOTW getCountryList successful', [
            'country_count' => count($countries),
        ]);

        return $countries;
    }

    /**
     * Get list of cities in a country
     *
     * @param  string  $countryCode  Country code
     * @return array List of cities
     *
     * @throws Exception If retrieval fails
     */
    public function getCityList(string $countryCode): array
    {
        $this->logger->info('DOTW getCityList request initiated', [
            'country_code' => $countryCode,
        ]);

        $body = "<bookingDetails><countryCode>{$countryCode}</countryCode></bookingDetails>";
        $xml = $this->wrapRequest('getservingcities', $body);
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getCityList error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
                'country_code' => $countryCode,
            ]);

            throw new Exception("DOTW getCityList error [{$errorCode}]: {$errorDetails}");
        }

        $cities = $this->parseCityList($response);

        $this->logger->info('DOTW getCityList successful', [
            'country_code' => $countryCode,
            'city_count' => count($cities),
        ]);

        return $cities;
    }

    /**
     * Get hotel star rating classifications
     *
     * @return array List of hotel classifications with codes
     *
     * @throws Exception If retrieval fails
     */
    public function getHotelClassifications(): array
    {
        $this->logger->info('DOTW getHotelClassifications request initiated');

        $xml = $this->wrapRequest('gethotelclassificationids', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getHotelClassifications error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW getHotelClassifications error [{$errorCode}]: {$errorDetails}");
        }

        $classifications = $this->parseClassifications($response);

        $this->logger->info('DOTW getHotelClassifications successful', [
            'classification_count' => count($classifications),
        ]);

        return $classifications;
    }

    /**
     * Get list of countries served by DOTW with hotels (getservingcountries)
     *
     * Returns only the countries for which DOTW has hotel inventory,
     * as opposed to getallcountries which returns the full internal list.
     *
     * @return array List of countries with 'code' and 'name' keys
     *
     * @throws Exception If retrieval fails
     */
    public function getServingCountries(): array
    {
        $this->logger->info('DOTW getServingCountries request initiated');

        $xml = $this->wrapRequest('getservingcountries', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getServingCountries error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW getServingCountries error [{$errorCode}]: {$errorDetails}");
        }

        $countries = $this->parseGenericCodeItems($response, 'country');

        $this->logger->info('DOTW getServingCountries successful', [
            'country_count' => count($countries),
        ]);

        return $countries;
    }

    /**
     * Get location filtering codes from DOTW (getlocationids)
     *
     * Location codes can be used as filters in hotel search requests
     * to limit results to specific geographic areas.
     *
     * @return array List of locations with 'code' and 'name' keys
     *
     * @throws Exception If retrieval fails
     */
    public function getLocationIds(): array
    {
        $this->logger->info('DOTW getLocationIds request initiated');

        $xml = $this->wrapRequest('getlocationids', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getLocationIds error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW getLocationIds error [{$errorCode}]: {$errorDetails}");
        }

        $locations = $this->parseGenericCodeItemsWithFallback($response, 'location', 'getlocationids');

        $this->logger->info('DOTW getLocationIds successful', [
            'location_count' => count($locations),
        ]);

        return $locations;
    }

    /**
     * Get amenity, leisure, and business facility codes from DOTW
     *
     * Merges results from three separate DOTW commands:
     * - getamenitiesids (category: 'amenity')
     * - getleisureids (category: 'leisure')
     * - getbusinessids (category: 'business')
     *
     * If any one call fails, a warning is logged and the others continue.
     * Partial results are returned rather than throwing on single-source failure.
     *
     * @return array List of amenities with 'code', 'name', and 'category' keys
     */
    public function getAmenityIds(): array
    {
        $this->logger->info('DOTW getAmenityIds request initiated (3 commands)');

        $merged = [];

        $commandCategoryMap = [
            'getamenitiesids' => 'amenity',
            'getleisureids'   => 'leisure',
            'getbusinessids'  => 'business',
        ];

        foreach ($commandCategoryMap as $command => $category) {
            try {
                $xml = $this->wrapRequest($command, '');
                $response = $this->post($xml);

                if ((string) $response->successful !== 'TRUE') {
                    $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
                    $this->logger->warning("DOTW {$command} returned unsuccessful response", [
                        'error_code' => $errorCode,
                        'category'   => $category,
                    ]);

                    continue;
                }

                $items = $this->parseGenericCodeItemsWithFallback($response, null, $command);

                foreach ($items as $item) {
                    $merged[] = [
                        'code'     => $item['code'],
                        'name'     => $item['name'],
                        'category' => $category,
                    ];
                }

                $this->logger->debug("DOTW {$command} parsed", [
                    'count'    => count($items),
                    'category' => $category,
                ]);
            } catch (\Exception $e) {
                $this->logger->warning("DOTW {$command} failed — continuing with other categories", [
                    'error'    => $e->getMessage(),
                    'category' => $category,
                ]);
            }
        }

        $this->logger->info('DOTW getAmenityIds complete', [
            'total_count' => count($merged),
        ]);

        return $merged;
    }

    /**
     * Get hotel preference codes from DOTW (getpreferencesids)
     *
     * Preference codes represent special hotel attributes or policies.
     *
     * @return array List of preferences with 'code' and 'name' keys
     *
     * @throws Exception If retrieval fails
     */
    public function getPreferenceIds(): array
    {
        $this->logger->info('DOTW getPreferenceIds request initiated');

        $xml = $this->wrapRequest('getpreferencesids', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getPreferenceIds error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW getPreferenceIds error [{$errorCode}]: {$errorDetails}");
        }

        $preferences = $this->parseGenericCodeItemsWithFallback($response, 'preference', 'getpreferencesids');

        $this->logger->info('DOTW getPreferenceIds successful', [
            'preference_count' => count($preferences),
        ]);

        return $preferences;
    }

    /**
     * Get hotel chain codes from DOTW (getchainids)
     *
     * Chain codes identify hotel chain affiliations (e.g. Marriott, Hilton).
     * Use these codes as filters in hotel search requests.
     *
     * @return array List of chains with 'code' and 'name' keys
     *
     * @throws Exception If retrieval fails
     */
    public function getChainIds(): array
    {
        $this->logger->info('DOTW getChainIds request initiated');

        $xml = $this->wrapRequest('getchainids', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) $response->request->error->code ?? 'UNKNOWN';
            $errorDetails = (string) $response->request->error->details ?? 'Unknown error';

            $this->logger->error('DOTW getChainIds error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW getChainIds error [{$errorCode}]: {$errorDetails}");
        }

        $chains = $this->parseGenericCodeItemsWithFallback($response, 'chain', 'getchainids');

        $this->logger->info('DOTW getChainIds successful', [
            'chain_count' => count($chains),
        ]);

        return $chains;
    }

    /**
     * Fetch salutation ID map from the DOTW API via getsalutationsids command.
     *
     * Returns an associative array keyed by lowercase salutation label mapped to
     * the numeric ID expected in confirmbooking/savebooking passenger XML.
     * Example: ['mr' => 1, 'mrs' => 2, 'miss' => 3, 'master' => 4, 'ms' => 5]
     *
     * The result is cached in $salutationMap so the API is only called once per
     * DotwService instance.
     *
     * Falls back to standard DOTW defaults if the API call fails.
     *
     * @return array<string, int> Salutation label => ID map
     */
    public function getSalutationIds(): array
    {
        if ($this->salutationMap !== null) {
            return $this->salutationMap;
        }

        $fallback = ['mr' => 1, 'mrs' => 2, 'miss' => 3, 'master' => 4, 'ms' => 5];

        $this->logger->info('DOTW getSalutationIds request initiated');

        $xml = $this->wrapRequest('getsalutationsids', '');
        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $this->logger->warning('DOTW getSalutationIds unsuccessful — using fallback map');
            $this->salutationMap = $fallback;

            return $this->salutationMap;
        }

        $map = [];
        foreach ($response->salutation->option ?? [] as $option) {
            $label = strtolower(trim((string) ($option['shortcut'] ?? '')));
            $id = (int) ($option['value'] ?? 0);
            if ($label !== '' && $id > 0) {
                $map[$label] = $id;
            }
        }

        if (empty($map)) {
            $this->logger->warning('DOTW getSalutationIds returned empty list — using fallback map');
            $this->salutationMap = $fallback;

            return $this->salutationMap;
        }

        $this->logger->info('DOTW getSalutationIds successful', ['count' => count($map)]);
        $this->salutationMap = $map;

        return $this->salutationMap;
    }

    /**
     * Search existing bookings by date range and/or customer reference.
     *
     * Wraps DOTW searchbookings XML command. Returns a flat array of booking summaries.
     * At least one of fromDate, toDate, or customerReference must be provided — enforced
     * at the GraphQL resolver layer (DotwSearchBookings).
     *
     * @param  array  $params  Optional filter keys:
     *                         - fromDate: string (YYYY-MM-DD)
     *                         - toDate: string (YYYY-MM-DD)
     *                         - customerReference: string
     * @return array List of booking summaries
     *
     * @throws Exception If DOTW returns a non-TRUE successful element
     */
    public function searchBookings(array $params = []): array
    {
        $this->logger->info('DOTW searchBookings request initiated', [
            'from_date' => $params['fromDate'] ?? null,
            'to_date' => $params['toDate'] ?? null,
            'customer_reference' => $params['customerReference'] ?? null,
        ]);

        $body = $this->buildSearchBookingsBody($params);
        $xml = $this->wrapRequest('searchbookings', $body);

        $response = $this->post($xml);

        if ((string) $response->successful !== 'TRUE') {
            $errorCode = (string) ($response->request->error->code ?? 'UNKNOWN');
            $errorDetails = (string) ($response->request->error->details ?? 'Unknown error');

            $this->logger->error('DOTW searchBookings error', [
                'error_code' => $errorCode,
                'error_details' => $errorDetails,
            ]);

            throw new Exception("DOTW searchBookings error [{$errorCode}]: {$errorDetails}");
        }

        $bookings = $this->parseSearchBookings($response);

        $this->logger->info('DOTW searchBookings successful', [
            'booking_count' => count($bookings),
        ]);

        return $bookings;
    }

    /**
     * Wrap XML request body with authentication headers
     *
     * Adds customer wrapper with MD5-hashed password as per DOTW spec
     * All elements and attributes are case-sensitive
     *
     * @param  string  $command  DOTW command name (searchhotels, getrooms, etc.)
     * @param  string  $body  XML request body (without wrapper)
     * @return string Complete XML request ready for POST
     */
    private function wrapRequest(string $command, string $body): string
    {
        return sprintf(
            '<customer>
  <username>%s</username>
  <password>%s</password>
  <id>%s</id>
  <source>%d</source>
  <product>%s</product>
  <request command="%s">%s</request>
</customer>',
            htmlspecialchars($this->username),
            $this->passwordMd5,
            htmlspecialchars($this->companyCode),
            config('dotw.request.source', 1),
            config('dotw.request.product', 'hotel'),
            htmlspecialchars($command),
            $body
        );
    }

    /**
     * Build XML body for searchhotels command
     *
     * @param  array  $params  Search parameters
     * @return string XML body
     */
    private function buildSearchHotelsBody(array $params): string
    {
        $roomsXml = $this->buildRoomsXml($params['rooms'] ?? []);

        $filtersXml = '';
        if (! empty($params['filters'])) {
            $filtersXml = $this->buildFilterXml($params['filters']);
        }

        return sprintf(
            '<bookingDetails>
      <fromDate>%s</fromDate>
      <toDate>%s</toDate>
      <currency>%s</currency>
      %s
    </bookingDetails>
    <return>
      %s
    </return>',
            htmlspecialchars($params['fromDate'] ?? ''),
            htmlspecialchars($params['toDate'] ?? ''),
            htmlspecialchars($params['currency'] ?? ''),
            $roomsXml,
            $filtersXml
        );
    }

    /**
     * Build XML body for getrooms command
     *
     * @param  array  $params  Room parameters
     * @param  bool  $blocking  Whether to perform rate blocking
     * @return string XML body
     */
    private function buildGetRoomsBody(array $params, bool $blocking = false): string
    {
        $roomsXml = '';

        if ($blocking && ! empty($params['roomTypeSelected'])) {
            // Blocking mode: add roomTypeSelected with allocationDetails
            $selected = $params['roomTypeSelected'];
            $roomsXml = sprintf(
                '<rooms no="%d">
      <room runno="0">
        <adultsCode>%d</adultsCode>
        %s
        <rateBasis>%s</rateBasis>
        <passengerNationality>%s</passengerNationality>
        <passengerCountryOfResidence>%s</passengerCountryOfResidence>
        <roomTypeSelected>
          <code>%s</code>
          <selectedRateBasis>%s</selectedRateBasis>
          <allocationDetails>%s</allocationDetails>
        </roomTypeSelected>
      </room>
    </rooms>',
                (int) ($params['rooms'][0]['no'] ?? 1),
                (int) ($params['rooms'][0]['adultsCode'] ?? 2),
                $this->buildChildrenXml($params['rooms'][0]['children'] ?? []),
                htmlspecialchars((string) ($selected['rateBasis'] ?? self::RATE_BASIS_ALL)),
                htmlspecialchars((string) ($params['rooms'][0]['passengerNationality'] ?? '')),
                htmlspecialchars((string) ($params['rooms'][0]['passengerCountryOfResidence'] ?? '')),
                htmlspecialchars((string) ($selected['code'] ?? '')),
                htmlspecialchars((string) ($selected['selectedRateBasis'] ?? self::RATE_BASIS_ALL)),
                htmlspecialchars((string) ($selected['allocationDetails'] ?? ''))
            );
        } else {
            // Browse mode: get available rates
            $roomsXml = $this->buildRoomsXml($params['rooms'] ?? []);
        }

        $fieldsXml = '';
        if (! empty($params['fields'])) {
            $fieldsXml = '<fields>';
            foreach ($params['fields'] as $field) {
                $fieldsXml .= sprintf('<roomField>%s</roomField>', htmlspecialchars($field));
            }
            $fieldsXml .= '</fields>';
        }

        // Blocking requests must NOT include roomField in the return section —
        // DOTW certification requirement: only browse requests should request roomField data
        $returnContent = $blocking ? '' : $fieldsXml;

        return sprintf(
            '<bookingDetails>
      <fromDate>%s</fromDate>
      <toDate>%s</toDate>
      <currency>%s</currency>
      %s
      <productId>%s</productId>
    </bookingDetails>
    <return>
      %s
    </return>',
            htmlspecialchars($params['fromDate'] ?? ''),
            htmlspecialchars($params['toDate'] ?? ''),
            htmlspecialchars($params['currency'] ?? ''),
            $roomsXml,
            htmlspecialchars((string) ($params['productId'] ?? '')),
            $returnContent
        );
    }

    /**
     * Build XML body for confirmbooking command
     *
     * @param  array  $params  Booking parameters
     * @return string XML body
     */
    private function buildConfirmBookingBody(array $params): string
    {
        $roomsXml = $this->buildConfirmRoomsXml($params['rooms'] ?? []);

        return sprintf(
            '<bookingDetails>
      <fromDate>%s</fromDate>
      <toDate>%s</toDate>
      <currency>%s</currency>
      <productId>%s</productId>
      <sendCommunicationTo>%s</sendCommunicationTo>
      <customerReference>%s</customerReference>
      %s
    </bookingDetails>',
            htmlspecialchars($params['fromDate'] ?? ''),
            htmlspecialchars($params['toDate'] ?? ''),
            htmlspecialchars($params['currency'] ?? ''),
            htmlspecialchars((string) ($params['productId'] ?? '')),
            htmlspecialchars($params['sendCommunicationTo'] ?? ''),
            htmlspecialchars($params['customerReference'] ?? ''),
            $roomsXml
        );
    }

    /**
     * Build XML body for savebooking command
     *
     * Same structure as confirmbooking but returns itinerary code
     *
     * @param  array  $params  Booking parameters
     * @return string XML body
     */
    private function buildSaveBookingBody(array $params): string
    {
        return $this->buildConfirmBookingBody($params);
    }

    /**
     * Build XML body for bookitinerary command
     *
     * @param  string  $bookingCode  Itinerary code from saveBooking
     * @return string XML body
     */
    private function buildBookItineraryBody(string $bookingCode): string
    {
        return sprintf(
            '<bookingDetails>
      <bookingCode>%s</bookingCode>
    </bookingDetails>',
            htmlspecialchars($bookingCode)
        );
    }

    /**
     * Build XML body for cancelbooking command
     *
     * @param  array  $params  Cancellation parameters
     * @return string XML body
     */
    private function buildCancelBookingBody(array $params): string
    {
        $penaltyXml = '';
        if (isset($params['penaltyApplied'])) {
            $penaltyXml = sprintf('<penaltyApplied>%.2f</penaltyApplied>', (float) $params['penaltyApplied']);
        }

        return sprintf(
            '<bookingDetails>
      <bookingType>%s</bookingType>
      <bookingCode>%s</bookingCode>
      <confirm>%s</confirm>
      %s
    </bookingDetails>',
            htmlspecialchars($params['bookingType'] ?? 'Hotel'),
            htmlspecialchars($params['bookingCode'] ?? ''),
            htmlspecialchars($params['confirm'] ?? 'no'),
            $penaltyXml
        );
    }

    /**
     * Build XML body for getbookingdetails command
     *
     * @param  string  $bookingCode  Booking reference
     * @return string XML body
     */
    private function buildGetBookingDetailBody(string $bookingCode): string
    {
        return sprintf(
            '<bookingDetails>
      <bookingCode>%s</bookingCode>
    </bookingDetails>',
            htmlspecialchars($bookingCode)
        );
    }

    /**
     * Build <rooms> XML element from occupancy array
     *
     * @param  array  $rooms  Room occupancy details
     * @return string XML rooms element
     */
    private function buildRoomsXml(array $rooms): string
    {
        if (empty($rooms)) {
            return '';
        }

        $roomCount = count($rooms);
        $roomsXml = sprintf('<rooms no="%d">', $roomCount);

        foreach ($rooms as $index => $room) {
            $childrenXml = $this->buildChildrenXml($room['children'] ?? []);

            $roomsXml .= sprintf(
                '<room runno="%d">
        <adultsCode>%d</adultsCode>
        %s
        <rateBasis>%d</rateBasis>
        <passengerNationality>%s</passengerNationality>
        <passengerCountryOfResidence>%s</passengerCountryOfResidence>
      </room>',
                $index,
                (int) ($room['adultsCode'] ?? 2),
                $childrenXml,
                (int) ($room['rateBasis'] ?? -1),
                htmlspecialchars((string) ($room['passengerNationality'] ?? '')),
                htmlspecialchars((string) ($room['passengerCountryOfResidence'] ?? ''))
            );
        }

        $roomsXml .= '</rooms>';

        return $roomsXml;
    }

    /**
     * Build <children> XML element
     *
     * @param  array  $children  Child ages array
     * @return string XML children element
     */
    private function buildChildrenXml(array $children): string
    {
        if (empty($children)) {
            return '<children no="0"/>';
        }

        $count = count($children);
        $childrenXml = sprintf('<children no="%d">', $count);

        foreach ($children as $index => $age) {
            $childrenXml .= sprintf(
                '<child runno="%d">%d</child>',
                $index,
                (int) $age
            );
        }

        $childrenXml .= '</children>';

        return $childrenXml;
    }

    /**
     * Build <rooms> element for confirmation with passenger details.
     *
     * VALID-03 — changedOccupancy contract:
     * When a rate has changedOccupancy (the occupancy you searched with is not what
     * DOTW will actually provide), the caller MUST pass the following fields split
     * from the validForOccupancy block returned by getRooms browse:
     *   - 'adultsCode'     => from validForOccupancy/adults   (what DOTW will book)
     *   - 'children'       => from validForOccupancy/children + childrenAges (what DOTW will book)
     *   - 'extraBed'       => from validForOccupancy/extraBed (0 if not present)
     *   - 'actualAdults'   => original search value           (what the customer searched)
     *   - 'actualChildren' => original search children array  (what the customer searched)
     * Failing to do this produces an invalid confirmbooking XML for 4+ pax bookings.
     *
     * @param  array  $rooms  Room confirmation details
     * @return string XML rooms element
     */
    private function buildConfirmRoomsXml(array $rooms): string
    {
        if (empty($rooms)) {
            return '';
        }

        $roomCount = count($rooms);
        $roomsXml = sprintf('<rooms no="%d">', $roomCount);

        foreach ($rooms as $index => $room) {
            $childrenXml = $this->buildChildrenXml($room['children'] ?? []);
            $actualChildrenXml = $this->buildActualChildrenXml($room['actualChildren'] ?? []);

            $passengersXml = $this->buildPassengersXml($room['passengers'] ?? []);

            // VALID-03: extraBed is required when validForOccupancy specifies an extra bed
            $extraBedXml = '';
            if (isset($room['extraBed']) && (int) $room['extraBed'] > 0) {
                $extraBedXml = sprintf('<extraBed>%d</extraBed>', (int) $room['extraBed']);
            }

            $specialRequestsXml = '';
            if (! empty($room['specialRequests'])) {
                $specialRequestsXml = sprintf('<specialRequests count="%d">', count($room['specialRequests']));
                foreach ($room['specialRequests'] as $i => $req) {
                    $specialRequestsXml .= sprintf(
                        '<req runno="%d">%s</req>',
                        $i,
                        htmlspecialchars($req)
                    );
                }
                $specialRequestsXml .= '</specialRequests>';
            }

            // COMPLY-02: XSD element order inside <room>:
            // roomTypeCode → selectedRateBasis → allocationDetails → adultsCode → actualAdults
            // → children → actualChildren → extraBed → passengerNationality
            // → passengerCountryOfResidence → passengersDetails → specialRequests → beddingPreference
            $roomsXml .= sprintf(
                '<room runno="%d">
        <roomTypeCode>%s</roomTypeCode>
        <selectedRateBasis>%s</selectedRateBasis>
        <allocationDetails>%s</allocationDetails>
        <adultsCode>%d</adultsCode>
        <actualAdults>%d</actualAdults>
        %s
        %s
        %s
        <passengerNationality>%s</passengerNationality>
        <passengerCountryOfResidence>%s</passengerCountryOfResidence>
        %s
        %s
        <beddingPreference>%d</beddingPreference>
      </room>',
                $index,
                htmlspecialchars((string) ($room['roomTypeCode'] ?? '')),
                htmlspecialchars((string) ($room['selectedRateBasis'] ?? '')),
                htmlspecialchars((string) ($room['allocationDetails'] ?? '')),
                (int) ($room['adultsCode'] ?? 2),
                (int) ($room['actualAdults'] ?? 2),
                $childrenXml,
                $actualChildrenXml,
                $extraBedXml,
                htmlspecialchars((string) ($room['passengerNationality'] ?? '')),
                htmlspecialchars((string) ($room['passengerCountryOfResidence'] ?? '')),
                $passengersXml,
                $specialRequestsXml,
                (int) ($room['beddingPreference'] ?? 0)
            );
        }

        $roomsXml .= '</rooms>';

        return $roomsXml;
    }

    /**
     * Build <actualChildren> XML element
     *
     * @param  array  $children  Actual child ages
     * @return string XML element
     */
    private function buildActualChildrenXml(array $children): string
    {
        if (empty($children)) {
            return '<actualChildren no="0"/>';
        }

        $count = count($children);
        $childrenXml = sprintf('<actualChildren no="%d">', $count);

        foreach ($children as $index => $age) {
            $childrenXml .= sprintf(
                '<actualChild runno="%d">%d</actualChild>',
                $index,
                (int) $age
            );
        }

        $childrenXml .= '</actualChildren>';

        return $childrenXml;
    }

    /**
     * Sanitize a passenger name for DOTW confirmbooking XML.
     *
     * COMPLY-01: DOTW requires names to be letters only, no whitespace,
     * no special characters, min 2 chars, max 25 chars.
     * Multi-word names are merged: "James Lee" → "JamesLee"
     *
     * @throws \InvalidArgumentException If name is too short after sanitization
     */
    private function sanitizePassengerName(string $name): string
    {
        // Step 1: Strip all whitespace (merges multi-word names: "James Lee" → "JamesLee")
        $sanitized = preg_replace('/\s+/', '', $name) ?? '';

        // Step 2: Remove all non-alphabetic characters (letters only, no numbers, no special chars)
        $sanitized = preg_replace('/[^A-Za-z]/', '', $sanitized) ?? '';

        // Step 3: Enforce minimum length
        if (strlen($sanitized) < 2) {
            throw new \InvalidArgumentException(
                "Passenger name '{$name}' is too short after sanitization (min 2 chars)"
            );
        }

        // Step 4: Enforce maximum length (truncate — DOTW spec says max 25)
        if (strlen($sanitized) > 25) {
            $sanitized = substr($sanitized, 0, 25);
        }

        return $sanitized;
    }

    /**
     * Build <passengersDetails> XML element
     *
     * Salutation IDs in each passenger array should come from getSalutationIds()
     * to comply with DOTW certification requirement (DOTW-FIX-01).
     * Falls back to 1 (Mr) if not provided — callers should resolve IDs dynamically.
     *
     * @param  array  $passengers  Passenger details
     * @return string XML element
     */
    private function buildPassengersXml(array $passengers): string
    {
        if (empty($passengers)) {
            return '';
        }

        $passengersXml = '<passengersDetails>';

        foreach ($passengers as $index => $passenger) {
            $isLeading = $index === 0 ? 'yes' : 'no';

            $passengersXml .= sprintf(
                '<passenger leading="%s">
          <salutation>%d</salutation>
          <firstName>%s</firstName>
          <lastName>%s</lastName>
        </passenger>',
                $isLeading,
                // Salutation IDs sourced from getsalutationsids API (see getSalutationIds())
                (int) ($passenger['salutation'] ?? 1),
                htmlspecialchars($this->sanitizePassengerName((string) ($passenger['firstName'] ?? ''))),
                htmlspecialchars($this->sanitizePassengerName((string) ($passenger['lastName'] ?? '')))
            );
        }

        $passengersXml .= '</passengersDetails>';

        return $passengersXml;
    }

    /**
     * Build filter XML for searchhotels
     *
     * Supports complex conditions with atomic conditions
     *
     * @param  array  $filters  Filter specifications
     * @return string XML filters element
     */
    private function buildFilterXml(array $filters): string
    {
        $filtersXml = '<filters xmlns:a="http://us.dotwconnect.com/xsd/atomicCondition" xmlns:c="http://us.dotwconnect.com/xsd/complexCondition">';

        if (isset($filters['city'])) {
            $filtersXml .= sprintf('<city>%s</city>', htmlspecialchars($filters['city']));
        }

        if (isset($filters['conditions']) && is_array($filters['conditions'])) {
            $filtersXml .= '<c:condition>';

            foreach ($filters['conditions'] as $condition) {
                $filtersXml .= '<a:condition>';
                $filtersXml .= sprintf('<fieldName>%s</fieldName>', htmlspecialchars($condition['fieldName'] ?? ''));
                $filtersXml .= sprintf('<fieldTest>%s</fieldTest>', htmlspecialchars($condition['fieldTest'] ?? 'equals'));

                if (! empty($condition['fieldValues'])) {
                    $filtersXml .= '<fieldValues>';
                    foreach ($condition['fieldValues'] as $value) {
                        $filtersXml .= sprintf('<fieldValue>%s</fieldValue>', htmlspecialchars((string) $value));
                    }
                    $filtersXml .= '</fieldValues>';
                }

                $filtersXml .= '</a:condition>';
            }

            $filtersXml .= '</c:condition>';
        }

        $filtersXml .= '</filters>';

        return $filtersXml;
    }

    /**
     * Send XML POST request to DOTW API
     *
     * Handles gzip compression, timeouts, and error responses
     *
     * @param  string  $xml  XML request body
     * @return SimpleXMLElement Parsed response
     *
     * @throws Exception If request fails or response is invalid XML
     */
    private function post(string $xml): SimpleXMLElement
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
                'Connection' => 'close',
                // VALID-04: gzip compression required on all DOTW requests (certification test 12)
                'Accept-Encoding' => 'gzip, deflate',
            ])
                ->timeout($this->timeout)
                ->connectTimeout(30)
                ->withOptions(['decode_content' => true])
                ->post($this->baseUrl, $xml);

            $this->logger->debug('DOTW API response received', [
                'status' => $response->status(),
                'body_length' => strlen($response->body()),
            ]);

            if (! $response->successful()) {
                $this->logger->error('DOTW API HTTP error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                throw new Exception("DOTW API HTTP {$response->status()}: ".substr($response->body(), 0, 200));
            }

            $simpleXml = simplexml_load_string($response->body());

            if (! $simpleXml) {
                $this->logger->error('DOTW API response is not valid XML', [
                    'body' => substr($response->body(), 0, 500),
                ]);

                throw new Exception('DOTW API response is not valid XML');
            }

            return $simpleXml;
        } catch (ConnectionException $e) {
            $this->logger->error('DOTW API timeout', [
                'timeout_seconds' => $this->timeout,
                'company_id' => $this->companyId,
            ]);
            throw new DotwTimeoutException(
                "DOTW API timeout after {$this->timeout}s",
                0,
                $e
            );
        } catch (Exception $e) {
            $this->logger->error('DOTW API request failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validates that each room's rateBasis <status> child element equals "checked"
     * after blocking getRooms. DOTW returns status as an XML element (<status>checked</status>),
     * not an attribute. Using attribute access ($rateBasis['status']) would silently return
     * empty string and accept unavailable rates — that is the VALID-01 bug this method corrects.
     *
     * @param  SimpleXMLElement  $response  XML response from getRooms with blocking
     *
     * @throws Exception If blocking failed (status element is not "checked")
     */
    private function validateBlockingStatus(SimpleXMLElement $response): void
    {
        $rooms = $response->xpath('//room');

        if (empty($rooms)) {
            return;
        }

        foreach ($rooms as $room) {
            $rateBases = $room->xpath('.//rateBasis');

            foreach ($rateBases as $rateBasis) {
                // VALID-01: read <status> as a child element, not an XML attribute.
                // DOTW returns <status>checked</status> inside <rateBasis>, not status="checked".
                $status = (string) ($rateBasis->status ?? 'unchecked');

                if ($status !== 'checked') {
                    throw new Exception(
                        'Rate blocking failed. Rate is no longer available (status: '.$status.'). Please search again.'
                    );
                }
            }
        }
    }

    /**
     * Parse hotel search results from response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Parsed hotels array
     */
    private function parseHotels(SimpleXMLElement $response): array
    {
        $hotels = [];
        $hotelElements = $response->xpath('//hotel');

        foreach ($hotelElements as $hotel) {
            $hotelData = [
                'hotelId' => (string) $hotel['hotelid'],
                'rooms' => [],
            ];

            $roomElements = $hotel->xpath('.//room');

            foreach ($roomElements as $room) {
                $roomData = [
                    'adults' => (string) $room['adults'],
                    'children' => (string) $room['children'],
                    'childrenAges' => (string) $room['childrenages'],
                    'roomTypes' => [],
                ];

                $roomTypeElements = $room->xpath('.//roomType');

                foreach ($roomTypeElements as $roomType) {
                    $rateBasisElements = $roomType->xpath('.//rateBasis');

                    foreach ($rateBasisElements as $rateBasis) {
                        $roomData['roomTypes'][] = [
                            'code' => (string) $roomType['roomtypecode'],
                            'name' => (string) $roomType->n,
                            'rateBasisId' => (string) $rateBasis['id'],
                            'rateType' => (string) $rateBasis->rateType['currencyid'] ?? '',
                            'nonRefundable' => (string) $rateBasis->rateType['nonrefundable'] ?? 'no',
                            'total' => (float) $rateBasis->total,
                            'totalTaxes' => (float) ($rateBasis->totalTaxes ?? 0),
                            'totalMinimumSelling' => (float) ($rateBasis->totalMinimumSelling ?? 0),
                        ];
                    }
                }

                $hotelData['rooms'][] = $roomData;
            }

            $hotels[] = $hotelData;
        }

        return $hotels;
    }

    /**
     * Parse room details from getRooms response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Parsed rooms array with keys:
     *   - roomTypeCode (string)
     *   - roomName (string)
     *   - specials (string[]) — promotional specials at roomType level (COMPLY-04)
     *   - details (array[]) — one entry per rateBasis, each with:
     *       id, status, price, taxes, allocationDetails, cancellationRules,
     *       tariffNotes (string, COMPLY-03),
     *       specialsApplied (string[], COMPLY-04),
     *       minStay (string, COMPLY-06),
     *       dateApplyMinStay (string, COMPLY-06),
     *       propertyFees (array[], COMPLY-07)
     */
    private function parseRooms(SimpleXMLElement $response): array
    {
        $rooms = [];
        $roomElements = $response->xpath('//room');

        foreach ($roomElements as $room) {
            $roomData = [
                'roomTypeCode' => (string) $room->roomType['roomtypecode'] ?? '',
                'roomName' => (string) $room->roomType->n ?? '',
                'details' => [],
            ];

            $rateBasisElements = $room->xpath('.//rateBasis');

            foreach ($rateBasisElements as $rateBasis) {
                // COMPLY-04: specialsApplied — list of promotions applied at rateBasis level
                $specialsApplied = [];
                if (isset($rateBasis->specialsApplied->special)) {
                    foreach ($rateBasis->specialsApplied->special as $s) {
                        $specialsApplied[] = (string) $s;
                    }
                }

                // COMPLY-07: propertyFees — fees listed at rateBasis level (e.g. resort fee, city tax)
                $propertyFees = [];
                if (isset($rateBasis->propertyFees) && (int) ($rateBasis->propertyFees['count'] ?? 0) > 0) {
                    foreach ($rateBasis->propertyFees->propertyFee as $fee) {
                        $propertyFees[] = [
                            'name' => (string) ($fee->name ?? ''),
                            'includedInPrice' => strtolower((string) ($fee['includedinprice'] ?? 'no')) === 'yes',
                            'currencyShort' => (string) ($fee->currencyShort ?? ''),
                        ];
                    }
                }

                $roomData['details'][] = [
                    'id' => (string) $rateBasis['id'],
                    'status' => (string) ($rateBasis['status'] ?? 'unknown'),
                    'price' => (float) ($rateBasis->total ?? 0),
                    'taxes' => (float) ($rateBasis->totalTaxes ?? 0),
                    'allocationDetails' => (string) ($rateBasis->allocationDetails ?? ''),
                    'cancellationRules' => $this->parseCancellationRules($rateBasis),
                    // COMPLY-03: tariffNotes — rate basis conditions/notes
                    'tariffNotes' => (string) ($rateBasis->tariffNotes ?? ''),
                    // COMPLY-04: specials applied to this rate basis
                    'specialsApplied' => $specialsApplied,
                    // COMPLY-06: minimum stay requirement for this rate basis
                    'minStay' => (string) ($rateBasis->minStay ?? ''),
                    'dateApplyMinStay' => (string) ($rateBasis->dateApplyMinStay ?? ''),
                    // COMPLY-07: property fees at rateBasis level
                    'propertyFees' => $propertyFees,
                ];
            }

            // COMPLY-04: specials at roomType level — promotional offers for this room type
            $specials = [];
            if (isset($room->roomType[0]->specials->special)) {
                foreach ($room->roomType[0]->specials->special as $s) {
                    $specials[] = (string) $s;
                }
            }
            $roomData['specials'] = $specials;

            $rooms[] = $roomData;
        }

        return $rooms;
    }

    /**
     * Parse cancellation rules from rate basis
     *
     * @param  SimpleXMLElement  $rateBasis  Rate basis element
     * @return array Parsed rules, each with keys:
     *   - fromDate (string)
     *   - toDate (string)
     *   - charge (float)
     *   - cancelCharge (float)
     *   - cancelRestricted (bool) — cancellation not permitted (COMPLY-05)
     *   - amendRestricted (bool) — amendment not permitted (COMPLY-05)
     */
    private function parseCancellationRules(SimpleXMLElement $rateBasis): array
    {
        $rules = [];
        $ruleElements = $rateBasis->xpath('.//rule');

        foreach ($ruleElements as $rule) {
            $rules[] = [
                'fromDate' => (string) ($rule->fromDate ?? ''),
                'toDate' => (string) ($rule->toDate ?? ''),
                'charge' => (float) ($rule->charge ?? 0),
                'cancelCharge' => (float) ($rule->cancelCharge ?? 0),
                // COMPLY-05: restriction flags — 'yes'/'no' in DOTW XML, mapped to bool
                'cancelRestricted' => strtolower((string) ($rule->cancelRestricted ?? 'no')) === 'yes',
                'amendRestricted' => strtolower((string) ($rule->amendRestricted ?? 'no')) === 'yes',
            ];
        }

        return $rules;
    }

    /**
     * Parse confirmation response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Confirmation data
     */
    private function parseConfirmation(SimpleXMLElement $response): array
    {
        return [
            'bookingCode' => (string) ($response->bookingCode ?? ''),
            'confirmationNumber' => (string) ($response->confirmationNumber ?? ''),
            'status' => (string) ($response->status ?? 'confirmed'),
            'paymentGuaranteedBy' => (string) ($response->paymentGuaranteedBy ?? ''),
        ];
    }

    /**
     * Parse itinerary response (from saveBooking)
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Itinerary data
     */
    private function parseItinerary(SimpleXMLElement $response): array
    {
        return [
            'itineraryCode' => (string) ($response->itineraryCode ?? ''),
            'status' => (string) ($response->status ?? 'saved'),
        ];
    }

    /**
     * Parse cancellation response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Cancellation result
     */
    private function parseCancellation(SimpleXMLElement $response): array
    {
        return [
            'bookingCode' => (string) ($response->bookingCode ?? ''),
            'refund' => (float) ($response->refund ?? 0),
            'charge' => (float) ($response->charge ?? 0),
            'status' => (string) ($response->status ?? ''),
        ];
    }

    /**
     * Parse booking detail response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Booking details with schema-required keys:
     *   - bookingCode       (string) DOTW booking reference
     *   - hotelCode         (string) DOTW hotel code
     *   - fromDate          (string) check-in date
     *   - toDate            (string) check-out date
     *   - status            (string) booking status
     *   - customerReference (string) customer's own reference
     *   - totalAmount       (float)  total booking amount
     *   - currency          (string) currency code
     *   - passengerDetails  (array)  list of passenger objects (firstName, lastName, type)
     *   — Backward-compat aliases (hotelName, checkIn, checkOut, totalPrice) also returned
     */
    private function parseBookingDetail(SimpleXMLElement $response): array
    {
        // Parse passenger details from DOTW XML — structure varies; capture as array
        $passengers = [];
        foreach ($response->xpath('//passenger') as $pax) {
            $passengers[] = [
                'firstName' => (string) ($pax->firstName ?? ''),
                'lastName'  => (string) ($pax->lastName ?? ''),
                'type'      => (string) ($pax->type ?? 'adult'),
            ];
        }

        return [
            // Schema-required fields (BookingDetails non-null contract)
            'bookingCode'       => (string) ($response->bookingCode ?? ''),
            'hotelCode'         => (string) ($response->hotelCode ?? ''),
            'fromDate'          => (string) ($response->fromDate ?? ''),
            'toDate'            => (string) ($response->toDate ?? ''),
            'status'            => (string) ($response->status ?? ''),
            'customerReference' => (string) ($response->customerReference ?? ''),
            'totalAmount'       => (float)  ($response->totalAmount ?? $response->totalPrice ?? 0),
            'currency'          => (string) ($response->currency ?? ''),
            'passengerDetails'  => $passengers,

            // Backward-compat aliases (keep existing callers unbroken)
            'hotelName'  => (string) ($response->hotelName ?? ''),
            'checkIn'    => (string) ($response->fromDate ?? $response->checkIn ?? ''),
            'checkOut'   => (string) ($response->toDate ?? $response->checkOut ?? ''),
            'totalPrice' => (float)  ($response->totalAmount ?? $response->totalPrice ?? 0),
        ];
    }

    /**
     * Parse country list response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Countries array
     */
    private function parseCountryList(SimpleXMLElement $response): array
    {
        $countries = [];
        $countryElements = $response->xpath('//country');

        foreach ($countryElements as $country) {
            $countries[] = [
                'code' => (string) $country['code'] ?? '',
                'name' => (string) $country ?? '',
            ];
        }

        return $countries;
    }

    /**
     * Parse city list response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Cities array
     */
    private function parseCityList(SimpleXMLElement $response): array
    {
        $cities = [];
        $cityElements = $response->xpath('//city');

        foreach ($cityElements as $city) {
            $cities[] = [
                'code' => (string) $city['code'] ?? '',
                'name' => (string) $city ?? '',
            ];
        }

        return $cities;
    }

    /**
     * Parse hotel classifications response
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Classifications array
     */
    private function parseClassifications(SimpleXMLElement $response): array
    {
        $classifications = [];
        $classElements = $response->xpath('//classification');

        foreach ($classElements as $class) {
            $classifications[] = [
                'id' => (string) $class['id'] ?? '',
                'name' => (string) $class ?? '',
            ];
        }

        return $classifications;
    }

    /**
     * Build XML body for searchbookings command
     *
     * @param  array  $params  Filter parameters (fromDate, toDate, customerReference)
     * @return string XML body
     */
    private function buildSearchBookingsBody(array $params): string
    {
        $parts = '';

        if (! empty($params['fromDate'])) {
            $parts .= sprintf('<fromDate>%s</fromDate>', htmlspecialchars($params['fromDate']));
        }

        if (! empty($params['toDate'])) {
            $parts .= sprintf('<toDate>%s</toDate>', htmlspecialchars($params['toDate']));
        }

        if (! empty($params['customerReference'])) {
            $parts .= sprintf('<customerReference>%s</customerReference>', htmlspecialchars($params['customerReference']));
        }

        return "<bookingDetails>{$parts}</bookingDetails>";
    }

    /**
     * Parse searchbookings response into flat booking summary array
     *
     * @param  SimpleXMLElement  $response  XML response
     * @return array Booking summaries
     */
    private function parseSearchBookings(SimpleXMLElement $response): array
    {
        $bookings = [];

        $bookingElements = $response->bookings->booking ?? [];

        foreach ($bookingElements as $booking) {
            $bookings[] = [
                'bookingCode' => (string) ($booking->bookingCode ?? ''),
                'customerReference' => (string) ($booking->customerReference ?? ''),
                'status' => (string) ($booking->status ?? ''),
                'hotelId' => (string) ($booking->hotelId ?? ''),
                'fromDate' => (string) ($booking->fromDate ?? ''),
                'toDate' => (string) ($booking->toDate ?? ''),
                'totalAmount' => (float) ($booking->totalAmount ?? 0),
                'currency' => (string) ($booking->currency ?? ''),
            ];
        }

        return $bookings;
    }

    /**
     * Generic parser for DOTW reference lists where each item has a 'code' attribute
     * and the element text is the name.
     *
     * Used for getservingcountries, etc. where the element name is predictable.
     *
     * @param  SimpleXMLElement  $response  Full XML response
     * @param  string  $elementTag  Element tag name to search for (e.g. 'country', 'location')
     * @return array Items with 'code' and 'name' keys
     */
    private function parseGenericCodeItems(SimpleXMLElement $response, string $elementTag): array
    {
        $items = [];
        $elements = $response->xpath("//{$elementTag}");

        foreach ($elements as $element) {
            $code = (string) ($element['code'] ?? '');
            $name = (string) $element;

            if ($code !== '') {
                $items[] = [
                    'code' => $code,
                    'name' => $name,
                ];
            }
        }

        return $items;
    }

    /**
     * Generic parser for DOTW reference lists with fallback xpath strategy.
     *
     * Tries a named element tag first (if provided), then falls back to searching
     * all elements that have a 'code' attribute. Logs at DEBUG level when fallback
     * is used, so XML structure can be investigated.
     *
     * Used for getlocationids, getpreferencesids, getchainids, and the three
     * amenity commands (getamenitiesids, getleisureids, getbusinessids) whose
     * exact XML element names are not documented in SKILL.md.
     *
     * @param  SimpleXMLElement  $response  Full XML response
     * @param  string|null  $elementTag  Preferred element tag to search, or null for fallback-only
     * @param  string  $commandName  DOTW command name used for debug logging
     * @return array Items with 'code' and 'name' keys
     */
    private function parseGenericCodeItemsWithFallback(
        SimpleXMLElement $response,
        ?string $elementTag,
        string $commandName
    ): array {
        $items = [];

        // Try the named element first if provided
        if ($elementTag !== null) {
            $elements = $response->xpath("//{$elementTag}");

            if (! empty($elements)) {
                foreach ($elements as $element) {
                    $code = (string) ($element['code'] ?? '');
                    $name = (string) ($element->name ?? $element);

                    if ($code !== '') {
                        $items[] = [
                            'code' => $code,
                            'name' => $name,
                        ];
                    }
                }

                return $items;
            }
        }

        // Fallback: find any element with a 'code' attribute
        $elements = $response->xpath('//*[@code]');

        if (empty($elements)) {
            $this->logger->debug("DOTW {$commandName} — no elements with 'code' attribute found in response", [
                'response_xml' => $response->asXML(),
            ]);

            return [];
        }

        $this->logger->debug("DOTW {$commandName} — using xpath fallback parser", [
            'element_count' => count($elements),
        ]);

        foreach ($elements as $element) {
            $code = (string) ($element['code'] ?? '');
            $name = (string) ($element->name ?? $element->n ?? $element);

            if ($code !== '') {
                $items[] = [
                    'code' => $code,
                    'name' => $name,
                ];
            }
        }

        return $items;
    }
}
