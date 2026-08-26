<?php

namespace App\Services\Vouchers;

use App\Models\VoucherTemplate;

/**
 * Fabricated payloads for the Settings gallery's "you have no {type}
 * bookings yet" state (plan §8: "render from a shipped fixture payload...
 * with a diagonal SAMPLE watermark"). Every field here traces to the exact
 * shape `VoucherDataRepository::payloadForTask()` produces — a template
 * never needs to know whether it is looking at a real payload or a
 * fixture, which is the point: one render path, honestly labelled.
 *
 * `VoucherDataRepository::shellForCompany()` supplies the REAL
 * company/voucher/terms blocks (the company's own logo and terms still
 * show up on a sample card — only the booking content is fabricated);
 * this class supplies everything else.
 *
 * Not a database read, so it does not funnel through VoucherDataRepository
 * itself — but it is the only class allowed to fabricate voucher content,
 * mirroring that class's "one place this happens" discipline.
 */
final class VoucherSampleFixtures
{
    /**
     * @param  array<string, mixed>  $shell  VoucherDataRepository::shellForCompany() output
     * @return array<string, mixed> a full payloadForTask()-shaped array
     */
    public static function forType(string $taskType, string $language, array $shell): array
    {
        $language = $language === VoucherTemplate::LANGUAGE_AR || $language === 'ARB' ? 'ARB' : 'EN';

        $typeBlock = match ($taskType) {
            VoucherCatalogue::TASK_TYPE_HOTEL => self::hotel($language),
            VoucherCatalogue::TASK_TYPE_FLIGHT => self::flight($language),
            VoucherCatalogue::TASK_TYPE_VISA => self::visa($language),
            VoucherCatalogue::TASK_TYPE_INSURANCE => self::insurance($language),
            default => self::generic($language),
        };

        return array_merge($shell, $typeBlock);
    }

    private static function hotel(string $lang): array
    {
        $ar = $lang === 'ARB';

        return [
            'task_type' => 'hotel',
            'resolved_type' => 'hotel',
            'client' => [
                'id' => null,
                'name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'phone' => '+965 5000 1234',
                'email' => 'sample.client@example.com',
            ],
            'agent' => ['id' => null, 'name' => $ar ? 'سارة العنزي' : 'Sara Al-Anzi'],
            'task' => [
                'id' => 0,
                'type' => 'hotel',
                'status' => 'issued',
                'reference' => 'SAMPLE-HTL-0001',
                'gds_reference' => null,
                'airline_reference' => null,
                'ticket_number' => null,
                'passenger_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'client_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'issued_date' => now()->toDateTimeString(),
                'expiry_date' => null,
                'duration' => null,
                'venue' => null,
                'additional_info' => null,
                'cancellation_policy' => [],
                'cancellation_deadline' => null,
            ],
            'flight' => null,
            'hotel' => [
                'hotel' => [
                    'id' => 0,
                    'name' => $ar ? 'فندق هيلتون مكة' : 'Hilton Makkah Convention',
                    'address' => $ar ? 'إبراهيم الخليل، مكة المكرمة' : 'Ibrahim Al Khalil Road, Makkah',
                    'city' => $ar ? 'مكة المكرمة' : 'Makkah',
                    'state' => null,
                    'country' => $ar ? 'المملكة العربية السعودية' : 'Saudi Arabia',
                    'zip_code' => null,
                    'phone' => '+966 12 500 0000',
                    'email' => null,
                    'website' => null,
                    'rating' => '5',
                    'image' => null,
                    'description' => null,
                ],
                'check_in' => now()->addDays(14)->toDateString(),
                'check_out' => now()->addDays(19)->toDateString(),
                'nights' => 5,
                'booking_time' => now()->toDateTimeString(),
                'room_reference' => 'SAMPLE-CNF-88213',
                'room_number' => null,
                'room_type' => $ar ? 'غرفة ديلوكس بسريرين' : 'Deluxe Twin Room',
                'room_name' => $ar ? 'غرفة ديلوكس بسريرين' : 'Deluxe Twin Room',
                'room_amount' => 2,
                'room_promotion' => null,
                'rate' => null,
                'meal_type' => 'BB',
                'meal_type_label' => 'Bed & Breakfast',
                'is_refundable' => true,
                'supplements' => null,
            ],
            'visa' => null,
            'insurance' => null,
            'segment' => null,
            'money' => null,
            'payment' => null,
        ];
    }

