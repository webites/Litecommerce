<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Scans modules/<Module>/{Domain,Application,Infrastructure,Presentation,Providers}
 * for references into another module's private layers. Only the Contracts
 * namespace of another module may be imported — anything else is a
 * boundary violation.
 *
 * Limitation: grouped `use Foo\{Bar, Baz};` isn't fully tokenized as a
 * single qualified name and may go undetected. Consider qossmic/deptrac
 * if boundary rules grow beyond this one.
 */
final class ModuleBoundaryScanner
{
    private const SCANNED_LAYERS = ['Domain', 'Application', 'Infrastructure', 'Presentation', 'Providers'];

    private const FORBIDDEN_LAYERS = ['Domain', 'Application', 'Infrastructure', 'Presentation', 'Providers'];

    /**
     * @return list<string> human-readable violation descriptions
     */
    public function scan(string $modulesPath, string $rootNamespace = 'Litecommerce\\Modules'): array
    {
        $violations = [];

        foreach ($this->modules($modulesPath) as $moduleName => $modulePath) {
            foreach ($this->phpFilesIn($modulePath, self::SCANNED_LAYERS) as $file) {
                $violations = [...$violations, ...$this->violationsInFile($file, $moduleName, $rootNamespace)];
            }
        }

        return $violations;
    }

    /**
     * @return array<string, string>
     */
    private function modules(string $modulesPath): array
    {
        if (! is_dir($modulesPath)) {
            return [];
        }

        $modules = [];

        foreach (scandir($modulesPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $modulesPath.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $modules[$entry] = $path;
            }
        }

        return $modules;
    }

    /**
     * @param  list<string>  $layers
     * @return list<SplFileInfo>
     */
    private function phpFilesIn(string $modulePath, array $layers): array
    {
        $files = [];

        foreach ($layers as $layer) {
            $layerPath = $modulePath.DIRECTORY_SEPARATOR.$layer;

            if (! is_dir($layerPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($layerPath, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function violationsInFile(SplFileInfo $file, string $ownModule, string $rootNamespace): array
    {
        $violations = [];
        $tokens = token_get_all((string) file_get_contents($file->getPathname()));

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            [$id, $text, $line] = $token;

            if (! in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = ltrim($text, '\\');

            if (! str_starts_with($name, $rootNamespace.'\\')) {
                continue;
            }

            $segments = explode('\\', substr($name, strlen($rootNamespace) + 1));
            $referencedModule = $segments[0] ?? null;
            $referencedLayer = $segments[1] ?? null;

            if ($referencedModule === null || $referencedModule === $ownModule) {
                continue;
            }

            if ($referencedLayer !== null && in_array($referencedLayer, self::FORBIDDEN_LAYERS, true)) {
                $violations[] = sprintf(
                    '%s:%d — moduł [%s] importuje %s\%s\%s (dozwolone tylko %s\%s\Contracts)',
                    $file->getPathname(),
                    $line,
                    $ownModule,
                    $rootNamespace,
                    $referencedModule,
                    $referencedLayer,
                    $rootNamespace,
                    $referencedModule,
                );
            }
        }

        return $violations;
    }
}
