<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DotwService;
use ReflectionClass;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Unit tests for DotwService private parser methods.
 *
 * Both parseCountryList() and parseCityList() are private methods that accept
 * a SimpleXMLElement $response (the full parsed DOTW response) and return an
 * array of ['code' => string, 'name' => string] pairs.
 *
 * We access them via ReflectionClass::getMethod() so production visibility
 * remains private — no source changes needed.
 *
 * @covers \App\Services\DotwService
 */
class DotwServiceParserTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Reflection helpers
    // -------------------------------------------------------------------------

    /**
     * Build a DotwService instance without triggering the constructor's
     * credential-loading logic (which requires DB rows or env vars).
     */
    private function makeService(): DotwService
    {
        return $this->getMockBuilder(DotwService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Invoke a private/protected method on $service via reflection.
     */
    private function callPrivate(DotwService $service, string $methodName, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass(DotwService::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$args);
    }

    /**
     * Wrap an XML fragment in a minimal DOTW response envelope so that
     * parseCountryList / parseCityList can locate elements via xpath('//country')
     * and xpath('//city').
     */
    private function wrapResponse(string $inner): SimpleXMLElement
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<result><successful>TRUE</successful>'
            .$inner
            .'</result>';

        return new SimpleXMLElement($xml);
    }

    // -------------------------------------------------------------------------
    // parseCountryList — child-element access (current correct behaviour)
    // -------------------------------------------------------------------------

    /**
     * Test 1 — parseCountryList reads <code> and <name> as child elements.
     *
     * The live DOTW getallcountries response uses child elements, not XML attributes:
     *   <country runno="0"><name>UAE</name><code>88</code></country>
     *
     * This test verifies the parser correctly reads those child elements and
     * returns the expected ['code', 'name'] pair.
     */
    public function test_parse_country_list_reads_child_code_element(): void
    {
        $response = $this->wrapResponse(
            '<countries count="1">'
            .'<country runno="0"><name>UAE</name><code>88</code></country>'
            .'</countries>'
        );

        $service = $this->makeService();
        /** @var array<int, array{code: string, name: string}> $result */
        $result = $this->callPrivate($service, 'parseCountryList', $response);

        $this->assertCount(1, $result, 'Exactly one country should be parsed');
        $this->assertSame('88', $result[0]['code'], 'code must be read from <code> child element');
        $this->assertSame('UAE', $result[0]['name'], 'name must be read from <name> child element');
        $this->assertNotEmpty($result[0]['code'], 'code must not be empty string');
    }

    /**
     * Test 2 — REGRESSION GUARD: attribute-shape XML yields empty code.
     *
     * This test documents and enforces that parseCountryList uses child-element
     * access ($country->code), NOT XML attribute access ($country['code']).
     *
     * The old buggy implementation used $country['code'] which reads the 'code'
     * XML attribute. The DOTW response never has a 'code' attribute on <country>;
     * attributes present are 'runno' only. Therefore attribute access always
     * returns an empty string.
     *
     * If someone reverts the parser back to attribute access, XML shaped like
     *   <country code="88" name="UAE"/>
     * would then return the correct values — breaking this test.
     *
     * Scenario tested: pass XML where code and name are attributes ONLY
     * (no child elements). The child-element parser must return empty strings
     * for both fields because no child elements exist.
     *
     * This test MUST pass at all times: if it starts failing it means the
     * parser was changed back to attribute access — which is the wrong shape
     * for the live DOTW response.
     */
    public function test_parse_country_list_with_attribute_code_returns_empty_code(): void
    {
        // XML shape: code and name as XML attributes only — no child elements.
        // The correct child-element parser will find no <code> or <name> children,
        // so both should resolve to empty string via ($country->code ?? '').
        $response = $this->wrapResponse(
            '<countries count="1">'
            .'<country runno="0" code="88" name="UAE"/>'
            .'</countries>'
        );

        $service = $this->makeService();
        /** @var array<int, array{code: string, name: string}> $result */
        $result = $this->callPrivate($service, 'parseCountryList', $response);

        $this->assertCount(1, $result, 'Parser must still return one entry even for attribute-only XML');

        // The child-element parser must return empty string when no <code> child exists.
        // If this assertion fails, the parser was reverted to attribute access ($country['code']),
        // which is WRONG for the real DOTW XML shape.
        $this->assertSame(
            '',
            $result[0]['code'],
            'Attribute-only XML must produce empty code — confirms parser uses child elements, not attributes'
        );
    }

    // -------------------------------------------------------------------------
    // parseCityList — child-element access
    // -------------------------------------------------------------------------

    /**
     * Test 3 — parseCityList reads <code> and <name> as child elements.
     *
     * Mirror of test_parseCountryList_reads_child_code_element for city parsing.
     * The live DOTW getservingcities response uses the same child-element shape:
     *   <city runno="0"><name>DUBAI</name><code>364</code></city>
     */
    public function test_parse_city_list_reads_child_code_element(): void
    {
        $response = $this->wrapResponse(
            '<cities count="1">'
            .'<city runno="0"><name>DUBAI</name><code>364</code></city>'
            .'</cities>'
        );

        $service = $this->makeService();
        /** @var array<int, array{code: string, name: string}> $result */
        $result = $this->callPrivate($service, 'parseCityList', $response);

        $this->assertCount(1, $result, 'Exactly one city should be parsed');
        $this->assertSame('364', $result[0]['code'], 'code must be read from <code> child element');
        $this->assertSame('DUBAI', $result[0]['name'], 'name must be read from <name> child element');
        $this->assertNotEmpty($result[0]['code'], 'code must not be empty string');
    }

    // -------------------------------------------------------------------------
    // Fixture test — count from real-shaped XML
    // -------------------------------------------------------------------------

    /**
     * Test 4 — parseCountryList returns exactly 219 items from the fixture file.
     *
     * The fixture at tests/fixtures/dotw/getallcountries-response.xml is a
     * synthetic representation of the real DOTW sandbox response shape with
     * 219 <country> child-element entries (matching the live count="219"
     * confirmed by live probe on 2026-05-06).
     *
     * This test guards against regressions that would silently drop countries
     * during parsing (e.g., if the xpath expression changed or an early-return
     * was accidentally introduced).
     */
    public function test_parse_country_list_returns_219_items_from_fixture(): void
    {
        $fixturePath = __DIR__.'/../../fixtures/dotw/getallcountries-response.xml';

        $this->assertFileExists($fixturePath, 'Fixture file must exist at tests/fixtures/dotw/getallcountries-response.xml');

        $xml = file_get_contents($fixturePath);
        $this->assertNotEmpty($xml, 'Fixture file must not be empty');

        $response = new SimpleXMLElement((string) $xml);

        $service = $this->makeService();
        /** @var array<int, array{code: string, name: string}> $result */
        $result = $this->callPrivate($service, 'parseCountryList', $response);

        $this->assertCount(219, $result, 'parseCountryList must return exactly 219 items from the fixture');

        // Spot-check UAE (code 88) is present
        $codes = array_column($result, 'code');
        $this->assertContains('88', $codes, 'UAE (code 88) must be present in parsed results');
    }
}