    private static function flight(string $lang): array
    {
        $ar = $lang === 'ARB';

        $legs = [
            [
                'departure_time' => now()->addDays(10)->setTime(8, 30)->format('Y-m-d H:i'),
                'arrival_time' => now()->addDays(10)->setTime(11, 45)->format('Y-m-d H:i'),
                'duration_time' => '02:15',
                'airport_from' => 'KWI',
                'airport_from_name' => $ar ? 'مطار الكويت الدولي' : 'Kuwait International Airport',
                'terminal_from' => '4',
                'airport_to' => 'DXB',
                'airport_to_name' => $ar ? 'مطار دبي الدولي' : 'Dubai International Airport',
                'terminal_to' => '1',
                'country_from' => $ar ? 'الكويت' : 'Kuwait',
                'country_to' => $ar ? 'الإمارات العربية المتحدة' : 'United Arab Emirates',
                'airline' => $ar ? 'الخطوط الجوية الكويتية' : 'Kuwait Airways',
                'flight_number' => 'KU542',
                'ticket_number' => '229-2833133219',
                'class_type' => 'Economy',
                'baggage_allowed' => '30KG',
                'equipment' => null,
                'flight_meal' => 'Meal',
                'seat_no' => '14C',
                'farebase' => 'YOW',
            ],
        ];

        return [
            'task_type' => 'flight',
            'resolved_type' => 'flight',
            'client' => [
                'id' => null,
                'name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'phone' => '+965 5000 1234',
                'email' => 'sample.client@example.com',
            ],
            'agent' => ['id' => null, 'name' => $ar ? 'سارة العنزي' : 'Sara Al-Anzi'],
            'task' => [
                'id' => 0,
                'type' => 'flight',
                'status' => 'issued',
                'reference' => 'SAMPLE-FLT-0001',
                'gds_reference' => '7NSYZS',
                'airline_reference' => 'KU542',
                'ticket_number' => '229-2833133219',
                'passenger_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'client_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'issued_date' => now()->toDateTimeString(),
                'expiry_date' => null,
                'duration' => null,
                'venue' => null,
                'additional_info' => null,
                'cancellation_policy' => [],
                'cancellation_deadline' => null,
            ],
            'flight' => [
                'legs' => $legs,
                'ancillaries' => [],
                'roster' => [[
                    'task_id' => 0,
                    'passenger_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                    'ticket_number' => '229-2833133219',
                    'seat_no' => '14C',
                    'baggage_allowed' => '30KG',
                    'flight_meal' => 'Meal',
                    'class_type' => 'Economy',
                ]],
            ],
            'hotel' => null,
            'visa' => null,
            'insurance' => null,
            'segment' => null,
            'money' => null,
            'payment' => null,
        ];
    }

    private static function visa(string $lang): array
    {
        $ar = $lang === 'ARB';

        return [
            'task_type' => 'visa',
            'resolved_type' => 'visa',
            'client' => [
                'id' => null,
                'name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'phone' => '+965 5000 1234',
                'email' => 'sample.client@example.com',
            ],
            'agent' => ['id' => null, 'name' => $ar ? 'سارة العنزي' : 'Sara Al-Anzi'],
            'task' => [
                'id' => 0,
                'type' => 'visa',
                'status' => 'issued',
                'reference' => 'SAMPLE-VSA-0001',
                'gds_reference' => null,
                'airline_reference' => null,
                'ticket_number' => null,
                'passenger_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'client_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'issued_date' => now()->toDateTimeString(),
                'expiry_date' => now()->addMonths(3)->toDateTimeString(),
                'duration' => null,
                'venue' => null,
                'additional_info' => null,
                'cancellation_policy' => [],
                'cancellation_deadline' => null,
            ],
            'flight' => null,
            'hotel' => null,
            'visa' => [
                'visa_type' => $ar ? 'زيارة - دخول واحد' : 'Visit - Single Entry',
                'application_number' => 'SAMPLE-APP-55210',
                'appointment_date' => now()->addDays(3)->toDateString(),
                'expiry_date' => now()->addMonths(3)->toDateString(),
                'number_of_entries' => '1',
                'stay_duration' => '30',
                'issuing_country' => $ar ? 'المملكة العربية السعودية' : 'Saudi Arabia',
            ],
            'insurance' => null,
            'segment' => null,
            'money' => null,
            'payment' => null,
        ];
    }

