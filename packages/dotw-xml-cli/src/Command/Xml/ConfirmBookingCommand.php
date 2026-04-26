<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command\Xml;

use Dotw\XmlCli\Command\AbstractXmlCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'xml:confirm-booking', description: 'Confirm a hotel booking (confirmbooking)')]
final class ConfirmBookingCommand extends AbstractXmlCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('hotel',      null, InputOption::VALUE_REQUIRED, 'DOTW hotel ID (productId)')
            ->addOption('from',       null, InputOption::VALUE_REQUIRED, 'Check-in date YYYY-MM-DD')
            ->addOption('to',         null, InputOption::VALUE_REQUIRED, 'Check-out date YYYY-MM-DD')
            ->addOption('currency',   null, InputOption::VALUE_REQUIRED, 'DOTW currency code', null)
            ->addOption('email',      null, InputOption::VALUE_REQUIRED, 'Customer email for DOTW communication', '')
            ->addOption('reference',  null, InputOption::VALUE_REQUIRED, 'Customer reference / booking reference', '')
            ->addOption('rooms-json', null, InputOption::VALUE_REQUIRED,
                "JSON array of room objects. Each room:\n" .
                "  {\"roomTypeCode\":\"X\",\"selectedRateBasis\":0,\"allocationDetails\":\"Y\"," .
                "\"adults\":2,\"nationality\":66,\"residence\":66," .
                "\"passengers\":[{\"salutation\":147,\"firstName\":\"John\",\"lastName\":\"Doe\",\"leading\":true}]}"
            )
            ->setHelp(
                "Confirm a hotel booking via confirmbooking.\n\n" .
                "Single room example:\n" .
                "  bin/dotw-xml xml:confirm-booking \\n" .
                "    --hotel=12345 --from=2026-09-01 --to=2026-09-03 \\n" .
                "    --email=agent@example.com --reference=REF001 \\n" .
                "    --rooms-json='[{\"roomTypeCode\":\"DELUXE\",\"selectedRateBasis\":0,\n" .
                "      \"allocationDetails\":\"ALLOC\",\"adults\":2,\"nationality\":66,\"residence\":66,\n" .
                "      \"passengers\":[{\"salutation\":147,\"firstName\":\"John\",\"lastName\":\"Doe\",\"leading\":true}]}]'\n\n" .
                "Salutation codes: 147=Mr, 148=Mrs, 149=Ms, 150=Miss"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hotel     = $input->getOption('hotel');
        $from      = $input->getOption('from');
        $to        = $input->getOption('to');
        $roomsJson = $input->getOption('rooms-json');

        if (!$hotel || !$from || !$to || !$roomsJson) {
            $output->getErrorOutput()->writeln('[INPUT_ERROR] --hotel, --from, --to, and --rooms-json are required');
            return self::EXIT_INPUT;
        }

        $rooms = json_decode((string) $roomsJson, true);
        if (!is_array($rooms) || empty($rooms)) {
            $output->getErrorOutput()->writeln('[INPUT_ERROR] --rooms-json must be a non-empty JSON array');
            return self::EXIT_INPUT;
        }

        $currency  = (int) ($input->getOption('currency') ?? $this->config->get('currency', 769));
        $email     = (string) ($input->getOption('email') ?? '');
        $reference = (string) ($input->getOption('reference') ?? '');

        $roomsXml = '';
        foreach ($rooms as $runno => $room) {
            $passengersXml = '';
            foreach (($room['passengers'] ?? []) as $p) {
                $leading        = ($p['leading'] ?? false) ? 'yes' : 'no';
                $passengersXml .= sprintf(
                    '<passenger leading="%s"><salutation>%d</salutation><firstName>%s</firstName><lastName>%s</lastName></passenger>',
                    $leading,
                    (int) ($p['salutation'] ?? 147),
                    htmlspecialchars((string) ($p['firstName'] ?? '')),
                    htmlspecialchars((string) ($p['lastName'] ?? ''))
                );
            }

            $childCount  = (int) ($room['children'] ?? 0);
            if ($childCount > 0) {
                $ages = $room['childAges'] ?? array_fill(0, $childCount, 10);
                $childElements = '';
                foreach ((array) $ages as $i => $age) {
                    $childElements .= sprintf('<child runno="%d">%d</child>', $i, (int) $age);
                }
                $childrenXml = sprintf('<children no="%d">%s</children>', $childCount, $childElements);
            } else {
                $childrenXml = '<children no="0"></children>';
            }

            $adults      = (int) ($room['adults']      ?? 2);
            $nationality = (int) ($room['nationality']  ?? $this->config->get('nationality', 66));
            $residence   = (int) ($room['residence']    ?? $this->config->get('residence', 66));

            $roomsXml .= sprintf(
                '<room runno="%d">
            <roomTypeCode>%s</roomTypeCode>
            <selectedRateBasis>%d</selectedRateBasis>
            <allocationDetails>%s</allocationDetails>
            <adultsCode>%d</adultsCode>
            <actualAdults>%d</actualAdults>
            %s
            <actualChildren no="0"></actualChildren>
            <extraBed>0</extraBed>
            <passengerNationality>%d</passengerNationality>
            <passengerCountryOfResidence>%d</passengerCountryOfResidence>
            <passengersDetails>%s</passengersDetails>
            <specialRequests count="0"></specialRequests>
            <beddingPreference>0</beddingPreference>
        </room>',
                $runno,
                htmlspecialchars((string) ($room['roomTypeCode'] ?? '')),
                (int) ($room['selectedRateBasis'] ?? 0),
                htmlspecialchars((string) ($room['allocationDetails'] ?? '')),
                $adults,
                $adults,
                $childrenXml,
                $nationality,
                $residence,
                $passengersXml
            );
        }

        $body = sprintf(
            '<bookingDetails>
    <fromDate>%s</fromDate>
    <toDate>%s</toDate>
    <currency>%d</currency>
    <productId>%s</productId>
    <sendCommunicationTo>%s</sendCommunicationTo>
    <customerReference>%s</customerReference>
    <rooms no="%d">
        %s
    </rooms>
</bookingDetails>',
            htmlspecialchars((string) $from),
            htmlspecialchars((string) $to),
            $currency,
            htmlspecialchars((string) $hotel),
            htmlspecialchars($email),
            htmlspecialchars($reference),
            count($rooms),
            $roomsXml
        );

        return $this->executeXml('confirmbooking', $body, $output);
    }
}
