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
    public static function hotels(SimpleXMLElement $xml): array
    {
        $hotels = [];
        foreach ($xml->hotels->hotel ?? [] as $hotel) {
            $minFare = PHP_FLOAT_MAX;
            $rateBasisName = '';
            foreach ($hotel->rooms->room ?? [] as $room) {
                foreach ($room->rateBases->rateBasis ?? [] as $rb) {
                    $fare = (float) $rb->total;
                    if ($fare < $minFare) {
                        $minFare = $fare;
                        $rateBasisName = (string) $rb->name;
                    }
                }
            }
            $hotels[] = [
                'hotel_id'     => (string) $hotel->productId,
                'hotel_name'   => (string) $hotel->hotelName,
                'star_rating'  => (string) $hotel->classification,
                'city_name'    => (string) $hotel->cityName,
                'country_name' => (string) $hotel->countryName,
                'min_fare'     => $minFare === PHP_FLOAT_MAX ? 0.0 : $minFare,
                'currency'     => (string) ($xml->hotels['currency'] ?? ''),
                'rate_basis'   => $rateBasisName,
                'thumbnail'    => (string) ($hotel->imageList->image->url ?? ''),
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
        $rooms = [];
        $currency = (string) ($xml->hotels->hotel->rooms['currency'] ?? '');
        foreach ($xml->hotels->hotel->rooms->room ?? [] as $room) {
            foreach ($room->rateBases->rateBasis ?? [] as $rb) {
                $rooms[] = [
                    'room_type_code'      => (string) $room->code,
                    'room_name'           => (string) $room->name,
                    'rate_basis_id'       => (int) $rb->id,
                    'rate_basis_name'     => (string) $rb->name,
                    'total_fare'          => (float) $rb->total,
                    'allocation_details'  => (string) ($rb->allocationDetails ?? ''),
                    'currency'            => $currency,
                    'cancellation_policy' => (string) ($rb->cancellationPolicy ?? ''),
                ];
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
