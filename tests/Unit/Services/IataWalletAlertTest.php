<?php

namespace Tests\Unit\Services;

use App\Services\IataWalletAlert;
use Tests\TestCase;

class IataWalletAlertTest extends TestCase
{
    public function test_decrease_message(): void
    {
        $expected = "IATA Wallet ▼ DECREASE\n"
            . "City Travelers — IATA 12345678\n"
            . "Change: -125.500 KWD\n"
            . "Balance: 3,200.000 → 3,074.500 KWD\n"
            . "2026-06-14 13:05";

        $this->assertSame(
            $expected,
            IataWalletAlert::build('City Travelers', '12345678', 3200.0, 3074.5, 'KWD', '2026-06-14 13:05')
        );
    }

    public function test_increase_message_without_iata_number(): void
    {
        $expected = "IATA Wallet ▲ INCREASE\n"
            . "City Travelers\n"
            . "Change: +150.000 KWD\n"
            . "Balance: 100.000 → 250.000 KWD\n"
            . "2026-06-14 09:00";

        $this->assertSame(
            $expected,
            IataWalletAlert::build('City Travelers', null, 100.0, 250.0, 'KWD', '2026-06-14 09:00')
        );
    }

    public function test_blank_currency_has_no_trailing_space(): void
    {
        $msg = IataWalletAlert::build('X', '  ', 10.0, 5.0, '', 'T');
        $this->assertStringContainsString("Change: -5.000\n", $msg);
        $this->assertStringContainsString("Balance: 10.000 → 5.000\n", $msg);
        $this->assertStringNotContainsString('IATA', $msg);
    }
}
