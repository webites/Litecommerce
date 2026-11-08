<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure\Repositories;

use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\CmsCacheGroups;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Cms\Contracts\MenuItemData;
use Litecommerce\Modules\Cms\Contracts\MenuItemType;
use Litecommerce\Modules\Cms\Contracts\MenuRepository;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;

final class EloquentMenuRepository implements MenuRepository
{
    public function __construct(
        private readonly CmsPageRepository $pages,
        private readonly ArchetypeRegistry $archetypes,
        private readonly ObjectCache $cache,
    ) {}

    public function resolveForLocation(string $locationId, string $locale): ?array
    {
        $resolved = $this->cache->remember(
            CmsCacheGroups::MENUS,
            "location:{$locationId}:locale:{$locale}",
            function () use ($locationId, $locale): array {
                $menu = Menu::query()->where('location_id', $locationId)->first();

                if ($menu === null) {
                    return ['assigned' => false, 'items' => []];
                }

                return [
                    'assigned' => true,
                    'items' => array_map(fn (MenuItemData $item): array => $this->itemToArray($item), $this->resolveNodes($menu->content, $locale)),
                ];
            },
        );

        if (($resolved['assigned'] ?? false) !== true) {
            return null;
        }

        return array_map(fn (array $item): MenuItemData => $this->itemFromArray($item), $resolved['items']);
    }

    /**
     * @param  array<int, mixed>  $nodes  Untyped JSON off the database — SaveMenuItemsRequest validates shape at write time, but this stays defensive against a row saved before that validation existed.
     * @return list<MenuItemData>
     */
    private function resolveNodes(array $nodes, string $locale): array
    {
        $resolved = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $item = $this->resolveNode($node, $locale);

            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /** @param  array<string, mixed>  $node */
    private function resolveNode(array $node, string $locale): ?MenuItemData
    {
        $type = MenuItemType::tryFrom(is_string($node['type'] ?? null) ? $node['type'] : '');

        if ($type === null) {
            return null;
        }

        $labelOverride = is_string($node['labelOverride'] ?? null) && $node['labelOverride'] !== '' ? $node['labelOverride'] : null;
        $targetBlank = (bool) ($node['targetBlank'] ?? false);
        $children = $this->resolveNodes(is_array($node['children'] ?? null) ? $node['children'] : [], $locale);

        $widgetKey = is_string($node['referenceKey'] ?? null) ? $node['referenceKey'] : null;

        return match ($type) {
            MenuItemType::Widget => new MenuItemData(
                type: $type,
                label: $labelOverride,
                url: null,
                widgetKey: $widgetKey,
                targetBlank: $targetBlank,
                children: $children,
                cssClasses: $widgetKey === null
                    ? ['sf-menu-item', 'sf-menu-item-widget']
                    : ['sf-menu-item', 'sf-menu-item-widget', "sf-menu-item-widget-{$widgetKey}"],
            ),
            MenuItemType::Custom => $this->resolveCustom($node, $labelOverride, $targetBlank, $children),
            MenuItemType::Page => $this->resolvePage($node, $locale, $labelOverride, $targetBlank, $children),
            MenuItemType::Archetype => $this->resolveArchetype($node, $locale, $labelOverride, $targetBlank, $children),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<MenuItemData>  $children
     */
    private function resolveCustom(array $node, ?string $labelOverride, bool $targetBlank, array $children): ?MenuItemData
    {
        $url = is_string($node['url'] ?? null) && $node['url'] !== '' ? $node['url'] : null;

        if ($url === null) {
            return null;
        }

        return new MenuItemData(
            type: MenuItemType::Custom,
            label: $labelOverride ?? $url,
            url: $url,
            widgetKey: null,
            targetBlank: $targetBlank,
            children: $children,
            cssClasses: ['sf-menu-item', 'sf-menu-item-custom'],
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<MenuItemData>  $children
     */
    private function resolvePage(array $node, string $locale, ?string $labelOverride, bool $targetBlank, array $children): ?MenuItemData
    {
        $pageId = is_numeric($node['referenceId'] ?? null) ? (int) $node['referenceId'] : null;

        if ($pageId === null) {
            return null;
        }

        $page = $this->pages->findPublishedTitleAndSlug($pageId, $locale);

        if ($page === null) {
            return null;
        }

        return new MenuItemData(
            type: MenuItemType::Page,
            label: $labelOverride ?? $page['title'],
            url: "/{$locale}/{$page['slug']}",
            widgetKey: null,
            targetBlank: $targetBlank,
            children: $children,
            cssClasses: ['sf-menu-item', 'sf-menu-item-page', "sf-menu-item-page-{$pageId}"],
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<MenuItemData>  $children
     */
    private function resolveArchetype(array $node, string $locale, ?string $labelOverride, bool $targetBlank, array $children): ?MenuItemData
    {
        $key = is_string($node['referenceKey'] ?? null) ? $node['referenceKey'] : null;
        $id = is_scalar($node['referenceId'] ?? null) ? (string) $node['referenceId'] : null;

        if ($key === null || $id === null) {
            return null;
        }

        $instance = $this->archetypes->resolveInstance($key, $id, $locale);

        if ($instance === null) {
            return null;
        }

        return new MenuItemData(
            type: MenuItemType::Archetype,
            label: $labelOverride ?? $instance['title'],
            url: "/{$locale}/{$instance['slug']}",
            widgetKey: null,
            targetBlank: $targetBlank,
            children: $children,
            cssClasses: ['sf-menu-item', 'sf-menu-item-archetype', "sf-menu-item-archetype-{$key}", "sf-menu-item-archetype-{$key}-{$id}"],
        );
    }

    /** @return array<string, mixed> */
    private function itemToArray(MenuItemData $item): array
    {
        return [
            'type' => $item->type->value,
            'label' => $item->label,
            'url' => $item->url,
            'widget_key' => $item->widgetKey,
            'target_blank' => $item->targetBlank,
            'children' => array_map(fn (MenuItemData $child): array => $this->itemToArray($child), $item->children),
            'css_classes' => $item->cssClasses,
        ];
    }

    /** @param array<string, mixed> $item */
    private function itemFromArray(array $item): MenuItemData
    {
        return new MenuItemData(
            type: MenuItemType::from((string) $item['type']),
            label: is_string($item['label']) ? $item['label'] : null,
            url: is_string($item['url']) ? $item['url'] : null,
            widgetKey: is_string($item['widget_key']) ? $item['widget_key'] : null,
            targetBlank: (bool) $item['target_blank'],
            children: array_map(fn (array $child): MenuItemData => $this->itemFromArray($child), $item['children']),
            cssClasses: array_values(array_filter($item['css_classes'], 'is_string')),
        );
    }
}
