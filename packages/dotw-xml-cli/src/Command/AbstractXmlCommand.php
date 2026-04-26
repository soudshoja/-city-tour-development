<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Command;

use Dotw\XmlCli\Config\Loader;
use Dotw\XmlCli\Dotw\XmlClient;
use Dotw\XmlCli\Output\Formatter;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for all DOTW XML pass-through commands.
 *
 * Output modes (mutually exclusive; --json wins if both passed):
 *   default  — pretty-printed XML
 *   --raw    — unformatted XML string as-is from DOTW
 *   --json   — XML converted to JSON via SimpleXMLElement
 *
 * Exit codes:
 *   0 = DOTW <successful>TRUE</successful>
 *   1 = DOTW <successful>FALSE</successful> (API-level error)
 *   2 = HTTP / network error (Guzzle exception)
 *   3 = Input validation error
 *   4 = Internal / unexpected error
 */
abstract class AbstractXmlCommand extends Command
{
    public const EXIT_SUCCESS  = 0;
    public const EXIT_DOTW_API = 1;
    public const EXIT_NETWORK  = 2;
    public const EXIT_INPUT    = 3;
    public const EXIT_INTERNAL = 4;

    protected Loader    $config;
    protected XmlClient $client;
    protected bool      $jsonMode = false;
    protected bool      $rawMode  = false;

    protected function configure(): void
    {
        $this
            ->addOption('json',    null, InputOption::VALUE_NONE, 'Output as JSON (machine-readable)')
            ->addOption('raw',     null, InputOption::VALUE_NONE, 'Output raw unformatted XML')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Config profile name', null);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->jsonMode = (bool) $input->getOption('json');
        $this->rawMode  = !$this->jsonMode && (bool) $input->getOption('raw');

        $profile      = $input->getOption('profile');
        $this->config = new Loader($profile);
        $this->client = new XmlClient($this->config->all());
    }

    /**
     * Send the XML body to DOTW and handle output/exit code.
     *
     * Concrete commands build $bodyXml (everything inside <request>)
     * and call this method as their final return value.
     *
     * @param string $dotwCommand  e.g. "searchhotels", "getrooms"
     * @param string $bodyXml      XML body (no <request> wrapper — XmlClient adds envelope)
     */
    protected function executeXml(
        string          $dotwCommand,
        string          $bodyXml,
        OutputInterface $output,
    ): int {
        try {
            $rawXml = $this->client->send($dotwCommand, $bodyXml);
        } catch (GuzzleException $e) {
            return $this->writeError($output, 'NETWORK_ERROR', $e->getMessage(), self::EXIT_NETWORK);
        } catch (\Throwable $e) {
            return $this->writeError($output, 'INTERNAL_ERROR', $e->getMessage(), self::EXIT_INTERNAL);
        }

        // Check <successful> — any position in response
        if (preg_match('/<successful>(.*?)<\/successful>/si', $rawXml, $m)) {
            $success = strtoupper(trim($m[1]));
            if ($success !== 'TRUE') {
                // Extract error details if present
                $code    = '';
                $details = '';
                if (preg_match('/<code>(.*?)<\/code>/si', $rawXml, $cm)) {
                    $code = trim($cm[1]);
                }
                if (preg_match('/<details>(.*?)<\/details>/si', $rawXml, $dm)) {
                    $details = trim($dm[1]);
                }
                $msg = $code ? "[{$code}] {$details}" : "DOTW returned successful=FALSE";
                $this->writeError($output, 'DOTW_API_ERROR', $msg, self::EXIT_DOTW_API);
                // Still print the response so caller can inspect
                $this->writeXml($rawXml, $output);
                return self::EXIT_DOTW_API;
            }
        }

        $this->writeXml($rawXml, $output);
        return self::EXIT_SUCCESS;
    }

    private function writeXml(string $rawXml, OutputInterface $output): void
    {
        if ($this->jsonMode) {
            $output->writeln(Formatter::toJson($rawXml));
        } elseif ($this->rawMode) {
            $output->writeln($rawXml);
        } else {
            $output->writeln(Formatter::prettyPrint($rawXml));
        }
    }

    private function writeError(
        OutputInterface $output,
        string          $code,
        string          $message,
        int             $exitCode,
    ): int {
        $errOut = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;
        $errOut->writeln("[{$code}] {$message}");
        return $exitCode;
    }
}
