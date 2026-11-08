<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use Litecommerce\Modules\Catalog\Domain\GtinValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GtinValidatorTest extends TestCase
{
    /** @return list<array{string}> */
    public static function validCodes(): array
    {
        return [['96385074'], ['036000291452'], ['5901234123457'], ['10012345000017']];
    }

    #[DataProvider('validCodes')]
    public function test_it_accepts_valid_gtin_codes(string $code): void
    {
        $this->assertTrue((new GtinValidator)->isValid($code));
    }

    /** @return list<array{string}> */
    public static function invalidCodes(): array
    {
        return [['5901234123458'], ['123'], ['59012341234A7'], [' 5901234123457']];
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_invalid_gtin_codes(string $code): void
    {
        $this->assertFalse((new GtinValidator)->isValid($code));
    }
}
