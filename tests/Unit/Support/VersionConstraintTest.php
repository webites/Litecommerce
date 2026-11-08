<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Versioning\VersionConstraint;
use PHPUnit\Framework\TestCase;

final class VersionConstraintTest extends TestCase
{
    public function test_wildcard_and_empty_always_satisfy(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('1.2.3', '*'));
        $this->assertTrue(VersionConstraint::satisfies('1.2.3', ''));
    }

    public function test_caret_allows_same_major_at_or_above(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('1.4.0', '^1.2.3'));
        $this->assertTrue(VersionConstraint::satisfies('1.2.3', '^1.2.3'));
        $this->assertFalse(VersionConstraint::satisfies('1.2.2', '^1.2.3'));
        $this->assertFalse(VersionConstraint::satisfies('2.0.0', '^1.2.3'));
    }

    public function test_tilde_allows_same_major_minor_at_or_above(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('1.2.9', '~1.2.3'));
        $this->assertFalse(VersionConstraint::satisfies('1.3.0', '~1.2.3'));
        $this->assertFalse(VersionConstraint::satisfies('1.2.2', '~1.2.3'));
    }

    public function test_comparison_operators(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('8.3.0', '>=8.3.0'));
        $this->assertTrue(VersionConstraint::satisfies('8.4.0', '>=8.3.0'));
        $this->assertFalse(VersionConstraint::satisfies('8.2.0', '>=8.3.0'));
        $this->assertTrue(VersionConstraint::satisfies('8.2.0', '<8.3.0'));
    }

    public function test_exact_version(): void
    {
        $this->assertTrue(VersionConstraint::satisfies('1.0.0', '1.0.0'));
        $this->assertFalse(VersionConstraint::satisfies('1.0.1', '1.0.0'));
    }
}
