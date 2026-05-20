<?php

declare(strict_types=1);

namespace Dotw\XmlCli\Dotw;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;

/**
 * DOTW V4 XML HTTP client.
 * Returns raw XML string — no parsing, no SimpleXMLElement.
 * Password is MD5-hashed at construction time.
 */
class XmlClient
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
            RequestOptions::DECODE_CONTENT  => true,
        ]);
    }

    /**
     * Send a DOTW request. Returns raw XML response body.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException on HTTP/network failure
     * @throws \RuntimeException on non-200 or non-XML response
     */
    public function send(string $command, string $bodyXml): string
    {
        $envelope = $this->wrap($command, $bodyXml);

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
                "DOTW HTTP {$response->getStatusCode()}: " . substr($body, 0, 300)
            );
        }

        return $body;
    }

    private function wrap(string $command, string $body): string
    {
        // cancelbooking XSD does not accept <product> — omit for cancel commands
        $productLine = in_array($command, ['cancelbooking'], true)
            ? ''
            : sprintf("  <product>%s</product>\n", $this->product);

        return sprintf(
            '<customer>
  <username>%s</username>
  <password>%s</password>
  <id>%s</id>
  <source>%d</source>
%s  <request command="%s">%s</request>
</customer>',
            htmlspecialchars($this->username),
            $this->passwordMd5,
            htmlspecialchars($this->companyCode),
            $this->source,
            $productLine,
            htmlspecialchars($command),
            $body
        );
    }
}
