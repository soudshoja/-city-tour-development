<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use Illuminate\Http\JsonResponse;

/**
 * Standardized response envelope for all DotwAI REST endpoints.
 *
 * Every response includes a `whatsappMessage` field (pre-formatted Arabic/English
 * text ready to send via WhatsApp) and optionally `whatsappOptions` (suggested
 * follow-up actions for the AI agent).
 *
 * Error responses additionally include a `suggestedAction` to guide the AI agent
 * on what to do next.
 *
 * @see EVNT-02 Every REST response includes whatsappMessage
 * @see EVNT-03 Error responses include suggestedAction for AI
 */
class DotwAIResponse
{
    /**
     * Error code constants.
     */
    public const PHONE_NOT_FOUND = 'PHONE_NOT_FOUND';
    public const COMPANY_NOT_FOUND = 'COMPANY_NOT_FOUND';
    public const CREDENTIALS_NOT_FOUND = 'CREDENTIALS_NOT_FOUND';
    public const TRACK_DISABLED = 'TRACK_DISABLED';
    public const CITY_NOT_FOUND = 'CITY_NOT_FOUND';
    public const HOTEL_NOT_FOUND = 'HOTEL_NOT_FOUND';
    public const NO_RESULTS = 'NO_RESULTS';
    public const DOTW_API_ERROR = 'DOTW_API_ERROR';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    /**
     * Default Arabic/English error messages and suggested actions per error code.
     *
     * @var array<string, array{whatsappMessage: string, suggestedAction: string}>
     */
    private static array $errorDefaults = [
        self::PHONE_NOT_FOUND => [
            'whatsappMessage' => "عذرا، لم نتمكن من التعرف على رقمك.\nSorry, we could not identify your phone number.",
            'suggestedAction' => 'Verify the phone number is registered with an agent account.',
        ],
        self::COMPANY_NOT_FOUND => [
            'whatsappMessage' => "عذرا، لم نتمكن من تحديد الشركة المرتبطة بحسابك.\nSorry, we could not determine your company.",
            'suggestedAction' => 'Ensure the agent is assigned to a branch with a valid company.',
        ],
        self::CREDENTIALS_NOT_FOUND => [
            'whatsappMessage' => "عذرا، لم يتم تفعيل خدمة حجز الفنادق لشركتك بعد.\nSorry, hotel booking service is not yet activated for your company.",
            'suggestedAction' => 'Contact the admin to set up DOTW credentials for your company.',
        ],
        self::TRACK_DISABLED => [
            'whatsappMessage' => "عذرا، هذه الخدمة غير متاحة حاليا.\nSorry, this service is currently unavailable.",
            'suggestedAction' => 'The booking track (B2B/B2C) is disabled for this company.',
        ],
        self::CITY_NOT_FOUND => [
            'whatsappMessage' => "عذرا، لم نتمكن من العثور على المدينة المطلوبة. حاول كتابة اسم المدينة بشكل مختلف.\nSorry, we could not find the requested city. Try a different spelling.",
            'suggestedAction' => 'Ask the user to provide a different city name or check spelling.',
        ],
        self::HOTEL_NOT_FOUND => [
            'whatsappMessage' => "عذرا، لم نتمكن من العثور على الفندق المطلوب.\nSorry, we could not find the requested hotel.",
            'suggestedAction' => 'Ask the user to try a different hotel name or search by city instead.',
        ],
        self::NO_RESULTS => [
            'whatsappMessage' => "عذرا، لا توجد نتائج متاحة للتواريخ المحددة.\nSorry, no results available for the selected dates.",
            'suggestedAction' => 'Suggest trying different dates, a different city, or removing filters.',
        ],
        self::DOTW_API_ERROR => [
            'whatsappMessage' => "عذرا، حدث خطأ في نظام الحجز. يرجى المحاولة مرة أخرى.\nSorry, there was a booking system error. Please try again.",
            'suggestedAction' => 'Retry the request. If persistent, contact technical support.',
        ],
        self::VALIDATION_ERROR => [
            'whatsappMessage' => "عذرا، البيانات المدخلة غير صحيحة.\nSorry, the input data is invalid.",
            'suggestedAction' => 'Check the request parameters and try again.',
        ],
    ];

    /**
     * Create a successful response with data and WhatsApp message.
     *
     * @param array<string, mixed>  $data             Response data payload
     * @param string                $whatsappMessage   Pre-formatted WhatsApp message
     * @param array<int, string>    $whatsappOptions   Suggested follow-up options for the AI
     * @return JsonResponse
     */
    public static function success(
        array $data,
        string $whatsappMessage,
        array $whatsappOptions = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'whatsappMessage' => $whatsappMessage,
            'whatsappOptions' => $whatsappOptions,
        ]);
    }

    /**
     * Create an error response with error details and WhatsApp message.
     *
     * Uses default Arabic/English messages and suggested actions per error code.
     * Custom values override defaults when provided.
     *
     * @param string      $code             Error code constant (e.g., PHONE_NOT_FOUND)
     * @param string      $message          Technical error message (for logging/debugging)
     * @param string|null $whatsappMessage   Custom WhatsApp message (overrides default)
     * @param string|null $suggestedAction   Custom suggested action (overrides default)
     * @param int         $httpStatus        HTTP status code (default 422)
     * @return JsonResponse
     */
    public static function error(
        string $code,
        string $message,
        ?string $whatsappMessage = null,
        ?string $suggestedAction = null,
        int $httpStatus = 422,
    ): JsonResponse {
        $defaults = self::$errorDefaults[$code] ?? [
            'whatsappMessage' => "عذرا، حدث خطأ غير متوقع.\nSorry, an unexpected error occurred.",
            'suggestedAction' => 'Contact technical support.',
        ];

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'suggestedAction' => $suggestedAction ?? $defaults['suggestedAction'],
            ],
            'whatsappMessage' => $whatsappMessage ?? $defaults['whatsappMessage'],
            'whatsappOptions' => [],
        ], $httpStatus);
    }
}
