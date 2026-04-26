<?php

declare(strict_types=1);

namespace Dotw\Cli\Dotw;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use SimpleXMLElement;

/**
 * DOTW V4 XML API client.
 *
 * Replicates DotwService::wrapRequest() + DotwService::post() patterns
 * without any Laravel dependency. Uses Guzzle directly.
 *
 * Credential handling: password is MD5-hashed on instantiation.
 * Gzip decompression: Guzzle's decode_content handles Accept-Encoding: gzip.
 */
class Client
{
    private GuzzleClient $http;
    private string $username;
    private string $passwordMd5;
    private string $companyCode;
    private int    $source;
    private string $product;
    private string $endpoint;

    public function __construct(array $config)
    {
        $this->username    = $config['username']     ?? '';
        $this->passwordMd5 = md5($config['password'] ?? '');
        $this->companyCode = (string) ($config['company_code'] ?? '');
        $this->source      = (int)   ($config['source']        ?? 1);
        $this->product     = $config['product']      ?? 'hotel';
        $this->endpoint    = $config['endpoint']     ?? 'https://xml.dotwconnect.com/2018-09-01/Dotw.asmx';

        $this->http = new GuzzleClient([
            RequestOptions::TIMEOUT         => (int) ($config['timeout'] ?? 25),
            RequestOptions::CONNECT_TIMEOUT => 30,
            RequestOptions::DECODE_CONTENT  => true,   // auto-decompress gzip
        ]);
    }

    /**
     * Send a DOTW API request.
     *
     * @param string $command  DOTW command name (e.g. "searchhotels")
     * @param string $bodyXml  Inner XML body (everything inside <request>)
     * @return SimpleXMLElement Parsed response root element
     * @throws \RuntimeException on HTTP error or non-XML response
     */
    public function send(string $command, string $bodyXml): SimpleXMLElement
    {
        $envelope = $this->wrapRequest($command, $bodyXml);

        $response = $this->http->post($this->endpoint, [
            RequestOptions::BODY    => $envelope,
            RequestOptions::HEADERS => [
                'Content-Type'    => 'text/xml',
                'Connection'      => 'close',
                'Accept-Encoding' => 'gzip, deflate',
            ],
        ]);

        $body = (string) $response->getBody();

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                "DOTW HTTP {$response->getStatusCode()}: " . substr($body, 0, 200)
            );
        }

        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            throw new \RuntimeException(
                'DOTW response is not valid XML: ' . substr($body, 0, 200)
            );
        }

        return $xml;
    }

    /**
     * Assert that the DOTW response <successful> element equals TRUE.
     * Throws with the error code and details on failure.
     */
    public function assertSuccessful(SimpleXMLElement $xml): void
    {
        if ((string) $xml->successful !== 'TRUE') {
            $code    = (string) ($xml->request->error->code    ?? 'UNKNOWN');
            $details = (string) ($xml->request->error->details ?? 'Unknown error');
            throw new \RuntimeException("DOTW error [{$code}]: {$details}");
        }
    }

    private function wrapRequest(string $command, string $body): string
    {
        return sprintf(
            '<customer>
  <username>%s</username>
  <password>%s</password>
  <id>%s</id>
  <source>%d</source>
  <product>%s</product>
  <request command="%s">%s</request>
</customer>',
            htmlspecialchars($this->username),
            $this->passwordMd5,
            htmlspecialchars($this->companyCode),
            $this->source,
            $this->product,
            htmlspecialchars($command),
            $body
        );
    }
}
