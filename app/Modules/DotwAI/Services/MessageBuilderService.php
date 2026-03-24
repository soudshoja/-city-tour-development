<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\Models\DotwAIBooking;

/**
 * WhatsApp message formatting service for DotwAI module.
 *
 * Pure functions (all static) for building bilingual Arabic/English
 * WhatsApp-ready text messages. No state, no dependencies.
 *
 * Every REST endpoint returns a pre-formatted whatsappMessage via these
 * methods so n8n AI agents can forward them directly to users.
 *
 * @see EVNT-02 Every REST response includes whatsappMessage
 */
class MessageBuilderService
{
    /**
     * Separator line for WhatsApp messages.
     */
    private const SEPARATOR = "──────────────────────────────";

    /**
     * Star character for hotel rating display.
     */
    private const STAR = '*';

    /**
     * Format search results as a bilingual Arabic/English numbered list.
     *
     * Example output:
     * ```
     * Search Results | نتائج البحث
     * ──────────────────────────────
     * 1. Hilton Dubai Creek
     *    ***** | Dubai
     *    KWD 45.000 - Breakfast
     *    Refundable | قابل للاسترداد
     * ```
     *
     * @param array<int, array{option_number: int, name: string, star_rating: ?int,
     *              city: string, cheapest_price: float, meal_type: string,
     *              is_refundable: bool, currency: string}> $hotels
     * @param string $currency Display currency symbol
     * @return string Formatted WhatsApp message
     */
    public static function formatSearchResults(array $hotels, string $currency): string
    {
        if (empty($hotels)) {
            return self::formatError(DotwAIResponse::NO_RESULTS, 'No hotels found');
        }

        $lines = [];
        $lines[] = "نتائج البحث | Search Results";
        $lines[] = self::SEPARATOR;

        foreach ($hotels as $hotel) {
            $number = $hotel['option_number'] ?? 0;
            $name = $hotel['name'] ?? 'Unknown Hotel';
            $stars = isset($hotel['star_rating']) && $hotel['star_rating'] > 0
                ? str_repeat(self::STAR, (int) $hotel['star_rating'])
                : '';
            $city = $hotel['city'] ?? '';
            $price = number_format((float) ($hotel['cheapest_price'] ?? 0), 3);
            $mealType = $hotel['meal_type'] ?? 'Room Only';
            $refundable = ($hotel['is_refundable'] ?? false)
                ? "قابل للاسترداد | Refundable"
                : "غير قابل للاسترداد | Non-Refundable";

            $lines[] = "";
            $lines[] = "{$number}. {$name}";

            $starCity = [];
            if (!empty($stars)) {
                $starCity[] = $stars;
            }
            if (!empty($city)) {
                $starCity[] = $city;
            }
            if (!empty($starCity)) {
                $lines[] = "   " . implode(' | ', $starCity);
            }

            $lines[] = "   {$currency} {$price} - {$mealType}";
            $lines[] = "   {$refundable}";
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;

        // Count metadata -- use first/last hotel option numbers for showing range
        $total = count($hotels);
        $firstHotel = $hotels[0] ?? null;
        $totalFound = $firstHotel['_total_found'] ?? $total;

        $lines[] = "عرض {$total} من {$totalFound} نتيجة";
        $lines[] = "Showing {$total} of {$totalFound} results";
        $lines[] = "";
        $lines[] = "للتفاصيل اكتب رقم الفندق";
        $lines[] = "For details, type the hotel number";

        return implode("\n", $lines);
    }

    /**
     * Format hotel details with room list.
     *
     * Example output:
     * ```
     * Hotel Details | تفاصيل الفندق
     * ──────────────────────────────
     * Hilton Dubai Creek - *****
     * Dubai
     *
     * Available Rooms | الغرف المتاحة:
     *
     * 1. Deluxe Room
     *    Breakfast | وجبة الإفطار
     *    KWD 45.000 / per night | ليلة
     *    Refundable | قابل للاسترداد
     *    Cancel by | آخر موعد للإلغاء: 2026-04-01
     * ```
     *
     * @param array  $hotel    Hotel info array
     * @param array  $rooms    Room details array
     * @param string $currency Display currency symbol
     * @return string Formatted WhatsApp message
     */
    public static function formatHotelDetails(array $hotel, array $rooms, string $currency): string
    {
        $lines = [];
        $lines[] = "تفاصيل الفندق | Hotel Details";
        $lines[] = self::SEPARATOR;

        $hotelName = $hotel['name'] ?? 'Hotel';
        $stars = isset($hotel['star_rating']) && $hotel['star_rating'] > 0
            ? ' - ' . str_repeat(self::STAR, (int) $hotel['star_rating'])
            : '';
        $lines[] = "{$hotelName}{$stars}";

        if (!empty($hotel['address'])) {
            $lines[] = $hotel['address'];
        }
        if (!empty($hotel['city'])) {
            $lines[] = $hotel['city'];
        }

        $lines[] = "";
        $lines[] = "الغرف المتاحة | Available Rooms:";

        foreach ($rooms as $index => $room) {
            $number = $index + 1;
            $roomName = $room['room_name'] ?? 'Room';
            $mealType = $room['meal_type'] ?? 'Room Only';
            $mealArabic = self::getMealTypeArabic($mealType);
            $price = number_format((float) ($room['display_price'] ?? $room['price'] ?? 0), 3);
            $refundable = ($room['is_refundable'] ?? true)
                ? "قابل للاسترداد | Refundable"
                : "غير قابل للاسترداد | Non-Refundable";

            $lines[] = "";
            $lines[] = "{$number}. {$roomName}";
            $lines[] = "   {$mealArabic} | {$mealType}";
            $lines[] = "   {$currency} {$price} / ليلة | per night";
            $lines[] = "   {$refundable}";

            // Cancellation deadline
            $cancelRules = $room['cancellation_rules'] ?? [];
            if (!empty($cancelRules)) {
                $firstRule = $cancelRules[0];
                $cancelDate = $firstRule['fromDate'] ?? '';
                if (!empty($cancelDate) && !($firstRule['cancelRestricted'] ?? false)) {
                    $lines[] = "   آخر موعد للإلغاء | Cancel by: {$cancelDate}";
                }
            }

            // Tariff notes
            if (!empty($room['tariff_notes'])) {
                $lines[] = "   " . $room['tariff_notes'];
            }

            // Specials
            if (!empty($room['specials'])) {
                foreach ($room['specials'] as $special) {
                    $specialDesc = $special['description'] ?? $special['name'] ?? '';
                    if (!empty($specialDesc)) {
                        $lines[] = "   عرض خاص | Special: {$specialDesc}";
                    }
                }
            }

            // Min stay
            if (!empty($room['min_stay'])) {
                $minStay = (int) $room['min_stay'];
                $lines[] = "   الحد الأدنى للإقامة | Min stay: {$minStay} " . ($minStay === 1 ? 'night' : 'nights');
            }
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "للحجز اكتب رقم الغرفة";
        $lines[] = "To book, type the room number";

        return implode("\n", $lines);
    }

    /**
     * Format a city list as a simple numbered list.
     *
     * @param array<int, array{code: string, name: string, country_code: string}> $cities
     * @return string Formatted WhatsApp message
     */
    public static function formatCityList(array $cities): string
    {
        if (empty($cities)) {
            return self::formatError(DotwAIResponse::CITY_NOT_FOUND, 'No cities found');
        }

        $lines = [];
        $lines[] = "المدن المتاحة | Available Cities";
        $lines[] = self::SEPARATOR;

        foreach ($cities as $index => $city) {
            $number = $index + 1;
            $name = $city['name'] ?? '';
            $lines[] = "{$number}. {$name}";
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "عدد المدن: " . count($cities);
        $lines[] = "Total cities: " . count($cities);

        return implode("\n", $lines);
    }

    /**
     * Format an error message as bilingual Arabic/English text.
     *
     * Maps error codes to user-friendly messages. Falls back to
     * a generic error message for unknown codes.
     *
     * @param string $code           Error code from DotwAIResponse constants
     * @param string $defaultMessage Fallback message if code not mapped
     * @return string Formatted WhatsApp error message
     */
    public static function formatError(string $code, string $defaultMessage = ''): string
    {
        $messages = [
            DotwAIResponse::CITY_NOT_FOUND => "لم نجد هذه المدينة | City not found. Please check the city name.",
            DotwAIResponse::NO_RESULTS => "لا توجد نتائج | No hotels available for these dates.",
            DotwAIResponse::DOTW_API_ERROR => "حدث خطأ | Service temporarily unavailable. Please try again.",
            DotwAIResponse::HOTEL_NOT_FOUND => "لم نجد هذا الفندق | Hotel not found. Please try a different name.",
            DotwAIResponse::PHONE_NOT_FOUND => "لم نتمكن من التعرف على رقمك | Phone number not recognized.",
            DotwAIResponse::VALIDATION_ERROR => "البيانات المدخلة غير صحيحة | Invalid input. Please check your data.",
            DotwAIResponse::CREDENTIALS_NOT_FOUND => "خدمة الحجز غير متاحة | Booking service not configured.",
            DotwAIResponse::TRACK_DISABLED => "هذه الخدمة غير متاحة | This service is currently unavailable.",
            'INTERNAL_ERROR' => "حدث خطأ غير متوقع | An unexpected error occurred. Please try again.",
        ];

        return $messages[$code] ?? (!empty($defaultMessage) ? $defaultMessage : "حدث خطأ | An error occurred.");
    }

    /**
     * Format a prebook confirmation message (Phase 19 -- after rate lock, before confirm).
     *
     * Example output:
     * ```
     * تم حجز الفندق | Booking Reserved
     * ──────────────────────────────
     * Hotel: Hilton Dubai Creek
     * Dates: 2026-04-10 → 2026-04-15
     * Price: KWD 225.000
     * Status: Refundable | قابل للاسترداد
     * Cancellation Deadline: 2026-04-05
     * Booking Ref: DOTWAI-550E8400-...
     * ```
     *
     * @param array $prebook Prebook result array from BookingService::prebook
     * @return string Formatted WhatsApp message
     */
    public static function formatPrebookConfirmation(array $prebook): string
    {
        $lines = [];
        $lines[] = "تم تثبيت الحجز | Booking Reserved";
        $lines[] = self::SEPARATOR;

        $lines[] = "Hotel | الفندق: " . ($prebook['hotel_name'] ?? 'N/A');
        $lines[] = "Dates | التواريخ: " . ($prebook['check_in'] ?? '') . " → " . ($prebook['check_out'] ?? '');

        $currency = $prebook['currency'] ?? 'KWD';
        $price = number_format((float) ($prebook['display_total_fare'] ?? 0), 3);
        $lines[] = "Price | السعر: {$currency} {$price}";

        $isRefundable = $prebook['is_refundable'] ?? true;
        $isApr = $prebook['is_apr'] ?? false;
        if ($isRefundable) {
            $lines[] = "Status | الحالة: Refundable | قابل للاسترداد";
        } else {
            $label = $isApr ? 'Non-Refundable (APR) | غير قابل للاسترداد (سعر مسبق)' : 'Non-Refundable | غير قابل للاسترداد';
            $lines[] = "Status | الحالة: {$label}";
        }

        $deadline = $prebook['cancellation_deadline'] ?? null;
        $lines[] = "Cancellation Deadline | آخر موعد للإلغاء: " . ($deadline ?? 'N/A');

        $lines[] = "Booking Ref | رقم الحجز: " . ($prebook['prebook_key'] ?? 'N/A');

        $lines[] = self::SEPARATOR;

        $needsPayment = $prebook['needs_payment'] ?? false;
        if ($needsPayment) {
            $lines[] = "للمتابعة يرجى الدفع أولاً";
            $lines[] = "To proceed, payment is required.";
        } else {
            $lines[] = "للتأكيد يرجى تقديم بيانات النزلاء";
            $lines[] = "To confirm, please provide guest details.";
        }

        return implode("\n", $lines);
    }

    /**
     * Format a booking confirmation message (Phase 19 -- after successful DOTW confirmation).
     *
     * Example output:
     * ```
     * تم تأكيد الحجز | Booking Confirmed
     * ──────────────────────────────
     * Confirmation No: DOTW-12345678
     * Hotel: Hilton Dubai Creek
     * Dates: 2026-04-10 → 2026-04-15
     * Guests: Mr. John Smith
     * ```
     *
     * @param array $confirmation Confirmation data from BookingService
     * @return string Formatted WhatsApp message
     */
    public static function formatBookingConfirmation(array $confirmation): string
    {
        $lines = [];
        $lines[] = "تم تأكيد الحجز | Booking Confirmed";
        $lines[] = self::SEPARATOR;

        $lines[] = "Confirmation No | رقم التأكيد: " . ($confirmation['confirmation_no'] ?? 'N/A');

        if (!empty($confirmation['booking_ref'])) {
            $lines[] = "DOTW Ref | المرجع: " . $confirmation['booking_ref'];
        }

        $lines[] = "Hotel | الفندق: " . ($confirmation['hotel_name'] ?? 'N/A');
        $lines[] = "Dates | التواريخ: " . ($confirmation['check_in'] ?? '') . " → " . ($confirmation['check_out'] ?? '');

        $guests = $confirmation['guest_details'] ?? [];
        if (!empty($guests)) {
            $lines[] = "";
            $lines[] = "Guests | النزلاء:";
            foreach ($guests as $guest) {
                $salutation = $guest['salutation'] ?? 'Mr';
                $name = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                $lines[] = "  - {$salutation}. {$name}";
            }
        }

        if (!empty($confirmation['payment_guaranteed_by'])) {
            $lines[] = "";
            $lines[] = "Payment Guaranteed By | ضامن الدفع: " . $confirmation['payment_guaranteed_by'];
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "سيتم إرسال الفاوتشر قريباً";
        $lines[] = "Voucher will be sent shortly.";

        return implode("\n", $lines);
    }

    /**
     * Format a credit balance summary message (Phase 19 -- B2B balance query).
     *
     * Example output:
     * ```
     * رصيد الائتمان | Credit Balance
     * ──────────────────────────────
     * Credit Limit: KWD 5,000.000
     * Used: KWD 1,250.000
     * Available: KWD 3,750.000
     * ```
     *
     * @param array $balance Balance data from CreditService::getBalance
     * @return string Formatted WhatsApp message
     */
    public static function formatBalanceSummary(array $balance): string
    {
        $lines = [];
        $lines[] = "رصيد الائتمان | Credit Balance";
        $lines[] = self::SEPARATOR;

        $currency = config('dotwai.display_currency', 'KWD');
        $limit     = number_format((float) ($balance['credit_limit'] ?? 0), 3);
        $used      = number_format((float) ($balance['used_credit'] ?? 0), 3);
        $available = number_format((float) ($balance['available_credit'] ?? 0), 3);

        $lines[] = "Credit Limit | الحد الائتماني: {$currency} {$limit}";
        $lines[] = "Used | المستخدم: {$currency} {$used}";
        $lines[] = "Available | المتاح: {$currency} {$available}";

        $lines[] = self::SEPARATOR;

        return implode("\n", $lines);
    }

    /**
     * Build suggested follow-up actions for WhatsApp options.
     *
     * Returns context-appropriate suggestions that the AI agent
     * can present as quick reply options.
     *
     * @param string $context Context type: 'search', 'details', 'cities', 'error'
     * @return array<int, string> Suggested follow-up actions
     */
    public static function buildWhatsAppOptions(string $context): array
    {
        return match ($context) {
            'search' => [
                "View details of a hotel (type number)",
                "Search another city",
                "Change dates",
            ],
            'details' => [
                "Book this hotel",
                "Go back to results",
                "Search again",
            ],
            'cities' => [
                "Search hotels in a city",
                "Try a different country",
            ],
            'error' => [
                "Try again",
                "Search a different city",
            ],
            default => [
                "Search hotels",
                "Get help",
            ],
        };
    }

    /**
     * Format a payment link message for WhatsApp delivery (Phase 19 -- payment-required tracks).
     *
     * Example output:
     * ```
     * مطلوب الدفع | Payment Required
     * ──────────────────────────────
     * Hotel | الفندق: Hilton Dubai Creek
     * Dates | التواريخ: 2026-04-10 → 2026-04-15
     * Amount | المبلغ: KWD 225.000
     *
     * Pay here | ادفع هنا:
     * https://myfatoorah.com/...
     *
     * Link expires | ينتهي الرابط: 2026-04-12 14:30:00
     * ```
     *
     * @param array       $paymentData Payment data from PaymentBridgeService::createPaymentLink
     * @param DotwAIBooking $booking   The booking record for hotel/date context
     * @return string Formatted WhatsApp message
     */
    public static function formatPaymentLink(array $paymentData, DotwAIBooking $booking): string
    {
        $lines = [];
        $lines[] = "مطلوب الدفع | Payment Required";
        $lines[] = self::SEPARATOR;

        $lines[] = "Hotel | الفندق: " . ($booking->hotel_name ?? 'N/A');
        $lines[] = "Dates | التواريخ: "
            . ($booking->check_in?->format('Y-m-d') ?? '')
            . " → "
            . ($booking->check_out?->format('Y-m-d') ?? '');

        $currency = $paymentData['currency'] ?? $booking->display_currency ?? 'KWD';
        $amount   = number_format((float) ($paymentData['amount'] ?? $booking->display_total_fare ?? 0), 3);
        $lines[] = "Amount | المبلغ: {$currency} {$amount}";

        $lines[] = "";
        $lines[] = "ادفع هنا | Pay here:";
        $lines[] = $paymentData['payment_url'] ?? '';

        if (! empty($paymentData['expiry'])) {
            $lines[] = "";
            $lines[] = "ينتهي الرابط | Link expires: " . $paymentData['expiry'];
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "يُرجى إتمام الدفع لتأكيد الحجز";
        $lines[] = "Please complete payment to confirm your booking.";

        return implode("\n", $lines);
    }

    /**
     * Format a booking failure / refund-initiated message for WhatsApp (Phase 19).
     *
     * Example output:
     * ```
     * فشل الحجز | Booking Failed
     * ──────────────────────────────
     * Hotel | الفندق: Hilton Dubai Creek
     * Dates | التواريخ: 2026-04-10 → 2026-04-15
     *
     * Reason | السبب: Rate no longer available
     * تم بدء استرداد المبلغ. | Refund has been initiated.
     * ```
     *
     * @param DotwAIBooking $booking The failed booking
     * @param string        $reason  Failure reason key: 'rate_unavailable' | 'booking_failed'
     * @return string Formatted WhatsApp message
     */
    public static function formatBookingFailed(DotwAIBooking $booking, string $reason): string
    {
        $lines = [];
        $lines[] = "فشل الحجز | Booking Failed";
        $lines[] = self::SEPARATOR;

        $lines[] = "Hotel | الفندق: " . ($booking->hotel_name ?? 'N/A');
        $lines[] = "Dates | التواريخ: "
            . ($booking->check_in?->format('Y-m-d') ?? '')
            . " → "
            . ($booking->check_out?->format('Y-m-d') ?? '');

        $lines[] = "";

        $reasonText = match ($reason) {
            'rate_unavailable' => "Rate no longer available | السعر لم يعد متاحاً",
            'booking_failed'   => "Hotel could not confirm booking | الفندق لم يتمكن من تأكيد الحجز",
            default            => "Booking could not be completed | لم يتم إتمام الحجز",
        };

        $lines[] = "Reason | السبب: {$reasonText}";
        $lines[] = "";
        $lines[] = "تم بدء استرداد المبلغ. | Refund has been initiated.";
        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "سيتواصل معك فريقنا قريباً";
        $lines[] = "Our team will contact you shortly.";

        return implode("\n", $lines);
    }

    /**
     * Format a full booking voucher for WhatsApp delivery (Phase 19 -- post-confirmation).
     *
     * Bilingual Arabic/English voucher with all booking details including
     * paymentGuaranteedBy (locked CONTEXT.md decision: always include when present).
     *
     * Example output:
     * ```
     * BOOKING CONFIRMATION | تأكيد الحجز
     * ══════════════════════════════
     *
     * Booking Reference: DOTW-12345678
     * الرقم المرجعي: DOTW-12345678
     * ──────────────────────────────
     *
     * Hotel | الفندق: Hilton Dubai Creek
     * Check-in | تسجيل الدخول: 10 Apr 2026
     * Check-out | تسجيل الخروج: 15 Apr 2026
     * ──────────────────────────────
     *
     * Guest(s) | الضيوف:
     * - Mr John Smith
     * ──────────────────────────────
     *
     * Total | المجموع: KWD 225.000
     * Status | الحالة: Confirmed | مؤكد
     * Payment Guaranteed By: City Travelers Agency
     * ──────────────────────────────
     *
     * Free cancellation until 05 Apr 2026
     * الإلغاء المجاني حتى 05 Apr 2026
     * ══════════════════════════════
     * City Travelers | سيتي ترافلرز
     * ```
     *
     * @param DotwAIBooking $booking The confirmed booking
     * @return string Formatted WhatsApp voucher message
     */
    public static function formatVoucherMessage(DotwAIBooking $booking): string
    {
        $doubleRule = "══════════════════════════════";

        $lines = [];
        $lines[] = "BOOKING CONFIRMATION | تأكيد الحجز";
        $lines[] = $doubleRule;
        $lines[] = "";

        // Booking reference
        $confirmationNo = $booking->confirmation_no ?? 'N/A';
        $lines[] = "Booking Reference: {$confirmationNo}";
        $lines[] = "الرقم المرجعي: {$confirmationNo}";
        $lines[] = self::SEPARATOR;
        $lines[] = "";

        // Hotel and dates
        $lines[] = "Hotel | الفندق: " . ($booking->hotel_name ?? 'N/A');
        $lines[] = "Check-in | تسجيل الدخول: " . ($booking->check_in?->format('d M Y') ?? 'N/A');
        $lines[] = "Check-out | تسجيل الخروج: " . ($booking->check_out?->format('d M Y') ?? 'N/A');
        $lines[] = self::SEPARATOR;
        $lines[] = "";

        // Guest list
        $lines[] = "Guest(s) | الضيوف:";
        $guestDetails = $booking->guest_details ?? [];
        if (!empty($guestDetails)) {
            foreach ($guestDetails as $guest) {
                $salutation = $guest['salutation'] ?? '';
                $firstName  = $guest['first_name'] ?? '';
                $lastName   = $guest['last_name'] ?? '';
                $name = trim("{$salutation} {$firstName} {$lastName}");
                $lines[] = "- " . ($name ?: 'Guest');
            }
        } else {
            $lines[] = "- Guest";
        }
        $lines[] = self::SEPARATOR;
        $lines[] = "";

        // Price and status
        $currency = $booking->display_currency ?? 'KWD';
        $fare     = number_format((float) ($booking->display_total_fare ?? 0), 3);
        $lines[] = "Total | المجموع: {$currency} {$fare}";
        $lines[] = "Status | الحالة: Confirmed | مؤكد";

        if (!empty($booking->payment_guaranteed_by)) {
            $lines[] = "Payment Guaranteed By: " . $booking->payment_guaranteed_by;
        }

        $lines[] = self::SEPARATOR;
        $lines[] = "";

        // Cancellation policy
        $isRefundable         = $booking->is_refundable ?? true;
        $isApr                = $booking->is_apr ?? false;
        $cancellationDeadline = $booking->cancellation_deadline;

        if ($isRefundable && !$isApr && $cancellationDeadline !== null) {
            $formattedDeadline = $cancellationDeadline->format('d M Y');
            $lines[] = "Free cancellation until {$formattedDeadline}";
            $lines[] = "الإلغاء المجاني حتى {$formattedDeadline}";
        } elseif (!$isRefundable || $isApr) {
            $lines[] = "Non-Refundable (APR) | غير قابل للاسترداد";
        } else {
            $lines[] = "See cancellation policy | راجع سياسة الإلغاء";
        }

        $lines[] = "";
        $lines[] = $doubleRule;
        $lines[] = "City Travelers | سيتي ترافلرز";

        return implode("\n", $lines);
    }

    /**
     * Format a cancellation pending / preview message (Phase 20 -- confirm=no step).
     *
     * Shows the penalty amount and asks the user for explicit confirmation to proceed.
     *
     * Example output:
     * ```
     * معاينة الإلغاء | Cancellation Preview
     * ──────────────────────────────
     * Hotel | الفندق: Hilton Dubai Creek
     * Dates | التواريخ: 2026-04-10 → 2026-04-15
     * DOTW Ref | المرجع: DOTW-12345678
     *
     * Penalty | رسوم الإلغاء: KWD 45.000
     * Refund | المبلغ المسترد: KWD 180.000
     *
     * هل تريد المتابعة بالإلغاء؟ | Do you want to proceed with cancellation?
     * Reply YES to confirm | أجب YES للتأكيد
     * ```
     *
     * @param array $data Keys: hotel_name, check_in, check_out, penalty_amount,
     *                    currency, booking_ref, refund_amount
     * @return string Formatted WhatsApp message
     */
    public static function formatCancellationPending(array $data): string
    {
        $lines = [];
        $lines[] = "معاينة الإلغاء | Cancellation Preview";
        $lines[] = self::SEPARATOR;

        $lines[] = "Hotel | الفندق: " . ($data['hotel_name'] ?? 'N/A');

        if (!empty($data['check_in']) || !empty($data['check_out'])) {
            $lines[] = "Dates | التواريخ: " . ($data['check_in'] ?? '') . " → " . ($data['check_out'] ?? '');
        }

        if (!empty($data['booking_ref'])) {
            $lines[] = "DOTW Ref | المرجع: " . $data['booking_ref'];
        }

        $lines[] = "";

        $currency      = $data['currency'] ?? 'KWD';
        $penaltyAmount = number_format((float) ($data['penalty_amount'] ?? 0), 3);
        $refundAmount  = number_format((float) ($data['refund_amount'] ?? 0), 3);

        if ((float) ($data['penalty_amount'] ?? 0) > 0) {
            $lines[] = "Penalty | رسوم الإلغاء: {$currency} {$penaltyAmount}";
        } else {
            $lines[] = "Penalty | رسوم الإلغاء: Free | مجاني";
        }

        $lines[] = "Refund | المبلغ المسترد: {$currency} {$refundAmount}";

        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "هل تريد المتابعة بالإلغاء؟";
        $lines[] = "Do you want to proceed with cancellation?";
        $lines[] = "أجب YES للتأكيد | Reply YES to confirm";

        return implode("\n", $lines);
    }

    /**
     * Format a cancellation confirmed message (Phase 20 -- confirm=yes step).
     *
     * Includes the mandatory DOTW delay warning (CANC-02 locked decision).
     *
     * Example output:
     * ```
     * تم الإلغاء | Cancellation Confirmed
     * ──────────────────────────────
     * Hotel | الفندق: Hilton Dubai Creek
     * DOTW Ref | المرجع: DOTW-12345678
     * Penalty | رسوم الإلغاء: KWD 45.000
     *
     * يرجى العلم: قد يستغرق تأكيد الإلغاء من DOTW وقتاً إضافياً للظهور على البوابة
     * Please note: DOTW cancellation confirmation may take additional time to reflect on the portal
     * ```
     *
     * @param array $data Keys: hotel_name, booking_ref, penalty_amount, currency,
     *                    is_free_cancellation (bool)
     * @return string Formatted WhatsApp message
     */
    public static function formatCancellationConfirmed(array $data): string
    {
        $lines = [];
        $lines[] = "تم الإلغاء | Cancellation Confirmed";
        $lines[] = self::SEPARATOR;

        $lines[] = "Hotel | الفندق: " . ($data['hotel_name'] ?? 'N/A');

        if (!empty($data['booking_ref'])) {
            $lines[] = "DOTW Ref | المرجع: " . $data['booking_ref'];
        }

        $lines[] = "";

        $currency = $data['currency'] ?? 'KWD';
        $isFree   = (bool) ($data['is_free_cancellation'] ?? false);

        if ($isFree) {
            $lines[] = "Penalty | رسوم الإلغاء: Free | مجاني";
        } else {
            $penaltyAmount = number_format((float) ($data['penalty_amount'] ?? 0), 3);
            $lines[] = "Penalty | رسوم الإلغاء: {$currency} {$penaltyAmount}";
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;

        // CANC-02 locked decision: always include DOTW delay warning
        $lines[] = "يرجى العلم: قد يستغرق تأكيد الإلغاء من DOTW وقتاً إضافياً للظهور على البوابة";
        $lines[] = "Please note: DOTW cancellation confirmation may take additional time to reflect on the portal";

        return implode("\n", $lines);
    }

    /**
     * Format a cancellation deadline reminder message (Phase 21 -- lifecycle reminders).
     *
     * Sent at 3, 2, and 1 days before the cancellation deadline for refundable
     * bookings. Bilingual AR/EN to serve both Arabic and English-speaking users.
     *
     * Example output:
     * ```
     * ⏰ تذكير من سياحتك | Booking Reminder
     * ──────────────────────────────
     * الفندق | Hotel: Hilton Dubai Creek
     * التاريخ | Date: 2026-04-10 إلى | to 2026-04-15
     * آخر موعد الإلغاء | Cancellation Deadline: 2026-04-05
     * الأيام المتبقية | Days Left: 2
     * الغرامة الحالية | Current Penalty: 100%
     *
     * إلغاء الآن لتجنب الرسوم | Cancel now to avoid charges
     * ```
     *
     * @param DotwAIBooking $booking  The booking with upcoming deadline
     * @param int           $daysLeft Days remaining until cancellation deadline
     * @return string Formatted WhatsApp reminder message
     */
    public static function formatReminderMessage(DotwAIBooking $booking, int $daysLeft): string
    {
        $penalty = 'TBD';

        if (!empty($booking->cancellation_rules) && is_array($booking->cancellation_rules)) {
            $penalty = $booking->cancellation_rules[0]['penalty'] ?? 'TBD';
        }

        $deadline = $booking->cancellation_deadline?->format('Y-m-d') ?? 'N/A';
        $checkIn  = $booking->check_in?->format('Y-m-d') ?? '';
        $checkOut = $booking->check_out?->format('Y-m-d') ?? '';

        return implode("\n", [
            "⏰ تذكير من سياحتك | Booking Reminder",
            self::SEPARATOR,
            "الفندق | Hotel: " . ($booking->hotel_name ?? 'N/A'),
            "التاريخ | Date: {$checkIn} إلى | to {$checkOut}",
            "آخر موعد الإلغاء | Cancellation Deadline: {$deadline}",
            "الأيام المتبقية | Days Left: {$daysLeft}",
            "الغرامة الحالية | Current Penalty: {$penalty}",
            "",
            "إلغاء الآن لتجنب الرسوم | Cancel now to avoid charges",
        ]);
    }

    /**
     * Format a deadline-passed confirmation message (Phase 21 -- after auto-invoice).
     *
     * Sent after the cancellation deadline passes and the booking is auto-invoiced.
     * Informs the user their booking is now fully confirmed with no free cancellation.
     *
     * Example output:
     * ```
     * ✅ تم تأكيد حجزك | Your booking is confirmed
     * ──────────────────────────────
     * الفندق | Hotel: Hilton Dubai Creek
     * التاريخ | Date: 2026-04-10 إلى | to 2026-04-15
     * رقم الحجز | Booking Ref: DOTW-12345678
     *
     * شكراً لاختيارك سياحتك | Thank you for booking with us
     * ```
     *
     * @param DotwAIBooking $booking The booking after deadline has passed
     * @return string Formatted WhatsApp deadline-passed message
     */
    public static function formatDeadlinePassedMessage(DotwAIBooking $booking): string
    {
        $checkIn  = $booking->check_in?->format('Y-m-d') ?? '';
        $checkOut = $booking->check_out?->format('Y-m-d') ?? '';
        $ref      = $booking->booking_ref ?? $booking->confirmation_no ?? 'N/A';

        return implode("\n", [
            "✅ تم تأكيد حجزك | Your booking is confirmed",
            self::SEPARATOR,
            "الفندق | Hotel: " . ($booking->hotel_name ?? 'N/A'),
            "التاريخ | Date: {$checkIn} إلى | to {$checkOut}",
            "رقم الحجز | Booking Ref: {$ref}",
            "",
            "شكراً لاختيارك سياحتك | Thank you for booking with us",
        ]);
    }

    /**
     * Format a booking status message showing cancellation policy, deadline, and penalty.
     *
     * Example output:
     * ```
     * حالة الحجز | Booking Status
     * ──────────────────────────────
     * الفندق | Hotel: Hilton Dubai Creek
     * التاريخ | Date: 2026-04-10 to 2026-04-15
     * رقم الحجز | Booking Ref: DOTW-12345678
     * الحالة | Status: confirmed
     *
     * قابل للاسترداد | Refundable
     * آخر موعد الإلغاء | Cancellation Deadline: 2026-04-05
     * الأيام المتبقية | Days Left: 3
     * الغرامة الحالية | Current Penalty: 0
     * ```
     *
     * @param array<string, mixed> $data Keys: hotel_name, check_in, check_out, status,
     *                                   booking_ref, cancellation_deadline, is_refundable,
     *                                   current_penalty
     * @return string Formatted WhatsApp message
     */
    public static function formatBookingStatusMessage(array $data): string
    {
        $lines = [];
        $lines[] = "حالة الحجز | Booking Status";
        $lines[] = self::SEPARATOR;

        $lines[] = "الفندق | Hotel: " . ($data['hotel_name'] ?? 'N/A');

        $checkIn  = $data['check_in'];
        $checkOut = $data['check_out'];
        if ($checkIn instanceof \DateTimeInterface) {
            $checkIn = $checkIn->format('Y-m-d');
        }
        if ($checkOut instanceof \DateTimeInterface) {
            $checkOut = $checkOut->format('Y-m-d');
        }
        $lines[] = "التاريخ | Date: {$checkIn} to {$checkOut}";
        $lines[] = "رقم الحجز | Booking Ref: " . ($data['booking_ref'] ?? 'N/A');
        $lines[] = "الحالة | Status: " . ($data['status'] ?? 'N/A');
        $lines[] = "";

        $isRefundable = (bool) ($data['is_refundable'] ?? true);
        $deadline = $data['cancellation_deadline'] ?? null;

        if ($isRefundable && $deadline !== null) {
            $deadlineFormatted = $deadline instanceof \DateTimeInterface
                ? $deadline->format('Y-m-d')
                : (string) $deadline;

            $daysLeft = (int) now()->diffInDays($deadline, false);

            $lines[] = "قابل للاسترداد | Refundable";
            $lines[] = "آخر موعد الإلغاء | Cancellation Deadline: {$deadlineFormatted}";
            $lines[] = "الأيام المتبقية | Days Left: " . abs($daysLeft);
        } else {
            $lines[] = "غير قابل للاسترداد | Non-Refundable";
        }

        $lines[] = "الغرامة الحالية | Current Penalty: " . ($data['current_penalty'] ?? 0);

        return implode("\n", $lines);
    }

    /**
     * Format a booking history list message for WhatsApp delivery.
     *
     * Example output:
     * ```
     * سجل الحجوزات | Booking History
     * ──────────────────────────────
     *
     * • Hilton Dubai Creek
     *   2026-04-10 to 2026-04-15
     *   Status: confirmed
     *
     * ──────────────────────────────
     * إجمالي | Total: 2 booking(s)
     * ```
     *
     * @param array<int, DotwAIBooking> $bookings Booking records to list
     * @param int                       $total    Total count across all pages
     * @return string Formatted WhatsApp message
     */
    public static function formatBookingHistoryMessage(array $bookings, int $total): string
    {
        if (empty($bookings)) {
            return "لا توجد حجوزات | No bookings found";
        }

        $lines = [];
        $lines[] = "سجل الحجوزات | Booking History";
        $lines[] = self::SEPARATOR;

        foreach ($bookings as $booking) {
            $checkIn  = $booking instanceof DotwAIBooking
                ? $booking->check_in?->format('Y-m-d')
                : ($booking['check_in'] ?? '');
            $checkOut = $booking instanceof DotwAIBooking
                ? $booking->check_out?->format('Y-m-d')
                : ($booking['check_out'] ?? '');
            $hotelName = $booking instanceof DotwAIBooking ? $booking->hotel_name : ($booking['hotel_name'] ?? 'N/A');
            $status    = $booking instanceof DotwAIBooking ? $booking->status : ($booking['status'] ?? 'N/A');

            $lines[] = "";
            $lines[] = "• {$hotelName}";
            $lines[] = "  {$checkIn} to {$checkOut}";
            $lines[] = "  Status: {$status}";
        }

        $lines[] = "";
        $lines[] = self::SEPARATOR;
        $lines[] = "إجمالي | Total: {$total} booking(s)";

        return implode("\n", $lines);
    }

    /**
     * Format a voucher resend confirmation message for WhatsApp.
     *
     * Sent after successfully re-sending the booking voucher to confirm
     * the resend action was completed.
     *
     * Example output:
     * ```
     * تم إعادة إرسال الفاتورة | Voucher Resent
     * ──────────────────────────────
     * الفندق | Hotel: Hilton Dubai Creek
     * رقم الحجز | Booking Ref: DOTW-12345678
     *
     * تحقق من رسائلك | Check your messages
     * ```
     *
     * @param DotwAIBooking $booking The confirmed booking whose voucher was resent
     * @return string Formatted WhatsApp message
     */
    public static function formatVoucherResendConfirmation(DotwAIBooking $booking): string
    {
        return implode("\n", [
            "تم إعادة إرسال الفاتورة | Voucher Resent",
            self::SEPARATOR,
            "الفندق | Hotel: " . ($booking->hotel_name ?? 'N/A'),
            "رقم الحجز | Booking Ref: " . ($booking->booking_ref ?? $booking->confirmation_no ?? 'N/A'),
            "",
            "تحقق من رسائلك | Check your messages",
        ]);
    }

    /**
     * Get Arabic translation for meal type.
     *
     * @param string $mealType English meal type label
     * @return string Arabic translation
     */
    private static function getMealTypeArabic(string $mealType): string
    {
        return match (strtolower($mealType)) {
            'room only' => 'إقامة فقط',
            'breakfast' => 'وجبة الإفطار',
            'half board' => 'نصف إقامة',
            'full board' => 'إقامة كاملة',
            'all inclusive' => 'شامل الكل',
            default => 'إقامة فقط',
        };
    }
}
