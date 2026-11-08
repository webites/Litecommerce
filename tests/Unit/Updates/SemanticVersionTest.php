<?php

declare(strict_types=1);

namespace Tests\Unit\Updates;

use InvalidArgumentException;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SemanticVersionTest extends TestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function comparisons(): iterable
    {
        yield 'patch' => ['0.1.1', '0.1.0', 1];
        yield 'minor beats patch' => ['0.2.0', '0.1.9', 1];
        yield 'numeric not lexical' => ['0.10.0', '0.9.0', 1];
        yield 'equal with prefix' => ['v1.0.0', '1.0.0', 0];
        yield 'release beats pre-release' => ['1.0.0', '1.0.0-rc.1', 1];
        yield 'pre-release order' => ['1.0.0-rc.2', '1.0.0-rc.1', 1];
        yield 'numeric identifier below alphanumeric' => ['1.0.0-alpha.1', '1.0.0-alpha.beta', -1];
        yield 'shorter pre-release is lower' => ['1.0.0-alpha', '1.0.0-alpha.1', -1];
        yield 'build metadata ignored' => ['1.0.0+abc', '1.0.0+def', 0];
    }

    #[DataProvider('comparisons')]
    public function test_it_orders_versions_by_semver_precedence(string $left, string $right, int $expected): void
    {
        $this->assertSame($expected, SemanticVersion::parse($left)->compareTo(SemanticVersion::parse($right)));
        $this->assertSame(-$expected, SemanticVersion::parse($right)->compareTo(SemanticVersion::parse($left)));
    }

    public function test_only_versions_without_a_pre_release_are_stable(): void
    {
        $this->assertTrue(SemanticVersion::parse('v0.1.0')->isStable());
        $this->assertFalse(SemanticVersion::parse('0.1.0-beta.1')->isStable());
    }

    #[DataProvider('invalid')]
    public function test_it_rejects_malformed_versions(string $value): void
    {
        $this->assertNull(SemanticVersion::tryParse($value));
        $this->expectException(InvalidArgumentException::class);
        SemanticVersion::parse($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalid(): iterable
    {
        yield 'two parts' => ['1.0'];
        yield 'leading zero' => ['01.0.0'];
        yield 'text' => ['latest'];
        yield 'branch' => ['main'];
        yield 'empty' => [''];
    }
}
