<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Domain;

/**
 * A parsed, structurally-valid manifest.json — ManifestParser is the only
 * way to build one, so by the time application code sees this object the
 * required fields, id/namespace convention and version formats are already
 * known-good. Compatibility (core/php/dependencies) is checked separately,
 * against the *current* environment, at install/enable time — a manifest
 * can be structurally valid and still incompatible.
 */
final readonly class ManifestData
{
    /**
     * @param  list<array{id:string,version:string}>  $dependencies
     * @param  array<string,string>  $autoloadPsr4  namespace prefix => path relative to the package root
     * @param  list<array{key:string,label:string}>  $permissions
     * @param  list<array{key:string,type:string,label:string,default:mixed}>  $settings
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $type,
        public string $coreConstraint,
        public string $phpConstraint,
        public array $dependencies,
        public array $autoloadPsr4,
        public string $providerClass,
        public array $permissions,
        public array $settings,
    ) {}
}
