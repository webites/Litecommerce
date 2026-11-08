<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class MediaThumbnailTest extends TestCase
{
    use UsesIsolatedStorage;

    private string $previousMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);

        // GD decoding real pixel buffers needs headroom ImageThumbnailer's own memory
        // guard otherwise (correctly) refuses to spend — deterministic regardless of how
        // much the rest of the suite has already allocated by the time this test runs.
        $this->previousMemoryLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '256M');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
        parent::tearDown();
    }

    public function test_uploading_a_large_image_generates_thumbnails_smaller_than_the_original_only(): void
    {
        $media = app(MediaLibrary::class)->store($this->pngContents(2000, 1000), 'logo.png', 'image/png');

        $this->assertSame(2000, $media->width);
        $this->assertSame(1000, $media->height);
        // 320/640/960/1280 are all strictly smaller than 2000 — every configured width is generated.
        $this->assertSame([320, 640, 960, 1280], array_keys($media->variants));

        foreach ($media->variants as $width => $url) {
            $this->assertNotNull($url);
            $path = parse_url((string) $url, PHP_URL_PATH);
            $this->assertStringContainsString("-{$width}w.png", (string) $path);
            $this->assertTrue(Storage::disk('public')->exists(ltrim((string) str_replace('/storage/', '', $path), '/')));
        }
    }

    public function test_uploading_a_small_image_never_upscales(): void
    {
        $media = app(MediaLibrary::class)->store($this->pngContents(100, 60), 'small.png', 'image/png');

        $this->assertSame(100, $media->width);
        $this->assertSame(60, $media->height);
        $this->assertSame([], $media->variants);
    }

    public function test_srcset_lists_every_variant_plus_the_original_as_the_largest_candidate(): void
    {
        $media = app(MediaLibrary::class)->store($this->pngContents(1000, 500), 'banner.png', 'image/png');

        $srcset = $media->srcset();
        $this->assertNotNull($srcset);

        $parts = array_map(static fn (string $part): array => explode(' ', trim($part)), explode(',', $srcset));
        $widths = array_map(static fn (array $part): int => (int) rtrim($part[1], 'w'), $parts);

        // 320/640/960 generated (< 1000), plus the original itself (1000w) as the largest — never 1280 (would upscale).
        $this->assertSame([320, 640, 960, 1000], $widths);
    }

    public function test_a_non_image_upload_gets_no_variants_and_no_srcset(): void
    {
        $media = app(MediaLibrary::class)->store('%PDF-1.4 not a real pdf either, just bytes', 'file.pdf', 'application/pdf');

        $this->assertNull($media->width);
        $this->assertNull($media->height);
        $this->assertSame([], $media->variants);
        $this->assertNull($media->srcset());
    }

    public function test_deleting_media_removes_its_thumbnail_files_too(): void
    {
        $media = app(MediaLibrary::class)->store($this->pngContents(2000, 1000), 'logo.png', 'image/png');
        $variantPaths = array_map(
            static fn (string $url): string => ltrim((string) str_replace('/storage/', '', (string) parse_url($url, PHP_URL_PATH)), '/'),
            array_values($media->variants),
        );
        foreach ($variantPaths as $path) {
            $this->assertTrue(Storage::disk('public')->exists($path));
        }

        app(MediaLibrary::class)->delete($media->id);

        foreach ($variantPaths as $path) {
            $this->assertFalse(Storage::disk('public')->exists($path));
        }
    }

    private function pngContents(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 100, 120, 200));

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