    private static function insurance(string $lang): array
    {
        $ar = $lang === 'ARB';

        return [
            'task_type' => 'insurance',
            'resolved_type' => 'insurance',
            'client' => [
                'id' => null,
                'name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'phone' => '+965 5000 1234',
                'email' => 'sample.client@example.com',
            ],
            'agent' => ['id' => null, 'name' => $ar ? 'سارة العنزي' : 'Sara Al-Anzi'],
            'task' => [
                'id' => 0,
                'type' => 'insurance',
                'status' => 'issued',
                'reference' => 'SAMPLE-INS-0001',
                'gds_reference' => null,
                'airline_reference' => null,
                'ticket_number' => null,
                'passenger_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'client_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'issued_date' => now()->toDateTimeString(),
                'expiry_date' => now()->addDays(14)->toDateTimeString(),
                'duration' => null,
                'venue' => null,
                'additional_info' => null,
                'cancellation_policy' => [],
                'cancellation_deadline' => null,
            ],
            'flight' => null,
            'hotel' => null,
            'visa' => null,
            'insurance' => [
                'insurance_type' => $ar ? 'تأمين سفر' : 'Travel Insurance',
                'plan_type' => $ar ? 'أساسي' : 'Basic',
                'package' => $ar ? 'باقة شنغن' : 'Schengen Package',
                'destination' => $ar ? 'أوروبا' : 'Europe',
                'duration' => '7',
                'date' => now()->addDays(7)->toDateString(),
                'document_reference' => 'SAMPLE-POL-30144',
                'paid_leaves' => null,
            ],
            'segment' => null,
            'money' => null,
            'payment' => null,
        ];
    }

    private static function generic(string $lang): array
    {
        $ar = $lang === 'ARB';

        return [
            'task_type' => 'car',
            'resolved_type' => 'generic',
            'client' => [
                'id' => null,
                'name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'phone' => '+965 5000 1234',
                'email' => 'sample.client@example.com',
            ],
            'agent' => ['id' => null, 'name' => $ar ? 'سارة العنزي' : 'Sara Al-Anzi'],
            'task' => [
                'id' => 0,
                'type' => 'car',
                'status' => 'issued',
                'reference' => 'SAMPLE-CAR-0001',
                'gds_reference' => null,
                'airline_reference' => null,
                'ticket_number' => null,
                'passenger_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'client_name' => $ar ? 'محمد أحمد السعدي' : 'Mohammed Al-Saadi',
                'issued_date' => now()->toDateTimeString(),
                'expiry_date' => null,
                'duration' => null,
                'venue' => $ar ? 'من مطار الشارقة الدولي إلى فندق هيلتون أبوظبي' : 'Sharjah International Airport (SHJ) to Hilton Abu Dhabi Yas Island',
                'additional_info' => $ar
                    ? 'باص صغير حتى 7 ركاب. وقت الالتقاء 12:15 ظهرا. يرجى الانتظار عند بوابة الوصول.'
                    : 'Minibus, up to 7 passengers. Pickup at 12:15 PM. Please wait at the arrivals gate.',
                'cancellation_policy' => [],
                'cancellation_deadline' => null,
            ],
            'flight' => null,
            'hotel' => null,
            'visa' => null,
            'insurance' => null,
            'segment' => [
                'type_label' => $ar ? 'انتقال' : 'Car',
                'venue' => $ar ? 'من مطار الشارقة الدولي إلى فندق هيلتون أبوظبي' : 'Sharjah International Airport (SHJ) to Hilton Abu Dhabi Yas Island',
                'additional_info' => $ar
                    ? 'باص صغير حتى 7 ركاب. وقت الالتقاء 12:15 ظهرا. يرجى الانتظار عند بوابة الوصول.'
                    : 'Minibus, up to 7 passengers. Pickup at 12:15 PM. Please wait at the arrivals gate.',
                'date' => now()->addDays(5)->toDateString(),
            ],
            'money' => null,
            'payment' => null,
        ];
    }
}
