<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Domain;

use Litecommerce\Modules\Themes\Contracts\ThemeInstallationException;

/**
 * Structural validation of a theme's manifest.json — an entirely separate
 * format from a plugin manifest (Extensions\Domain\ManifestParser): no
 * `provider`/`autoload`/`permissions`, since a theme package ships no PHP
 * at all — see "Zakres i świadome cięcia"
 * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 */
final class ThemeManifestParser
{
    // Same single-slug convention as a plugin id — a theme id also ends up
    // in a route parameter (panel.themes.* routes) and in a public asset
    // URL segment (/themes/<id>/...), where a literal slash would break both.
    private const ID_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+(-[a-z0-9.]+)?$/i';

    private const ENGINES = ['blade', 'blade-react'];

    public function parse(string $json): ThemeManifestData
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new ThemeInstallationException('manifest_invalid_json', 'manifest.json does not contain valid JSON.');
        }

        foreach (['id', 'name', 'version'] as $required) {
            if (empty($data[$required]) || ! is_string($data[$required])) {
                throw new ThemeInstallationException('manifest_missing_field', "Manifest is missing the required field \"{$required}\".", ['field' => $required]);
            }
        }

        $id = $data['id'];
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new ThemeInstallationException('manifest_invalid_id', "Id \"{$id}\" must be a single lowercase slug.", ['id' => $id]);
        }

        $version = $data['version'];
        if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new ThemeInstallationException('manifest_invalid_version', "Version \"{$version}\" must be semver.", ['version' => $version]);
        }

        $type = is_string($data['type'] ?? null) ? $data['type'] : 'theme';
        if ($type !== 'theme') {
            throw new ThemeInstallationException('manifest_unsupported_type', "Package type \"{$type}\" is not supported for themes — only \"theme\".", ['type' => $type]);
        }

        $engine = is_string($data['engine'] ?? null) ? $data['engine'] : 'blade';
        if (! in_array($engine, self::ENGINES, true)) {
            $allowed = implode(', ', self::ENGINES);
            throw new ThemeInstallationException('manifest_invalid_engine', "Engine \"{$engine}\" is not supported — allowed: {$allowed}.", ['engine' => $engine, 'allowed' => $allowed]);
        }

        return new ThemeManifestData(
            id: $id,
            name: $data['name'],
            version: $version,
            engine: $engine,
            coreConstraint: is_string($data['core'] ?? null) ? $data['core'] : '*',
            requiredPlugins: self::parseRequiredPlugins($data['dependencies'] ?? []),
            settings: self::parseSettings($data['settings'] ?? []),
            supportsDarkMode: ($data['supports_dark_mode'] ?? false) === true,
            menuLocations: self::parseMenuLocations($data['menu_locations'] ?? []),
        );
    }

    /** @return list<array{id:string,version:string}> */
    private static function parseRequiredPlugins(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new ThemeInstallationException('manifest_invalid_dependencies', '"dependencies" must be a list.');
        }

        $dependencies = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null)) {
                throw new ThemeInstallationException('manifest_invalid_dependencies_id', 'Each dependency must have an "id" field.');
            }
            $dependencies[] = ['id' => $entry['id'], 'version' => is_string($entry['version'] ?? null) ? $entry['version'] : '*'];
        }

        return $dependencies;
    }

    /** @return list<array{key:string,type:string,label:string,default:mixed}> */
    private static function parseSettings(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new ThemeInstallationException('manifest_invalid_settings', '"settings" must be a list.');
        }

        $settings = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null)) {
                throw new ThemeInstallationException('manifest_invalid_settings_key', 'Each setting must have a "key" field.');
            }
            $settings[] = [
                'key' => $entry['key'],
                'type' => is_string($entry['type'] ?? null) ? $entry['type'] : 'string',
                'label' => is_string($entry['label'] ?? null) ? $entry['label'] : $entry['key'],
                'default' => $entry['default'] ?? null,
            ];
        }

        return $settings;
    }

    /**
     * Unlike parseSettings/parseRequiredPlugins above, this never throws — a
     * malformed or missing "menu_locations" simply yields no locations, the
     * same lenient convention as the one-line supports_dark_mode above.
     *
     * @return list<array{id:string,label:string}>
     */
    private static function parseMenuLocations(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $locations = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null) || $entry['id'] === '') {
                continue;
            }
            $locations[] = ['id' => $entry['id'], 'label' => is_string($entry['label'] ?? null) ? $entry['label'] : $entry['id']];
        }

        return $locations;
    }
}
