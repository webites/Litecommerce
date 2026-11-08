<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Domain;

use Illuminate\Support\Str;
use Litecommerce\Modules\Extensions\Contracts\ExtensionInstallationException;

// Structural validation only; core/php/dependency compatibility is checked separately, against live state at install/enable time.
final class ManifestParser
{
    // A single slug, not Composer's "vendor/name" — a literal slash would
    // break the {extension} route parameter (panel.extensions.* routes),
    // which Laravel does not match across segments by default.
    private const ID_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+(-[a-z0-9.]+)?$/i';

    public function parse(string $json): ManifestData
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new ExtensionInstallationException('manifest_invalid_json', 'manifest.json does not contain valid JSON.');
        }

        foreach (['id', 'name', 'version', 'provider'] as $required) {
            if (empty($data[$required]) || ! is_string($data[$required])) {
                throw new ExtensionInstallationException('manifest_missing_field', "Manifest is missing the required field \"{$required}\".", ['field' => $required]);
            }
        }

        $id = $data['id'];
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new ExtensionInstallationException('manifest_invalid_id', "Id \"{$id}\" must be a single lowercase slug.", ['id' => $id]);
        }

        $version = $data['version'];
        if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new ExtensionInstallationException('manifest_invalid_version', "Version \"{$version}\" must be semver.", ['version' => $version]);
        }

        $type = is_string($data['type'] ?? null) ? $data['type'] : 'plugin';
        if ($type !== 'plugin') {
            // Themes are a separate package format with their own parser (Themes\Domain\ThemeManifestParser).
            throw new ExtensionInstallationException('manifest_unsupported_type', "Package type \"{$type}\" is not supported for plugins.", ['type' => $type]);
        }

        $autoload = $data['autoload']['psr-4'] ?? [];
        if (! is_array($autoload) || $autoload === []) {
            throw new ExtensionInstallationException('manifest_missing_autoload', 'Manifest must declare at least one "autoload.psr-4" prefix.');
        }

        $requiredPrefix = self::requiredNamespacePrefix($id);
        foreach (array_keys($autoload) as $prefix) {
            if (! is_string($prefix) || ! str_starts_with($prefix, $requiredPrefix)) {
                throw new ExtensionInstallationException(
                    'manifest_invalid_namespace',
                    "Namespace \"{$prefix}\" must start with \"{$requiredPrefix}\".",
                    ['prefix' => (string) $prefix, 'required' => $requiredPrefix],
                );
            }
        }

        return new ManifestData(
            id: $id,
            name: $data['name'],
            version: $version,
            type: $type,
            coreConstraint: is_string($data['core'] ?? null) ? $data['core'] : '*',
            phpConstraint: is_string($data['php'] ?? null) ? $data['php'] : '*',
            dependencies: self::parseDependencies($data['dependencies'] ?? []),
            autoloadPsr4: $autoload,
            providerClass: $data['provider'],
            permissions: self::parsePermissions($data['permissions'] ?? []),
            settings: self::parseSettings($data['settings'] ?? []),
        );
    }

    /** The PSR-4 prefix convention every namespace declared by this plugin must start with — see "Manifest" in 08-extensions.md. */
    public static function requiredNamespacePrefix(string $id): string
    {
        return 'LitecommercePlugins\\'.Str::studly($id).'\\';
    }

    /** @return list<array{id:string,version:string}> */
    private static function parseDependencies(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new ExtensionInstallationException('manifest_invalid_dependencies', '"dependencies" must be a list.');
        }

        $dependencies = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['id'] ?? null)) {
                throw new ExtensionInstallationException('manifest_invalid_dependencies_id', 'Every dependency must have an "id" field.');
            }
            $dependencies[] = ['id' => $entry['id'], 'version' => is_string($entry['version'] ?? null) ? $entry['version'] : '*'];
        }

        return $dependencies;
    }

    /** @return list<array{key:string,label:string}> */
    private static function parsePermissions(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new ExtensionInstallationException('manifest_invalid_permissions', '"permissions" must be a list.');
        }

        $permissions = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null)) {
                throw new ExtensionInstallationException('manifest_invalid_permissions_key', 'Every permission must have a "key" field.');
            }
            $permissions[] = ['key' => $entry['key'], 'label' => is_string($entry['label'] ?? null) ? $entry['label'] : $entry['key']];
        }

        return $permissions;
    }

    /** @return list<array{key:string,type:string,label:string,default:mixed}> */
    private static function parseSettings(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new ExtensionInstallationException('manifest_invalid_settings', '"settings" must be a list.');
        }

        $settings = [];
        foreach ($raw as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null)) {
                throw new ExtensionInstallationException('manifest_invalid_settings_key', 'Every setting must have a "key" field.');
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
}
