<?php

namespace App\Services\Vouchers;

/**
 * The five voucher designs we ship in code (plan §5, §16 step 3). This is
 * the single source of truth for "what designs exist" — the
 * voucher_templates registry rows (migration
 * 2026_08_27_130000_seed_voucher_templates_registry) are seeded FROM this
 * list, and the Settings gallery reads it directly rather than keeping a
 * second copy in a view or a controller.
 *
 * Deliberately excludes the package design (`vouchers.package-classic`,
 * plan §5 row 6) — that ships with Phase B (task_packages, §13 Phase B),
 * not this step.
 *
 * "generic" is not a real `tasks.type` value — GENERIC_TASK_TYPES below is
 * the honest list of typed tasks with no detail table (plan §0, §7) that
 * the generic-segment design previews against.
 */
final class VoucherCatalogue
{
    public const TASK_TYPE_FLIGHT = 'flight';

    public const TASK_TYPE_HOTEL = 'hotel';

    public const TASK_TYPE_VISA = 'visa';

    public const TASK_TYPE_INSURANCE = 'insurance';

    public const TASK_TYPE_GENERIC = 'generic';

    /**
     * The real `tasks.type` values with no detail table (verified live,
     * plan §1 fact 12 / §0): car, rail, esim, event, tour. A generic
     * catalogue preview picks the company's latest task among these.
     */
    public const GENERIC_TASK_TYPES = ['car', 'rail', 'esim', 'event', 'tour'];

    public const LANGUAGES = ['EN', 'ARB'];

    /**
     * @return array<int, array{task_type: string, view_key: string, name: string, label: string}>
     */
    public static function entries(): array
    {
        return [
            [
                'task_type' => self::TASK_TYPE_HOTEL,
                'view_key' => 'vouchers.hotel-classic',
                'name' => 'Hotel Voucher',
                'label' => 'hotel',
            ],
            [
                'task_type' => self::TASK_TYPE_FLIGHT,
                'view_key' => 'vouchers.flight-classic',
                'name' => 'Flight E-Ticket / Itinerary',
                'label' => 'flight',
            ],
            [
                'task_type' => self::TASK_TYPE_VISA,
                'view_key' => 'vouchers.visa-classic',
                'name' => 'Visa Confirmation',
                'label' => 'visa',
            ],
            [
                'task_type' => self::TASK_TYPE_INSURANCE,
                'view_key' => 'vouchers.insurance-classic',
                'name' => 'Insurance Cover Note',
                'label' => 'insurance',
            ],
            [
                'task_type' => self::TASK_TYPE_GENERIC,
                'view_key' => 'vouchers.segment-generic',
                'name' => 'Generic Service Voucher',
                'label' => 'transfer, rail, tour, eSIM or event',
            ],
        ];
    }

    public static function find(string $taskType): ?array
    {
        foreach (self::entries() as $entry) {
            if ($entry['task_type'] === $taskType) {
                return $entry;
            }
        }

        return null;
    }

    public static function isValidTaskType(string $taskType): bool
    {
        return self::find($taskType) !== null;
    }

    public static function isValidLanguage(string $language): bool
    {
        return in_array(strtoupper($language), self::LANGUAGES, true);
    }

    /**
     * The catalogue task_type a real `tasks.type` value previews/issues
     * against (Step 4, plan §16): the four typed tasks map 1:1, the five
     * types with no detail table (GENERIC_TASK_TYPES) all fall to the one
     * generic-segment design.
     */
    public static function catalogTaskTypeFor(string $taskType): string
    {
        if (in_array($taskType, self::GENERIC_TASK_TYPES, true)) {
            return self::TASK_TYPE_GENERIC;
        }

        return $taskType;
    }
}
