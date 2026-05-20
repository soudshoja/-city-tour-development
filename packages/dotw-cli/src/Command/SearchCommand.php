<?php

declare(strict_types=1);

namespace Dotw\Cli\Command;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Dotw\Cli\Dotw\ResponseParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * dotw:search — search hotels by city and dates.
 *
 * Usage: bin/dotw dotw:search --city=364 --from=2026-05-01 --to=2026-05-02 [--adults=2] [--json]
 */
#[AsCommand(name: 'dotw:search', description: 'Search DOTW hotels by city code and dates')]
class SearchCommand extends AbstractCommand
{

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription('Search DOTW hotels by city code and dates')
            ->addOption('city',     null, InputOption::VALUE_REQUIRED, 'DOTW city code (e.g. 364 for Dubai)')
            ->addOption('from',     null, InputOption::VALUE_REQUIRED, 'Check-in date (YYYY-MM-DD)')
            ->addOption('to',       null, InputOption::VALUE_REQUIRED, 'Check-out date (YYYY-MM-DD)')
            ->addOption('adults',   null, InputOption::VALUE_REQUIRED, 'Number of adults', 2)
            ->addOption('children', null, InputOption::VALUE_REQUIRED, 'Number of children', 0)
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Currency code (DOTW numeric, e.g. 769 for KWD)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $city = $input->getOption('city');
        $from = $input->getOption('from');
        $to   = $input->getOption('to');

        if (!$city || !$from || !$to) {
            return $this->fail($output, 'DOTW_E_INPUT', '--city, --from, and --to are required', self::EXIT_INPUT);
        }

        $params = [
            'fromDate'    => $from,
            'toDate'      => $to,
            'currency'    => (int) ($input->getOption('currency') ?? $this->config->get('currency', 769)),
            'city'        => (int) $city,
            'adults'      => (int) $input->getOption('adults'),
            'children'    => (int) $input->getOption('children'),
            'nationality' => (int) $this->config->get('nationality', 66),
            'residence'   => (int) $this->config->get('residence', 66),
            'rateBasis'   => -1,
        ];

        try {
            $client  = new Client($this->config->all());
            $bodyXml = RequestBuilder::search($params);
            $xml     = $client->send('searchhotels', $bodyXml);
            $client->assertSuccessful($xml);
            $hotels  = ResponseParser::hotels($xml);
        } catch (\RuntimeException $e) {
            return $this->fail($output, 'DOTW_E_API', $e->getMessage(), self::EXIT_DOTW_API);
        } catch (\Throwable $e) {
            return $this->fail($output, 'DOTW_E_INTERNAL', $e->getMessage(), self::EXIT_INTERNAL);
        }

        if ($this->jsonMode) {
            return $this->success($output, ['hotels' => $hotels, 'count' => count($hotels)]);
        }

        $output->writeln(sprintf('<info>Found %d hotel(s)</info>', count($hotels)));
        foreach ($hotels as $i => $h) {
            $output->writeln(sprintf(
                '  [%d] %s (ID: %s) — %s* — %s %s',
                $i + 1,
                $h['hotel_name'],
                $h['hotel_id'],
                $h['star_rating'],
                $h['currency'],
                number_format($h['min_fare'], 3)
            ));
        }
        return self::EXIT_SUCCESS;
    }
}
