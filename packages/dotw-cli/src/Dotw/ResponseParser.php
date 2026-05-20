<?php

declare(strict_types=1);

namespace Dotw\Cli\Dotw;

use SimpleXMLElement;

/**
 * Parses DOTW SimpleXMLElement responses into plain PHP arrays.
 * All methods are static. No state. No Laravel.
 */
class ResponseParser
{
    /**
     * Parse searchhotels response into a flat hotel list.
     *
     * @return array<int, array{
     *   hotel_id: string,
     *   hotel_name: string,
     *   star_rating: string,
     *   city_name: string,
     *   country_name: string,
     *   min_fare: float,
     *   currency: string,
     *   rate_basis: string,
     *   thumbnail: string,
     * }>
     */
    /**
     * DOTW V4 rate basis ID → human-readable name map.
     * Search response does not include description attribute; map from ID.
     */
    private static array $rateBasisNames = [
        0    => 'Room Only',
        1331 => 'Breakfast',
        1332 => 'Half Board',
        1333 => 'Full Board',
        1334 => 'All Inclusive',
        1335 => 'Soft All Inclusive',
        1336 => 'Self Catering',
    ];

    public static function hotels(SimpleXMLElement $xml): array
    {
        // V4 searchhotels response:
        //   <result command="searchhotels" ...>
        //     <hotels>
        //       <hotel hotelid="1013275">
        //         <rooms>
        //           <room adults="2" ...>
        //             <roomType roomtypecode="..." ><name>DELUXE ROOM.</name>
        //               <rateBases>
        //                 <rateBasis id="0"><rateType currencyid="769">1</rateType><total>20.9479</total>
        // Note: hotel name, stars, city are NOT in search response — only in getrooms response.
        $hotels = [];
        foreach ($xml->hotels->hotel ?? [] as $hotel) {
            $hotelId   = (string) ($hotel['hotelid'] ?? '');
            $minFare   = PHP_FLOAT_MAX;
            $minRbId   = 0;
            $currencyId = '';

            foreach ($hotel->rooms->room ?? [] as $room) {
                foreach ($room->roomType ?? [] as $roomType) {
                    foreach ($roomType->rateBases->rateBasis ?? [] as $rb) {
                        $fare = (float) $rb->total;
                        if ($fare < $minFare && $fare > 0.0) {
                            $minFare    = $fare;
                            $minRbId    = (int) ($rb['id'] ?? 0);
                            $currencyId = (string) ($rb->rateType['currencyid'] ?? '');
                        }
                    }
                }
            }

            $hotels[] = [
                'hotel_id'     => $hotelId,
                'hotel_name'   => '',   // not in search RS; call dotw:hotels:show for details
                'star_rating'  => '',
                'city_name'    => '',
                'country_name' => '',
                'min_fare'     => $minFare === PHP_FLOAT_MAX ? 0.0 : $minFare,
                'currency_id'  => $currencyId,
                'rate_basis'   => self::$rateBasisNames[$minRbId] ?? "RB-{$minRbId}",
                'thumbnail'    => '',
            ];
        }
        return $hotels;
    }

    /**
     * Parse getrooms response into a rooms array.
     *
     * @return array<int, array{
     *   room_type_code: string,
     *   room_name: string,
     *   rate_basis_id: int,
     *   rate_basis_name: string,
     *   total_fare: float,
     *   allocation_details: string,
     *   currency: string,
     *   cancellation_policy: string,
     * }>
     */
    public static function rooms(SimpleXMLElement $xml): array
    {
        // V4 getrooms response: <result><currencyShort>KWD</currencyShort>
        //   <hotel id="..." name="...">
        //     <rooms count="1">
        //       <room runno="0" ...>
        //         <roomType runno="0" roomtypecode="...">
        //           <name>...</name>
        //           <rateBases count="N">
        //             <rateBasis runno="0" id="..." description="...">
        //               <allocationDetails>...</allocationDetails>
        //               <total>...</total>
        //               <cancellationRules>...</cancellationRules>
        $rooms    = [];
        $currency = (string) ($xml->currencyShort ?? '');

        foreach ($xml->hotel->rooms->room ?? [] as $room) {
            foreach ($room->roomType ?? [] as $roomType) {
                $roomTypeCode = (string) ($roomType['roomtypecode'] ?? '');
                $roomName     = (string) ($roomType->name ?? '');

                foreach ($roomType->rateBases->rateBasis ?? [] as $rb) {
                    // Build a human-readable cancellation summary from first non-zero rule
                    $cancelPolicy = '';
                    foreach ($rb->cancellationRules->rule ?? [] as $rule) {
                        $charge = (float) $rule->charge;
                        if ($charge > 0.0) {
                            $cancelPolicy = sprintf(
                                'From %s: %s %s',
                                (string) ($rule->fromDate ?? $rule->toDate ?? ''),
                                $currency,
                                number_format($charge, 3)
                            );
                            break;
                        }
                    }

                    $rooms[] = [
                        'room_type_code'      => $roomTypeCode,
                        'room_name'           => $roomName,
                        'rate_basis_id'       => (int) ($rb['id'] ?? 0),
                        'rate_basis_name'     => (string) ($rb['description'] ?? ''),
                        'total_fare'          => (float) $rb->total,
                        'allocation_details'  => (string) ($rb->allocationDetails ?? ''),
                        'currency'            => $currency,
                        'cancellation_policy' => $cancelPolicy,
                    ];
                }
            }
        }
        return $rooms;
    }

    /**
     * Parse cancelbooking response to get penalty/refund info.
     */
    public static function cancelPreview(SimpleXMLElement $xml): array
    {
        $results = [];
        foreach ($xml->services->service ?? [] as $service) {
            $results[] = [
                'booking_code' => (string) $service['code'],
                'charge'       => (float) $service->cancellationPenalty->charge,
                'currency'     => (string) $service->cancellationPenalty->currencyShort,
            ];
        }
        return $results;
    }
}
