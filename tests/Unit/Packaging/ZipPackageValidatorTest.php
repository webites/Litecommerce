<?php

declare(strict_types=1);

namespace Tests\Unit\Packaging;

use App\Support\Packaging\PackageValidationException;
use App\Support\Packaging\ZipPackageValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipPackageValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        parent::tearDown();
    }

    /** @param array<string, string> $files */
    private function zip(array $files): ZipArchive
    {
        $path = tempnam(sys_get_temp_dir(), 'lc-zip-');
        $this->paths[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $zip->open($path);

        return $zip;
    }

    private function target(): string
    {
        $path = sys_get_temp_dir().'/lc-extract-'.bin2hex(random_bytes(4));
        $this->paths[] = $path;

        return $path;
    }

    private function codeOf(callable $callback): string
    {
        try {
            $callback();
        } catch (PackageValidationException $exception) {
            return $exception->errorCode;
        }

        return 'none';
    }

    public function test_valid_package_is_validated_and_extracted_with_safe_permissions(): void
    {
        $zip = $this->zip(['manifest.json' => '{"id":"x"}', 'src/Plugin.php' => '<?php echo 1;', 'assets/' => '']);
        $validator = new ZipPackageValidator(10_000_000, 100);
        $target = $this->target();

        $this->assertSame('{"id":"x"}', $validator->validate($zip));
        $validator->extract($zip, $target);

        $this->assertSame('<?php echo 1;', file_get_contents($target.'/src/Plugin.php'));
        $this->assertDirectoryExists($target.'/assets');
        $this->assertSame('0644', substr(sprintf('%o', fileperms($target.'/src/Plugin.php')), -4));
    }

    /** @return array<string, array{string}> */
    public static function unsafeNames(): array
    {
        return [
            'traversal' => ['a/../../evil.php'],
            'absolute' => ['/etc/evil.php'],
            'backslash' => ['a\\evil.php'],
            'drive letter' => ['C:evil.php'],
            'too long' => [str_repeat('a', 300).'.php'],
            'too deep' => [str_repeat('d/', 25).'f.php'],
        ];
    }

    #[DataProvider('unsafeNames')]
    public function test_unsafe_entry_names_are_rejected(string $name): void
    {
        $zip = $this->zip(['manifest.json' => '{}', $name => 'x']);

        $code = $this->codeOf(fn () => (new ZipPackageValidator(10_000_000, 100))->validate($zip));

        $this->assertContains($code, ['package_unsafe_path', 'package_path_traversal']);
    }

    public function test_case_insensitive_duplicate_entries_are_rejected(): void
    {
        $zip = $this->zip(['manifest.json' => '{}', 'Readme.txt' => 'a', 'README.txt' => 'b']);

        $this->assertSame('package_duplicate_entry', $this->codeOf(fn () => (new ZipPackageValidator(10_000_000, 100))->validate($zip)));
    }

    public function test_highly_compressed_entry_is_rejected_before_extraction(): void
    {
        $zip = $this->zip(['manifest.json' => '{}', 'bomb.bin' => str_repeat("\0", 5_000_000)]);

        $this->assertSame('package_suspicious_compression', $this->codeOf(fn () => (new ZipPackageValidator(100_000_000, 100))->validate($zip)));
    }

    public function test_oversized_manifest_is_rejected(): void
    {
        $zip = $this->zip(['manifest.json' => str_repeat('a', 5000)]);

        $this->assertSame('package_manifest_too_large', $this->codeOf(fn () => (new ZipPackageValidator(10_000_000, 100, 100, 1000))->validate($zip)));
    }

    public function test_extraction_enforces_the_real_byte_budget_and_removes_partial_output(): void
    {
        $zip = $this->zip(['manifest.json' => '{}', 'big.bin' => random_bytes(300_000)]);
        $target = $this->target();
        mkdir($target);

        $code = $this->codeOf(fn () => (new ZipPackageValidator(100_000, 100))->extract($zip, $target));

        $this->assertSame('package_too_large', $code);
        $this->assertDirectoryDoesNotExist($target);
    }

    private function rrmdir(string $path): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
