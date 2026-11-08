<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Domain;

final readonly class ThemeManifestData
{
    /**
     * @param  list<array{id:string,version:string}>  $requiredPlugins
     * @param  list<array{key:string,type:string,label:string,default:mixed}>  $settings
     * @param  list<array{id:string,label:string}>  $menuLocations
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $engine,
        public string $coreConstraint,
        public array $requiredPlugins,
        public array $settings,
        public bool $supportsDarkMode,
        public array $menuLocations,
    ) {}
}
