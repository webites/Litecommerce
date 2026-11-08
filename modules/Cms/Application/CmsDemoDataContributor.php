<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

final readonly class CmsDemoDataContributor implements DemoDataContributor
{
    public function __construct(
        private CreatePage $createPage,
        private StoreSettingsReader $storeSettings,
        private DemoDataContent $demoContent,
    ) {}

    public function key(): string
    {
        return 'cms';
    }

    public function priority(): int
    {
        return 400;
    }

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void
    {
        $content = $this->demoContent->load('cms::demo', $context->locale);

        $pageIds = [];
        foreach ($content['pages'] as $key => $definition) {
            $page = $this->createPage->handle(new CreatePageData(
                locale: $context->locale,
                slug: $this->availableSlug($definition['slug'], $context),
                title: $definition['title'],
                seoTitle: $definition['seo'],
                seoDescription: $definition['seo'],
                status: PageStatus::Published->value,
            ));
            CmsPageTranslation::query()->where('page_id', $page->getKey())->where('locale', $context->locale)->update([
                'content' => ['content' => $this->withLocale($definition['content'], $context->locale), 'root' => ['props' => []]],
            ]);
            $pageIds[$key] = (int) $page->getKey();
            $resources->record($context->batchId, $this->key(), 'page', (int) $page->getKey());
        }

        if (! CmsPage::query()->where('is_homepage', true)->exists()) {
            CmsPage::query()->whereKey($pageIds['start'])->update(['is_homepage' => true]);
        }

        $labels = $content['menu'];
        $menuItems = [
            $this->widget('home', $labels['home']),
            $this->widget('catalog_index', $labels['products']),
            $this->page($pageIds['about'], $labels['about']),
            $this->page($pageIds['shipping_payment'], $labels['shipping_payment']),
            $this->page($pageIds['contact'], $labels['contact']),
        ];
        if ($this->storeSettings->current()->blogEnabled) {
            $menuItems[] = $this->archetype('blog', 'index', $labels['blog']);
        }
        $menuItems[] = $this->widget('cart', null);
        $menuItems[] = $this->widget('account', null);

        $menu = Menu::query()->create([
            'name' => $labels['name'],
            'location_id' => Menu::query()->where('location_id', 'primary')->exists() ? null : 'primary',
            'content' => $menuItems,
        ]);
        $resources->record($context->batchId, $this->key(), 'menu', (int) $menu->getKey());
    }

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void
    {
        Menu::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'menu')))->delete();
        CmsPage::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'page')))->delete();
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, mixed>
     */
    private function withLocale(array $blocks, string $locale): array
    {
        array_walk_recursive($blocks, static function (mixed &$value) use ($locale): void {
            if (is_string($value)) {
                $value = str_replace('{locale}', $locale, $value);
            }
        });

        return $blocks;
    }

    private function availableSlug(string $base, DemoDataContext $context): string
    {
        if (! CmsPageTranslation::query()->where('locale', $context->locale)->where('slug', $base)->exists()) {
            return $base;
        }

        return $base.'-demo-'.strtolower(substr($context->batchId, -6));
    }

    /** @return array<string, mixed> */
    private function widget(string $key, ?string $label): array
    {
        return $this->item('widget', $key, null, null, $label);
    }

    /** @return array<string, mixed> */
    private function page(int $id, string $label): array
    {
        return $this->item('page', null, (string) $id, null, $label);
    }

    /** @return array<string, mixed> */
    private function archetype(string $key, string $id, string $label): array
    {
        return $this->item('archetype', $key, $id, null, $label);
    }

    /** @return array<string, mixed> */
    private function item(string $type, ?string $referenceKey, ?string $referenceId, ?string $url, ?string $label): array
    {
        return [
            'id' => (string) Str::ulid(),
            'type' => $type,
            'referenceKey' => $referenceKey,
            'referenceId' => $referenceId,
            'url' => $url,
            'labelOverride' => $label,
            'targetBlank' => false,
            'children' => [],
        ];
    }

    /** @param list<string> $ids @return list<int> */
    private function integers(array $ids): array
    {
        return array_map('intval', $ids);
    }
}
