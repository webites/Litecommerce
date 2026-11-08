<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The default merchant runtime has no Node.js, SSH or Composer: shop code must not shell out,
 * except the Scheduler module's explicit, optional crontab helper and the Backup module's optional mysqldump
 * adapter (the built-in PDO exporter is the fallback). Method calls such as PDO::exec() run SQL, not a shell.
 */
final class RuntimeIndependenceTest extends TestCase
{
    private const ALLOWED_PROCESS_PATHS = ['modules/Scheduler/', 'modules/Backup/Infrastructure/MysqldumpDatabaseDumper.php'];

    public function test_runtime_code_never_invokes_node_tooling_or_shells_out_outside_the_scheduler(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];

        foreach (['app', 'modules', 'bootstrap', 'routes', 'config', 'database'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $code = $this->withoutComments((string) file_get_contents($file->getPathname()));

                if (preg_match('/\b(npm|npx|yarn|pnpm)\s+(run|install|ci|exec)\b|[\'"]node[\'"]/', $code) === 1) {
                    $violations[] = "{$relative}: references Node tooling at runtime";
                }
                if (! $this->isAllowedProcessPath($relative) && preg_match('/(?<!->)(?<!::)\b(shell_exec|proc_open|passthru|popen|exec|system)\s*\(|Process::(run|start|fromShellCommandline)|new\s+Process\s*\(/', $code) === 1) {
                    $violations[] = "{$relative}: spawns a process";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_composer_runtime_requirements_do_not_include_node_packages(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([], array_values(array_filter(array_keys($composer['require'] ?? []), static fn (string $package): bool => str_contains($package, 'node'))));
    }

    private function withoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    private function isAllowedProcessPath(string $relative): bool
    {
        foreach (self::ALLOWED_PROCESS_PATHS as $allowed) {
            if (str_starts_with($relative, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
