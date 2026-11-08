<?php

declare(strict_types=1);

namespace Tests\Unit\Updates;

use InvalidArgumentException;
use Litecommerce\Modules\Installer\Contracts\EnvironmentRequirements;
use Litecommerce\Modules\Updates\Application\BuildReleasePackage;
use Litecommerce\Modules\Updates\Application\ReleaseManifestParser;
use Litecommerce\Modules\Updates\Contracts\InvalidReleaseManifestException;
use Litecommerce\Modules\Updates\Infrastructure\SodiumPackageSignatureVerifier;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ReleasePackageTest extends TestCase
{
    private string $workspace;

    private string $secretKey;

    private string $publicKey;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir().'/lc-release-'.bin2hex(random_bytes(4));
        mkdir($this->workspace.'/src/app', 0775, true);
        mkdir($this->workspace.'/src/tests', 0775, true);
        mkdir($this->workspace.'/src/storage/logs', 0775, true);
        file_put_contents($this->workspace.'/src/app/Kernel.php', '<?php // app');
        file_put_contents($this->workspace.'/src/artisan', 'artisan');
        file_put_contents($this->workspace.'/src/.env', 'APP_KEY=secret');
        file_put_contents($this->workspace.'/src/tests/FooTest.php', 'test');
        file_put_contents($this->workspace.'/src/storage/logs/laravel.log', 'log');

        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = base64_encode(sodium_crypto_sign_secretkey($pair));
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($pair));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->workspace));
    }

    public function test_the_archive_ships_only_allowed_files_and_a_signed_manifest(): void
    {
        $result = $this->builder()->build($this->workspace.'/src', $this->workspace.'/out', '0.1.0', $this->secretKey, '0.1.0');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($result['archive']));
        $names = array_map(fn (int $i): string => (string) $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        sort($names);
        $this->assertSame(['app/Kernel.php', 'artisan', 'manifest.json', 'manifest.json.sig'], $names);

        $manifest = (new ReleaseManifestParser)->parse((string) $zip->getFromName('manifest.json'));
        $this->assertSame('0.1.0', $manifest->version->toString());
        $this->assertSame('0.1.0', $manifest->upgradeableFrom?->toString());
        $this->assertSame(hash('sha256', '<?php // app'), $manifest->files['app/Kernel.php']);
        $this->assertSame(['app/Kernel.php', 'artisan'], array_keys($manifest->files));

        $verifier = new SodiumPackageSignatureVerifier([$this->publicKey]);
        $this->assertTrue($verifier->verify((string) $zip->getFromName('manifest.json'), (string) $zip->getFromName('manifest.json.sig')));
        $this->assertStringStartsWith(hash_file('sha256', $result['archive']), (string) file_get_contents($result['checksum']));
    }

    public function test_a_tampered_manifest_or_unknown_key_is_not_trusted(): void
    {
        $result = $this->builder()->build($this->workspace.'/src', $this->workspace.'/out', '0.1.0', $this->secretKey);
        $zip = new ZipArchive;
        $zip->open($result['archive']);
        $manifest = (string) $zip->getFromName('manifest.json');
        $signature = (string) $zip->getFromName('manifest.json.sig');

        $this->assertFalse((new SodiumPackageSignatureVerifier([$this->publicKey]))->verify(str_replace('0.1.0', '9.9.9', $manifest), $signature));
        $otherKey = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));
        $this->assertFalse((new SodiumPackageSignatureVerifier([$otherKey]))->verify($manifest, $signature));
        $this->assertFalse((new SodiumPackageSignatureVerifier([]))->verify($manifest, $signature));
        $this->assertFalse((new SodiumPackageSignatureVerifier([$this->publicKey]))->verify($manifest, 'not base64 !!'));
        $this->assertFalse((new SodiumPackageSignatureVerifier(['garbage', $this->publicKey]))->verify($manifest.' ', $signature));
        $this->assertTrue((new SodiumPackageSignatureVerifier(['garbage', $this->publicKey]))->verify($manifest, $signature));
    }

    public function test_pre_releases_and_bad_keys_cannot_be_built(): void
    {
        try {
            $this->builder()->build($this->workspace.'/src', $this->workspace.'/out', '0.2.0-rc.1', $this->secretKey);
            $this->fail('A pre-release must be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('stable', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->builder()->build($this->workspace.'/src', $this->workspace.'/out', '0.1.0', 'bad-key');
    }

    public function test_the_parser_refuses_paths_that_could_escape_the_installation(): void
    {
        $valid = ['format' => 1, 'version' => '0.1.0', 'min_php' => '8.3.0', 'php_extensions' => ['zip'], 'upgrade_from' => null, 'built_at' => 'now', 'files' => ['a.php' => str_repeat('a', 64)]];
        $this->assertSame('0.1.0', (new ReleaseManifestParser)->parse((string) json_encode($valid))->version->toString());

        foreach (['../evil.php', '/etc/passwd', 'a/../../b', 'a//b', 'a\\b', './x'] as $path) {
            try {
                (new ReleaseManifestParser)->parse((string) json_encode([...$valid, 'files' => [$path => str_repeat('a', 64)]]));
                $this->fail("Path {$path} must be refused.");
            } catch (InvalidReleaseManifestException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach ([['version' => '0.1.0-rc.1'], ['format' => 2], ['min_php' => 'php'], ['php_extensions' => 'zip'], ['files' => []], ['files' => ['a.php' => 'short']]] as $override) {
            try {
                (new ReleaseManifestParser)->parse((string) json_encode([...$valid, ...$override]));
                $this->fail('Manifest '.json_encode($override).' must be refused.');
            } catch (InvalidReleaseManifestException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function builder(): BuildReleasePackage
    {
        $requirements = new class implements EnvironmentRequirements
        {
            public function minimumPhpVersion(): string
            {
                return '8.3.0';
            }

            public function requiredExtensions(): array
            {
                return ['zip', 'sodium'];
            }
        };

        return new BuildReleasePackage($requirements, ['.env', 'tests', 'storage/logs']);
    }
}
