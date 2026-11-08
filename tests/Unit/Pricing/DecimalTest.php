<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use Litecommerce\Modules\Pricing\Domain\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function test_it_rounds_exact_half_up(): void
    {
        $this->assertSame('0.13', Decimal::roundHalfUp('0.125', 2));
        $this->assertSame('1.00', Decimal::roundHalfUp('0.995', 2));
        $this->assertSame('11', Decimal::roundHalfUp('10.5', 0));
        $this->assertSame('10', Decimal::roundHalfUp('10.4999999999', 0));
    }

    public function test_it_leaves_values_below_the_half_unchanged(): void
    {
        $this->assertSame('0.12', Decimal::roundHalfUp('0.124', 2));
    }

    public function test_it_matches_the_net_line_acceptance_example(): void
    {
        // 3 × 10,00 zł, VAT 23% -> netto 30,00, VAT 6,90, brutto 36,90
        $net = Decimal::roundHalfUp(Decimal::mul('10.00', '3'), 2);
        $vat = Decimal::roundHalfUp(Decimal::div(Decimal::mul($net, '23'), '100'), 2);
        $gross = Decimal::roundHalfUp(Decimal::add($net, $vat), 2);

        $this->assertSame('30.00', $net);
        $this->assertSame('6.90', $vat);
        $this->assertSame('36.90', $gross);
    }

    public function test_it_matches_the_gross_line_split_in_the_hundred_formula(): void
    {
        // 2 × 12,30 zł brutto, VAT 23% -> netto 20,00, VAT 4,60, brutto 24,60
        $gross = Decimal::roundHalfUp(Decimal::mul('12.30', '2'), 2);
        $vat = Decimal::roundHalfUp(Decimal::div(Decimal::mul($gross, '23'), Decimal::add('100', '23')), 2);
        $net = Decimal::roundHalfUp(Decimal::sub($gross, $vat), 2);

        $this->assertSame('24.60', $gross);
        $this->assertSame('4.60', $vat);
        $this->assertSame('20.00', $net);
    }

    public function test_compare(): void
    {
        $this->assertSame(-1, Decimal::compare('4.99', '5.00'));
        $this->assertSame(0, Decimal::compare('5.00', '5.00'));
        $this->assertSame(1, Decimal::compare('5.01', '5.00'));
    }
}
