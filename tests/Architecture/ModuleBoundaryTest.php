<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\ModuleBoundaryScanner;

final class ModuleBoundaryTest extends TestCase
{
    public function test_no_module_imports_another_modules_internal_layers(): void
    {
        $modulesPath = dirname(__DIR__, 2).'/modules';

        $violations = (new ModuleBoundaryScanner)->scan($modulesPath);

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
