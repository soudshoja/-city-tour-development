<?php
declare(strict_types=1);
namespace Dotw\XmlCli\Tests\Unit;

use Dotw\XmlCli\Output\Formatter;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    public function test_pretty_print_indents_valid_xml(): void
    {
        $xml    = '<root><child>text</child></root>';
        $result = Formatter::prettyPrint($xml);
        $this->assertStringContainsString('<child>text</child>', $result);
        // DOMDocument adds indentation — child should be on its own line
        $this->assertStringContainsString("\n", $result);
    }

    public function test_pretty_print_returns_original_on_malformed_xml(): void
    {
        $broken = 'not xml at all';
        $result = Formatter::prettyPrint($broken);
        $this->assertSame($broken, $result);
    }

    public function test_to_json_returns_valid_json_for_valid_xml(): void
    {
        $xml  = '<root><item>foo</item></root>';
        $json = Formatter::toJson($xml);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('item', $data);
        $this->assertSame('foo', $data['item']);
    }

    public function test_to_json_returns_error_object_for_broken_xml(): void
    {
        $json = Formatter::toJson('broken xml <<<');
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }
}
