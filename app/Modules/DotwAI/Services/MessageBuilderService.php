<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

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
