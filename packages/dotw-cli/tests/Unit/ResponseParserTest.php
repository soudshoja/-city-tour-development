<?php

declare(strict_types=1);

namespace Dotw\Cli\Tests\Unit;

use Dotw\Cli\Dotw\ResponseParser;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Unit tests for ResponseParser.
 * Uses synthetic XML strings — no network required.
 */
class ResponseParserTest extends TestCase
{
    // -----------------------------------------------------------------------
    // cancelPreview()
    // -----------------------------------------------------------------------

    public function test_cancel_preview_parses_charge(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="cancelbooking" version="4.0">
  <services>
    <service runno="0" code="938745233">
      <cancellationPenalty>
        <charge>13.2818<formatted>13.28</formatted></charge>
        <currency>769</currency>
        <currencyShort>KWD</currencyShort>
      </cancellationPenalty>
    </service>
  </services>
  <successful>TRUE</successful>
</result>';

        $xml    = new SimpleXMLElement($xmlStr);
        $result = ResponseParser::cancelPreview($xml);

        $this->assertCount(1, $result);
        $this->assertSame('938745233', $result[0]['booking_code']);
        $this->assertEqualsWithDelta(13.2818, $result[0]['charge'], 0.0001);
        $this->assertSame('KWD', $result[0]['currency']);
    }

    public function test_cancel_preview_handles_zero_penalty(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="cancelbooking" version="4.0">
  <services>
    <service runno="0" code="12345678">
      <cancellationPenalty>
        <charge>0</charge>
        <currency>769</currency>
        <currencyShort>KWD</currencyShort>
      </cancellationPenalty>
    </service>
  </services>
  <successful>TRUE</successful>
</result>';

        $xml    = new SimpleXMLElement($xmlStr);
        $result = ResponseParser::cancelPreview($xml);

        $this->assertSame(0.0, $result[0]['charge']);
    }

    public function test_cancel_preview_handles_multiple_services(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="cancelbooking" version="4.0">
  <services>
    <service runno="0" code="111111111">
      <cancellationPenalty>
        <charge>50.000</charge>
        <currency>769</currency>
        <currencyShort>KWD</currencyShort>
      </cancellationPenalty>
    </service>
    <service runno="1" code="222222222">
      <cancellationPenalty>
        <charge>25.000</charge>
        <currency>769</currency>
        <currencyShort>KWD</currencyShort>
      </cancellationPenalty>
    </service>
  </services>
  <successful>TRUE</successful>
</result>';

        $xml    = new SimpleXMLElement($xmlStr);
        $result = ResponseParser::cancelPreview($xml);

        $this->assertCount(2, $result);
        $this->assertSame('111111111', $result[0]['booking_code']);
        $this->assertSame('222222222', $result[1]['booking_code']);
        $this->assertEqualsWithDelta(50.0, $result[0]['charge'], 0.001);
        $this->assertEqualsWithDelta(25.0, $result[1]['charge'], 0.001);
    }

