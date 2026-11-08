<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ModuleBoundaryScannerTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = sys_get_temp_dir().'/litecommerce-boundary-fixture-'.uniqid();

        $this->writeFixture('ModuleA/Application/UsesContractOnly.php', <<<'PHP'
            <?php

            namespace Litecommerce\Modules\ModuleA\Application;

            use Litecommerce\Modules\ModuleB\Contracts\SomeContract;

            final class UsesContractOnly
            {
                public function __construct(private SomeContract $contract) {}
            }
            PHP);

        $this->writeFixture('ModuleA/Infrastructure/ImportsForeignInfrastructure.php', <<<'PHP'
            <?php

            namespace Litecommerce\Modules\ModuleA\Infrastructure;

            use Litecommerce\Modules\ModuleB\Infrastructure\SomeEloquentModel;

            final class ImportsForeignInfrastructure
            {
                public function make(): SomeEloquentModel
                {
                    return new SomeEloquentModel();
                }
            }
            PHP);

        $this->writeFixture('ModuleB/Contracts/SomeContract.php', <<<'PHP'
            <?php

            namespace Litecommerce\Modules\ModuleB\Contracts;

            interface SomeContract {}
            PHP);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);

        parent::tearDown();
    }

    public function test_it_allows_importing_another_modules_contracts(): void
    {
        $violations = (new ModuleBoundaryScanner)->scan($this->fixturesPath);

        $this->assertEmpty(array_filter(
            $violations,
            static fn (string $v): bool => str_contains($v, 'UsesContractOnly')
        ));
    }

    public function test_it_detects_import_of_another_modules_infrastructure(): void
    {
        $violations = (new ModuleBoundaryScanner)->scan($this->fixturesPath);

        $this->assertNotEmpty(array_filter(
            $violations,
            static fn (string $v): bool => str_contains($v, 'ImportsForeignInfrastructure')
        ));
    }

    private function writeFixture(string $relativePath, string $contents): void
    {
        $path = $this->fixturesPath.'/'.$relativePath;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
