<?php

declare(strict_types=1);

namespace Tests\Unit\Updates;

use Litecommerce\Modules\Updates\Application\CompatibilityChecker;
use Litecommerce\Modules\Updates\Application\EnvironmentProbe;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;
use PHPUnit\Framework\TestCase;

final class CompatibilityCheckerTest extends TestCase
{
    public function test_a_healthy_environment_reports_no_problems(): void
    {
        $report = $this->checker()->check($this->manifest('0.2.0'), SemanticVersion::parse('0.1.0'));

        $this->assertTrue($report->isCompatible());
    }

    public function test_every_blocking_condition_is_reported_with_its_parameters(): void
    {
        $checker = $this->checker(php: '8.2.0', extensions: [], writable: false, free: 1024);

        $codes = array_column($checker->check($this->manifest('0.1.0', 'sodium', '0.3.0'), SemanticVersion::parse('0.1.0'))->problems, 'code');

        $this->assertEqualsCanonicalizing(['not_newer', 'upgrade_from_required', 'php_too_old', 'extension_missing', 'not_writable', 'low_disk_space'], array_values(array_unique($codes)));
    }

    public function test_an_unknown_amount_of_free_space_is_not_a_problem(): void
    {
        $this->assertTrue($this->checker(free: null)->check($this->manifest('0.2.0'), SemanticVersion::parse('0.1.0'))->isCompatible());
    }

    private function manifest(string $version, string $extension = 'zip', ?string $from = null): ReleaseManifest
    {
        return new ReleaseManifest(SemanticVersion::parse($version), '8.3.0', [$extension], $from === null ? null : SemanticVersion::parse($from), 'now', ['a.php' => str_repeat('a', 64)]);
    }

    /** @param list<string>|null $extensions */
    private function checker(string $php = '8.3.5', ?array $extensions = null, bool $writable = true, ?int $free = 10_000_000_000): CompatibilityChecker
    {
        $probe = new class($php, $extensions, $writable, $free) implements EnvironmentProbe
        {
            /** @param list<string>|null $extensions */
            public function __construct(private string $php, private ?array $extensions, private bool $writable, private ?int $free) {}

            public function phpVersion(): string
            {
                return $this->php;
            }

            public function hasExtension(string $extension): bool
            {
                return $this->extensions === null || in_array($extension, $this->extensions, true);
            }

            public function isWritable(string $path): bool
            {
                return $this->writable;
            }

            public function freeBytes(): ?int
            {
                return $this->free;
            }
        };

        return new CompatibilityChecker($probe, ['app', 'vendor'], 300 * 1048576);
    }
}
