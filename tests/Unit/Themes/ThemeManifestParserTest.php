<?php

declare(strict_types=1);

namespace Tests\Unit\Themes;

use Litecommerce\Modules\Themes\Contracts\ThemeInstallationException;
use Litecommerce\Modules\Themes\Domain\ThemeManifestParser;
use PHPUnit\Framework\TestCase;

final class ThemeManifestParserTest extends TestCase
{
    private function validManifest(array $overrides = []): string
    {
        return json_encode(array_merge([
            'id' => 'default-storefront',
            'name' => 'Domyslny motyw',
            'version' => '1.0.0',
            'type' => 'theme',
            'engine' => 'blade',
        ], $overrides), JSON_THROW_ON_ERROR);
    }

    public function test_parses_a_minimal_valid_manifest(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest());

        $this->assertSame('default-storefront', $manifest->id);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertSame('blade', $manifest->engine);
        $this->assertSame('*', $manifest->coreConstraint);
        $this->assertSame([], $manifest->requiredPlugins);
    }

    public function test_defaults_engine_to_blade_when_absent(): void
    {
        $data = json_decode($this->validManifest(), true);
        unset($data['engine']);

        $manifest = (new ThemeManifestParser)->parse(json_encode($data));

        $this->assertSame('blade', $manifest->engine);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->expectException(ThemeInstallationException::class);
        (new ThemeManifestParser)->parse('{not json');
    }

    public function test_rejects_a_slash_in_the_id(): void
    {
        $this->expectException(ThemeInstallationException::class);
        (new ThemeManifestParser)->parse($this->validManifest(['id' => 'acme/theme']));
    }

    public function test_rejects_plugin_type(): void
    {
        $this->expectException(ThemeInstallationException::class);
        (new ThemeManifestParser)->parse($this->validManifest(['type' => 'plugin']));
    }

    public function test_rejects_unknown_engine(): void
    {
        $this->expectException(ThemeInstallationException::class);
        (new ThemeManifestParser)->parse($this->validManifest(['engine' => 'react-native']));
    }

    public function test_rejects_invalid_version_format(): void
    {
        $this->expectException(ThemeInstallationException::class);
        (new ThemeManifestParser)->parse($this->validManifest(['version' => 'v1']));
    }

    public function test_parses_required_plugins_and_settings(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest([
            'dependencies' => [['id' => 'hello-plugin', 'version' => '^1.0']],
            'settings' => [['key' => 'accent_color', 'type' => 'string', 'label' => 'Kolor', 'default' => '#000']],
        ]));

        $this->assertSame([['id' => 'hello-plugin', 'version' => '^1.0']], $manifest->requiredPlugins);
        $this->assertSame('accent_color', $manifest->settings[0]['key']);
        $this->assertSame('#000', $manifest->settings[0]['default']);
    }

    public function test_defaults_supports_dark_mode_to_false_when_absent(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest());

        $this->assertFalse($manifest->supportsDarkMode);
    }

    public function test_parses_supports_dark_mode_when_true(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest(['supports_dark_mode' => true]));

        $this->assertTrue($manifest->supportsDarkMode);
    }

    public function test_defaults_menu_locations_to_empty_when_absent(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest());

        $this->assertSame([], $manifest->menuLocations);
    }

    public function test_parses_menu_locations(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest([
            'menu_locations' => [['id' => 'primary', 'label' => 'Menu główne'], ['id' => 'footer', 'label' => 'Stopka']],
        ]));

        $this->assertSame([
            ['id' => 'primary', 'label' => 'Menu główne'],
            ['id' => 'footer', 'label' => 'Stopka'],
        ], $manifest->menuLocations);
    }

    public function test_menu_locations_never_throws_on_malformed_entries(): void
    {
        $manifest = (new ThemeManifestParser)->parse($this->validManifest([
            'menu_locations' => 'not-a-list',
        ]));

        $this->assertSame([], $manifest->menuLocations);

        $manifest = (new ThemeManifestParser)->parse($this->validManifest([
            'menu_locations' => [['id' => 'primary'], ['label' => 'Brak id'], 'garbage', ['id' => '']],
        ]));

        $this->assertSame([['id' => 'primary', 'label' => 'primary']], $manifest->menuLocations);
    }
}
