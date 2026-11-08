<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ThemeHelpersTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_shop_name_helpers_reflect_the_configured_store_name(): void
    {
        app(StoreSettingsStore::class)->set('store.name', 'Sklep Testowy');

        $this->assertSame('Sklep Testowy', getShopName());

        ob_start();
        showShopName();
        $this->assertSame('Sklep Testowy', ob_get_clean());
    }

    public function test_logo_helpers_report_nothing_set_by_default(): void
    {
        $this->assertFalse(hasLogo());
        $this->assertNull(getLogo());

        ob_start();
        showLogo();
        $this->assertSame('', ob_get_clean());
    }

    public function test_show_logo_renders_srcset_width_and_height_for_an_uploaded_image(): void
    {
        $media = app(MediaLibrary::class)->store($this->pngContents(1000, 500), 'logo.png', 'image/png');
        app(StoreSettingsStore::class)->set('store.logo_media_id', $media->id);
        app(StoreSettingsStore::class)->set('store.name', 'Sklep Testowy');

        $this->assertTrue(hasLogo());
        $this->assertSame($media->url, getLogo());

        ob_start();
        showLogo();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('src="'.$media->url.'"', $html);
        $this->assertStringContainsString('srcset="'.$media->srcset().'"', $html);
        $this->assertStringContainsString('width="1000"', $html);
        $this->assertStringContainsString('height="500"', $html);
        $this->assertStringContainsString('alt="Sklep Testowy"', $html);
    }

    public function test_show_logo_lets_caller_attributes_override_the_computed_alt(): void
    {
        $media = app(MediaLibrary::class)->store($this->pngContents(100, 50), 'logo.png', 'image/png');
        app(StoreSettingsStore::class)->set('store.logo_media_id', $media->id);

        ob_start();
        showLogo(['alt' => 'Custom alt', 'class' => 'site-logo']);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('alt="Custom alt"', $html);
        $this->assertStringContainsString('class="site-logo"', $html);
    }

    public function test_is_blog_enabled_reflects_the_setting(): void
    {
        app(StoreSettingsStore::class)->set('blog.enabled', false);
        $this->assertFalse(isBlogEnabled());

        app(StoreSettingsStore::class)->set('blog.enabled', true);
        $this->assertTrue(isBlogEnabled());
    }

    public function test_show_image_renders_nothing_for_a_null_url(): void
    {
        ob_start();
        showImage(null, 'irrelevant 320w', '50vw', 100, 50);
        $this->assertSame('', ob_get_clean());
    }

    public function test_show_image_renders_every_attribute_when_given(): void
    {
        ob_start();
        showImage('https://example.test/a.jpg', 'https://example.test/a-320.jpg 320w', '50vw', 320, 200, ['alt' => 'A photo', 'loading' => 'lazy']);
        $html = (string) ob_get_clean();

        $this->assertSame(
            '<img alt="A photo" loading="lazy" src="https://example.test/a.jpg" srcset="https://example.test/a-320.jpg 320w" sizes="50vw" width="320" height="200">',
            $html,
        );
    }

    public function test_show_image_omits_sizes_without_srcset_and_skips_absent_dimensions(): void
    {
        ob_start();
        showImage('https://example.test/a.jpg', null, '50vw');
        $html = (string) ob_get_clean();

        $this->assertSame('<img src="https://example.test/a.jpg">', $html);
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
