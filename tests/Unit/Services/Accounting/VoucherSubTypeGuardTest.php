<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\InvalidSubTypeException;
use App\Services\Accounting\VoucherSubTypeGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * W5.L item 3 (w5-brief.md §W5.L: "doc_type RV|PV|AST accepted by the engine with sub_type
 * lists") — VoucherSubTypeGuard::assertValid(), the caller-side vocabulary check
 * (deliberately NOT enforced inside PostingService::post() itself — see that class's own
 * docblock, and VoucherSubTypeGuard's own docblock, for the regression this design avoids).
 *
 * Pure unit test: config-driven only, no DB.
 */
class VoucherSubTypeGuardTest extends TestCase
{
    public static function validPairsProvider(): array
    {
        return [
            'RV/INVOICE' => ['RV', 'INVOICE'],
            'RV/ACCOUNT' => ['RV', 'ACCOUNT'],
            'RV/TOPUP' => ['RV', 'TOPUP'],
            'RV/IMPORT' => ['RV', 'IMPORT'],
            'RV/GATEWAY_SETTLE' => ['RV', 'GATEWAY_SETTLE'],
            'PV/SUPPLIER' => ['PV', 'SUPPLIER'],
            'PV/ACCOUNT' => ['PV', 'ACCOUNT'],
            'PV/BONUS' => ['PV', 'BONUS'],
            'PV/REFUND_OUT' => ['PV', 'REFUND_OUT'],
            'PV/BY_DATE' => ['PV', 'BY_DATE'],
            'AST/LEGACY' => ['AST', 'LEGACY'],
        ];
    }

    #[DataProvider('validPairsProvider')]
    public function test_accepts_every_registered_sub_type(string $docType, string $subType): void
    {
        VoucherSubTypeGuard::assertValid($docType, $subType);
        $this->addToAssertionCount(1); // no exception == pass
    }

    public static function invalidPairsProvider(): array
    {
        return [
            'RV null subType' => ['RV', null],
            'RV unknown subType' => ['RV', 'NOT_A_REAL_SUBTYPE'],
            'PV null subType' => ['PV', null],
            'PV subType borrowed from RV' => ['PV', 'INVOICE'],
            'AST null subType' => ['AST', null],
            'AST subType other than LEGACY' => ['AST', 'SETTLE_CASH'],
        ];
    }

    #[DataProvider('invalidPairsProvider')]
    public function test_rejects_an_invalid_sub_type_for_a_governed_doc_type(string $docType, ?string $subType): void
    {
        try {
            VoucherSubTypeGuard::assertValid($docType, $subType);
            $this->fail('Expected InvalidSubTypeException to be thrown.');
        } catch (InvalidSubTypeException $e) {
            $this->assertSame($docType, $e->docType);
            $this->assertSame($subType, $e->subType);
        }
    }

    /**
     * A docType with no entry in config('accounting.sub_types') (e.g. JV, or a real PV sub_type
     * from an unrelated, already-shipped feature area like RefundPostingService's 'REFUND_DISPO')
     * must remain completely ungoverned by THIS check — validating it is this guard's caller's own
     * choice to make (or not make) explicitly.
     */
    public function test_does_not_govern_an_unregistered_doc_type(): void
    {
        VoucherSubTypeGuard::assertValid('JV', 'ANYTHING_AT_ALL');
        VoucherSubTypeGuard::assertValid('JV', null);
        $this->addToAssertionCount(2);
    }
}
