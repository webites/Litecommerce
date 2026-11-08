<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Cms\Application\CreateMenu;
use Litecommerce\Modules\Cms\Application\DeleteMenu;
use Litecommerce\Modules\Cms\Application\SaveMenuItems;
use Litecommerce\Modules\Cms\Application\UpdateMenu;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;
use Litecommerce\Modules\Cms\Presentation\Concerns\ResolvesEditingLocale;
use Litecommerce\Modules\Cms\Presentation\Requests\SaveMenuItemsRequest;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Storefront\Contracts\MenuLocationRegistry;
use Litecommerce\Modules\Storefront\Contracts\MenuWidgetRegistry;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;

/** Panel screen for the WordPress-style menu builder — see docs/knowledge-base/09-presentation.md's menu section. */
final class MenuController extends Controller
{
    use ResolvesEditingLocale;

    public function index(ActiveThemeResolver $theme, MenuLocationRegistry $locations): Response
    {
        $availableLocations = $this->combinedLocations($theme, $locations);
        $menus = Menu::query()->orderBy('name')->get();

        return Inertia::render('Panel/Cms/Menus/Index', [
            'menus' => $menus->map(static fn (Menu $menu): array => [
                'id' => $menu->getKey(),
                'name' => $menu->name,
                'locationId' => $menu->location_id,
                'locationLabel' => $availableLocations[$menu->location_id ?? ''] ?? null,
                'itemCount' => count($menu->content),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request, CreateMenu $creator, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $menu = $creator->handle($data['name']);

        $this->audit($audit, 'cms.menu.created', (string) $menu->getKey());

        return redirect()->route('panel.cms.menus.edit', $menu)->with('status', 'Menu zostało utworzone.');
    }

    /**
     * Takes the raw id (not an implicit-bound Menu) so a missing/deleted menu
     * can redirect to the listing with a message instead of a bare 404 — see
     * litecommerce-panel-ui skill's "Zasada 7" (deliberately not applied
     * anywhere else in the panel yet).
     */
    public function edit(int $menu, LanguageRepository $languages, ArchetypeRegistry $archetypes, MenuWidgetRegistry $widgets, ActiveThemeResolver $theme, MenuLocationRegistry $locationRegistry): Response|RedirectResponse
    {
        $menu = Menu::find($menu);

        if ($menu === null) {
            return $this->menuNotFound();
        }

        $locale = $this->defaultLocale($languages);

        $pages = CmsPage::query()
            ->whereNull('archetype')
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->get()
            ->map(static function (CmsPage $page): ?array {
                $translation = $page->translations->first();

                return $translation === null ? null : ['id' => (int) $page->getKey(), 'label' => $translation->title];
            })
            ->filter()
            ->values()
            ->all();

        $archetypeData = array_map(static fn (array $archetype): array => [
            'key' => $archetype['key'],
            'label' => $archetype['label'],
            'instances' => $archetypes->listInstances($archetype['key'], $locale),
        ], $archetypes->all());
        $widgetData = $widgets->all();

        return Inertia::render('Panel/Cms/Menus/Edit', [
            'menu' => [
                'id' => $menu->getKey(),
                'name' => $menu->name,
                'locationId' => $menu->location_id,
                'content' => $this->annotateAutoLabels($menu->content, $pages, $archetypeData, $widgetData),
            ],
            'locations' => $this->combinedLocationList($theme, $locationRegistry),
            'pages' => $pages,
            'archetypes' => $archetypeData,
            'widgets' => $widgetData,
        ]);
    }

    /**
     * The stored tree only carries ids/keys, not display names — without this,
     * the editor would show every "Strona"/"Archetyp"/"Widżet" row with the
     * same empty "(automatyczna etykieta)" placeholder and no way to tell
     * WHICH page/instance/widget it actually points at (a real gap the
     * merchant found: "nie widze jaki widzet jest dodany bo mam tylko
     * informacje 'Widzet'"). Resolved purely from data already fetched for
     * the picker sidebar — no extra queries.
     *
     * @param  array<int, mixed>  $nodes
     * @param  list<array{id:int,label:string}>  $pages
     * @param  list<array{key:string,label:string,instances:list<array{id:string,label:string}>}>  $archetypes
     * @param  list<array{key:string,extensionId:string,label:string}>  $widgets
     * @return list<array<string, mixed>>
     */
    private function annotateAutoLabels(array $nodes, array $pages, array $archetypes, array $widgets): array
    {
        $pageLabelsById = array_column($pages, 'label', 'id');
        $widgetLabelsByKey = array_column($widgets, 'label', 'key');
        $archetypeInstanceLabels = [];
        foreach ($archetypes as $archetype) {
            $archetypeInstanceLabels[$archetype['key']] = array_column($archetype['instances'], 'label', 'id');
        }

        $annotate = function (array $nodes) use (&$annotate, $pageLabelsById, $widgetLabelsByKey, $archetypeInstanceLabels): array {
            $result = [];
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $node['autoLabel'] = match ($node['type'] ?? null) {
                    'page' => $pageLabelsById[(int) ($node['referenceId'] ?? 0)] ?? null,
                    'archetype' => $archetypeInstanceLabels[$node['referenceKey'] ?? ''][(string) ($node['referenceId'] ?? '')] ?? null,
                    'widget' => $widgetLabelsByKey[$node['referenceKey'] ?? ''] ?? null,
                    'custom' => $node['url'] ?? null,
                    default => null,
                };
                $node['children'] = $annotate(is_array($node['children'] ?? null) ? $node['children'] : []);
                $result[] = $node;
            }

            return $result;
        };

        return $annotate($nodes);
    }

    public function update(Request $request, int $menu, UpdateMenu $updater, AuditLogger $audit): RedirectResponse
    {
        $menu = Menu::find($menu);

        if ($menu === null) {
            return $this->menuNotFound();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locationId' => ['nullable', 'string', 'max:64'],
        ]);

        $updater->handle($menu, $data['name'], $data['locationId'] ?? null);

        $this->audit($audit, 'cms.menu.updated', (string) $menu->getKey());

        return back()->with('status', 'Ustawienia menu zostały zapisane.');
    }

    public function updateItems(SaveMenuItemsRequest $request, int $menu, SaveMenuItems $saver, AuditLogger $audit): RedirectResponse
    {
        $menu = Menu::find($menu);

        if ($menu === null) {
            return $this->menuNotFound();
        }

        $saver->handle($menu, $request->tree());

        $this->audit($audit, 'cms.menu.items_updated', (string) $menu->getKey());

        return back()->with('status', 'Menu zostało zapisane.');
    }

    public function destroy(int $menu, DeleteMenu $deleter, AuditLogger $audit): RedirectResponse
    {
        $menu = Menu::find($menu);

        if ($menu === null) {
            return $this->menuNotFound();
        }

        $id = (string) $menu->getKey();
        $deleter->handle($menu);

        $this->audit($audit, 'cms.menu.deleted', $id);

        return redirect()->route('panel.cms.menus.index')->with('status', 'Menu zostało usunięte.');
    }

    private function menuNotFound(): RedirectResponse
    {
        return redirect()->route('panel.cms.menus.index')->with('error', 'Menu nie zostało znalezione.');
    }

    /** @return array<string, string> location id => label */
    private function combinedLocations(ActiveThemeResolver $theme, MenuLocationRegistry $locations): array
    {
        $result = [];
        foreach ($this->combinedLocationList($theme, $locations) as $location) {
            $result[$location['id']] = $location['label'];
        }

        return $result;
    }

    /** @return list<array{id: string, label: string}> */
    private function combinedLocationList(ActiveThemeResolver $theme, MenuLocationRegistry $locations): array
    {
        $seen = [];
        $result = [];

        foreach ([...$theme->menuLocations(), ...array_map(
            static fn (array $location): array => ['id' => $location['id'], 'label' => $location['label']],
            $locations->all(),
        )] as $location) {
            if (isset($seen[$location['id']])) {
                continue;
            }
            $seen[$location['id']] = true;
            $result[] = $location;
        }

        return $result;
    }

    private function audit(AuditLogger $audit, string $action, string $menuId): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) request()->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'cms_menu',
            subjectId: $menuId,
            changes: [],
        ));
    }
}