    public function test_cancel_preview_returns_empty_array_for_no_services(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="cancelbooking" version="4.0">
  <services/>
  <successful>TRUE</successful>
</result>';

        $xml    = new SimpleXMLElement($xmlStr);
        $result = ResponseParser::cancelPreview($xml);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    // -----------------------------------------------------------------------
    // rooms()
    // -----------------------------------------------------------------------

    public function test_rooms_parses_rate_basis_and_fare(): void
    {
        // Minimal synthetic getrooms response matching V4 structure
        $xmlStr = '<?xml version="1.0"?>
<result command="getrooms" version="4.0">
  <currencyShort>KWD</currencyShort>
  <hotel id="1013275" name="Test Hotel">
    <rooms count="1">
      <room runno="0">
        <roomType runno="0" roomtypecode="483146225">
          <name>Standard Double</name>
          <rateBases count="1">
            <rateBasis runno="0" id="1331" description="Breakfast">
              <allocationDetails>ALLOC001</allocationDetails>
              <total>120.000</total>
              <cancellationRules>
                <rule>
                  <fromDate>2026-04-30</fromDate>
                  <charge>0</charge>
                </rule>
              </cancellationRules>
            </rateBasis>
          </rateBases>
        </roomType>
      </room>
    </rooms>
  </hotel>
  <successful>TRUE</successful>
</result>';

        $xml   = new SimpleXMLElement($xmlStr);
        $rooms = ResponseParser::rooms($xml);

        $this->assertCount(1, $rooms);
        $this->assertSame('483146225', $rooms[0]['room_type_code']);
        $this->assertSame('Standard Double', $rooms[0]['room_name']);
        $this->assertSame(1331, $rooms[0]['rate_basis_id']);
        $this->assertEqualsWithDelta(120.0, $rooms[0]['total_fare'], 0.001);
        $this->assertSame('ALLOC001', $rooms[0]['allocation_details']);
        $this->assertSame('KWD', $rooms[0]['currency']);
    }

    public function test_rooms_parses_cancellation_policy_from_first_nonzero_charge(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="getrooms" version="4.0">
  <currencyShort>KWD</currencyShort>
  <hotel id="1013275" name="Test Hotel">
    <rooms count="1">
      <room runno="0">
        <roomType runno="0" roomtypecode="123456">
          <name>Deluxe Suite</name>
          <rateBases count="1">
            <rateBasis runno="0" id="0" description="Room Only">
              <allocationDetails>ALLOC002</allocationDetails>
              <total>200.000</total>
              <cancellationRules>
                <rule>
                  <fromDate>2026-04-20</fromDate>
                  <charge>0</charge>
                </rule>
                <rule>
                  <fromDate>2026-04-28</fromDate>
                  <charge>100.000</charge>
                </rule>
              </cancellationRules>
            </rateBasis>
          </rateBases>
        </roomType>
      </room>
    </rooms>
  </hotel>
  <successful>TRUE</successful>
</result>';

        $xml   = new SimpleXMLElement($xmlStr);
        $rooms = ResponseParser::rooms($xml);

        $this->assertCount(1, $rooms);
        $this->assertStringContainsString('2026-04-28', $rooms[0]['cancellation_policy']);
        $this->assertStringContainsString('100', $rooms[0]['cancellation_policy']);
    }

    public function test_rooms_returns_multiple_rate_bases_as_separate_items(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="getrooms" version="4.0">
  <currencyShort>KWD</currencyShort>
  <hotel id="1013275" name="Test Hotel">
    <rooms count="1">
      <room runno="0">
        <roomType runno="0" roomtypecode="777888">
          <name>Executive Room</name>
          <rateBases count="2">
            <rateBasis runno="0" id="0" description="Room Only">
              <allocationDetails>ALLOC-RO</allocationDetails>
              <total>90.000</total>
              <cancellationRules/>
            </rateBasis>
            <rateBasis runno="1" id="1331" description="Breakfast">
              <allocationDetails>ALLOC-BB</allocationDetails>
              <total>110.000</total>
              <cancellationRules/>
            </rateBasis>
          </rateBases>
        </roomType>
      </room>
    </rooms>
  </hotel>
  <successful>TRUE</successful>
</result>';

        $xml   = new SimpleXMLElement($xmlStr);
        $rooms = ResponseParser::rooms($xml);

        $this->assertCount(2, $rooms);
        $this->assertSame(0, $rooms[0]['rate_basis_id']);
        $this->assertSame(1331, $rooms[1]['rate_basis_id']);
        $this->assertEqualsWithDelta(90.0, $rooms[0]['total_fare'], 0.001);
        $this->assertEqualsWithDelta(110.0, $rooms[1]['total_fare'], 0.001);
    }

    public function test_rooms_returns_empty_array_for_no_rooms(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="getrooms" version="4.0">
  <currencyShort>KWD</currencyShort>
  <hotel id="9999" name="Empty Hotel">
    <rooms count="0"/>
  </hotel>
  <successful>TRUE</successful>
</result>';

        $xml   = new SimpleXMLElement($xmlStr);
        $rooms = ResponseParser::rooms($xml);

        $this->assertIsArray($rooms);
        $this->assertCount(0, $rooms);
    }

    // -----------------------------------------------------------------------
    // hotels() — basic parsing
    // -----------------------------------------------------------------------

    public function test_hotels_parses_hotel_id_and_min_fare(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="searchhotels" version="4.0">
  <hotels>
    <hotel hotelid="1013275">
      <rooms>
        <room adults="2">
          <roomType roomtypecode="483146225">
            <name>Standard</name>
            <rateBases>
              <rateBasis id="0">
                <rateType currencyid="769">1</rateType>
                <total>99.500</total>
              </rateBasis>
              <rateBasis id="1331">
                <rateType currencyid="769">1</rateType>
                <total>120.000</total>
              </rateBasis>
            </rateBases>
          </roomType>
        </room>
      </rooms>
    </hotel>
  </hotels>
  <successful>TRUE</successful>
</result>';

        $xml    = new SimpleXMLElement($xmlStr);
        $hotels = ResponseParser::hotels($xml);

        $this->assertCount(1, $hotels);
        $this->assertSame('1013275', $hotels[0]['hotel_id']);
        $this->assertEqualsWithDelta(99.5, $hotels[0]['min_fare'], 0.001, 'min_fare should be the lowest rate basis total');
    }

    public function test_hotels_returns_empty_for_no_hotels(): void
    {
        $xmlStr = '<?xml version="1.0"?>
<result command="searchhotels" version="4.0">
  <hotels/>
  <successful>TRUE</successful>
</result>';

        $xml    = new SimpleXMLElement($xmlStr);
        $hotels = ResponseParser::hotels($xml);

        $this->assertIsArray($hotels);
        $this->assertCount(0, $hotels);
    }
}
