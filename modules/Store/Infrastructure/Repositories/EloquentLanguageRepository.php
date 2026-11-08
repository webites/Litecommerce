<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Repositories;

use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\StoreCacheGroups;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;

final class EloquentLanguageRepository implements LanguageRepository
{
    public function __construct(private readonly ObjectCache $cache) {}

    public function default(): ?LanguageData
    {
        foreach ($this->all() as $language) {
            if ($language->isDefault) {
                return $language;
            }
        }

        return null;
    }

    public function all(): array
    {
        $rows = $this->cache->remember(StoreCacheGroups::LANGUAGES, 'all', fn (): array => Language::query()
            ->orderBy('name')
            ->get()
            ->map(static fn (Language $language): array => [
                'code' => $language->code,
                'name' => $language->name,
                'is_default' => $language->is_default,
                'is_enabled' => $language->is_enabled,
                'noindex' => $language->noindex,
            ])
            ->all());

        return array_map(static fn (array $language): LanguageData => new LanguageData(
            code: (string) $language['code'],
            name: (string) $language['name'],
            isDefault: (bool) $language['is_default'],
            isEnabled: (bool) $language['is_enabled'],
            noindex: (bool) $language['noindex'],
        ), $rows);
    }
}
