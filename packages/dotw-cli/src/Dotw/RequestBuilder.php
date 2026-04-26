<?php

declare(strict_types=1);

namespace Dotw\Cli\Dotw;

/**
 * Builds DOTW XML request bodies (everything inside <request>).
 * All methods are static. No state. No Laravel.
 */
class RequestBuilder
{
    /**
     * Build the <bookingDetails> + <return> body for searchhotels.
     *
     * @param array{
     *   fromDate: string,
     *   toDate: string,
     *   currency: int,
     *   city: int,
     *   adults: int,
     *   children: int,
     *   nationality: int,
     *   residence: int,
     *   rateBasis: int,
     * } $params
     */
    public static function search(array $params): string
    {
        $childrenXml = $params['children'] > 0
            ? sprintf('<children no="%d"><child runno="0"><age>10</age></child></children>', $params['children'])
            : '<children no="0"/>';

        return sprintf(
            '<bookingDetails>
    <fromDate>%s</fromDate>
    <toDate>%s</toDate>
    <currency>%d</currency>
    <rooms no="1">
        <room runno="0">
            <adultsCode>%d</adultsCode>
            %s
            <rateBasis>%d</rateBasis>
            <passengerNationality>%d</passengerNationality>
            <passengerCountryOfResidence>%d</passengerCountryOfResidence>
        </room>
    </rooms>
</bookingDetails>
<return>
    <filters xmlns:a="http://us.dotwconnect.com/xsd/atomicCondition"
             xmlns:c="http://us.dotwconnect.com/xsd/complexCondition">
        <city>%d</city>
    </filters>
</return>',
            htmlspecialchars($params['fromDate']),
            htmlspecialchars($params['toDate']),
            $params['currency'],
            $params['adults'],
            $childrenXml,
            $params['rateBasis'],
            $params['nationality'],
            $params['residence'],
            $params['city']
        );
    }

    /**
     * Build getRooms body for browse pass (blocking=false).
     *
     * NOTE: <productId> must appear AFTER <rooms> per getrooms.xsd.
     * The <return><fields> section requests cancellation, name, and tariff notes.
     */
    public static function getRoomsBrowse(array $params): string
    {
        $childrenXml = ($params['children'] ?? 0) > 0
            ? sprintf('<children no="%d"><child runno="0"><age>10</age></child></children>', $params['children'])
            : '<children no="0"/>';

        return sprintf(
            '<bookingDetails>
    <fromDate>%s</fromDate>
    <toDate>%s</toDate>
    <currency>%d</currency>
    <rooms no="1">
        <room runno="0">
            <adultsCode>%d</adultsCode>
            %s
            <rateBasis>%d</rateBasis>
            <passengerNationality>%d</passengerNationality>
            <passengerCountryOfResidence>%d</passengerCountryOfResidence>
        </room>
    </rooms>
    <productId>%s</productId>
</bookingDetails>
<return>
    <fields>
        <roomField>cancellation</roomField>
        <roomField>name</roomField>
        <roomField>tariffNotes</roomField>
    </fields>
</return>',
            htmlspecialchars($params['fromDate']),
            htmlspecialchars($params['toDate']),
            $params['currency'],
            $params['adults'],
            $childrenXml,
            $params['rateBasis'] ?? -1,
            $params['nationality'],
            $params['residence'],
            htmlspecialchars($params['hotelId'])
        );
    }

    /**
     * Build getRooms body for block pass (blocking=true).
     *
     * NOTE: <productId> must appear AFTER <rooms> per getrooms.xsd.
     * Requires roomTypeCode, selectedRateBasis, allocationDetails from browse response.
     */
    public static function getRoomsBlock(array $params): string
    {
        $childrenXml = ($params['children'] ?? 0) > 0
            ? sprintf('<children no="%d"><child runno="0"><age>10</age></child></children>', $params['children'])
            : '<children no="0"/>';

        return sprintf(
            '<bookingDetails>
    <fromDate>%s</fromDate>
    <toDate>%s</toDate>
    <currency>%d</currency>
    <rooms no="1">
        <room runno="0">
            <adultsCode>%d</adultsCode>
            %s
            <rateBasis>%d</rateBasis>
            <passengerNationality>%d</passengerNationality>
            <passengerCountryOfResidence>%d</passengerCountryOfResidence>
            <roomTypeSelected>
                <code>%s</code>
                <selectedRateBasis>%d</selectedRateBasis>
                <allocationDetails>%s</allocationDetails>
            </roomTypeSelected>
        </room>
    </rooms>
    <productId>%s</productId>
</bookingDetails>
<return>
</return>',
            htmlspecialchars($params['fromDate']),
            htmlspecialchars($params['toDate']),
            $params['currency'],
            $params['adults'],
            $childrenXml,
            $params['rateBasis'] ?? -1,
            $params['nationality'],
            $params['residence'],
            htmlspecialchars($params['roomTypeCode']),
            $params['selectedRateBasis'],
            htmlspecialchars($params['allocationDetails']),
            htmlspecialchars($params['hotelId'])
        );
    }

    /**
     * Build cancelbooking body (confirm=no for preview, confirm=yes for execute).
     */
    public static function cancelBooking(string $bookingCode, bool $confirm, ?float $penaltyApplied = null): string
    {
        $confirmStr = $confirm ? 'yes' : 'no';

        $testPrices = '';
        if ($confirm && $penaltyApplied !== null) {
            $testPrices = sprintf(
                '<testPricesAndAllocation>
        <service referencenumber="%s">
            <penaltyApplied>%s</penaltyApplied>
        </service>
    </testPricesAndAllocation>',
                htmlspecialchars($bookingCode),
                $penaltyApplied
            );
        }

        return sprintf(
            '<bookingDetails>
    <bookingType>1</bookingType>
    <bookingCode>%s</bookingCode>
    <confirm>%s</confirm>
    %s
</bookingDetails>',
            htmlspecialchars($bookingCode),
            $confirmStr,
            $testPrices
        );
    }
}
