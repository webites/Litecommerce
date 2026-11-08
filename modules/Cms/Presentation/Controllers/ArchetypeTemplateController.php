<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Catalog\Contracts\CategoryCatalog;
use Litecommerce\Modules\Cms\Application\DeleteArchetypeTemplate;
use Litecommerce\Modules\Cms\Application\GetOrCreateArchetypeTemplate;
use Litecommerce\Modules\Cms\Application\UpdateArchetypeTemplate;
use Litecommerce\Modules\Cms\Application\UpdateArchetypeTemplateData;
use Litecommerce\Modules\Cms\Application\UpdatePageContent;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Presentation\Concerns\ResolvesEditingLocale;
use Litecommerce\Modules\Cms\Presentation\Requests\SaveArchetypeTemplateRequest;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Panel screen for the merchant's whole-archetype overrides (category, brand,
 * ...) — every registered ArchetypeRegistry entry is listed here, whether or
 * not a CmsPage row exists for it yet; a row is only created lazily, when the
 * merchant actually opens the builder, never merely by being registered.
 */
final class ArchetypeTemplateController extends Controller
{
    use ResolvesEditingLocale;

    public function index(LanguageRepository $languages, ArchetypeRegistry $registry): Response
    {
        $locale = $this->defaultLocale($languages);
        $pages = CmsPage::query()->whereNotNull('archetype')->get()->keyBy('archetype');

        return Inertia::render('Panel/Cms/Archetypes/Index', [
            'archetypes' => array_map(static function (array $archetype) use ($pages): array {
                $page = $pages->get($archetype['key']);

                return [
                    'key' => $archetype['key'],
                    'label' => $archetype['label'],
                    'hasOverride' => $page !== null && $page->status === PageStatus::Published->value,
                ];
            }, $registry->all()),
        ]);
    }

    public function edit(string $key, Request $request, LanguageRepository $languages, ArchetypeRegistry $registry, GetOrCreateArchetypeTemplate $getOrCreate, CategoryCatalog $categories, ActiveThemeResolver $theme, GlobalTagRegistry $globalTags): Response
    {
        $archetype = $this->findRegistered($registry, $key);
        $defaultLocale = $this->defaultLocale($languages);
        $locale = $this->resolveRequestedLocale((string) $request->query('locale', ''), $languages);
        // GetOrCreateArchetypeTemplate always guarantees the DEFAULT locale's row
        // exists — the requested locale (if different) may still have none yet.
        $page = $getOrCreate->handle($key, $archetype['label'], $defaultLocale);
        $translations = $page->translations;
        $translation = $translations->firstWhere('locale', $locale);
        $isUntranslated = $translation === null;
        $sourceTranslation = $translation ?? $translations->firstWhere('locale', $defaultLocale);
        abort_if($sourceTranslation === null, 404);

        $blocks = config('cms.blocks');
        $categoryOptions = array_map(
            static fn (mixed $category): array => ['label' => $category->name, 'value' => $category->id],
            $categories->listAll($locale),
        );
        $blocks['product-grid']['fields']['category_id']['options'] = [
            ['label' => '— dowolna —', 'value' => ''],
            ...$categoryOptions,
        ];

        return Inertia::render('Panel/Cms/Edit', [
            'blocks' => $blocks,
            'rootFields' => config('cms.archetype_root_fields'),
            'themeCssUrl' => $theme->assetUrl('css/theme.css'),
            'endpoints' => [
                'productSearchUrl' => route('panel.cms.pages.products.search'),
                'imageUploadUrl' => route('panel.cms.media.store'),
            ],
            'saveUrl' => route('panel.cms.archetypes.update', $key),
            'backUrl' => route('panel.cms.archetypes.index'),
            'backLabel' => 'Wróć do listy szablonów',
            'editUrl' => route('panel.cms.archetypes.edit', $key),
            'currentLocale' => $locale,
            'isUntranslated' => $isUntranslated,
            'languages' => $this->languageSwitcherOptions($languages, $translations),
            'availableTags' => $archetype['availableTags'],
            'generalTags' => $this->tagsToMap($globalTags->all()),
            'data' => [
                'content' => $sourceTranslation->content['content'] ?? [],
                'root' => ['props' => [
                    'title' => $sourceTranslation->title,
                ]],
            ],
        ]);
    }

    public function save(SaveArchetypeTemplateRequest $request, string $key, ArchetypeRegistry $registry, UpdateArchetypeTemplate $updater, UpdatePageContent $contentUpdater, AuditLogger $audit): RedirectResponse
    {
        $this->findRegistered($registry, $key);
        $locale = $request->resolveLocale();
        $page = CmsPage::query()->where('archetype', $key)->firstOrFail();
        /** @var array<string, mixed> $rootProps */
        $rootProps = (array) $request->validated('root.props');
        /** @var list<array<string, mixed>> $content */
        $content = $request->validated('content');

        DB::transaction(function () use ($page, $locale, $rootProps, $content, $updater, $contentUpdater): void {
            $updater->handle($page, new UpdateArchetypeTemplateData(
                locale: $locale,
                title: (string) $rootProps['title'],
            ));
            $contentUpdater->handle($page, $locale, ['content' => $content]);
        });

        $this->audit($audit, 'cms.archetype_template.saved', $key, []);

        return back()->with('status', 'Szablon został opublikowany.');
    }

    public function restoreDefault(string $key, ArchetypeRegistry $registry, DeleteArchetypeTemplate $deleter, AuditLogger $audit): RedirectResponse
    {
        $this->findRegistered($registry, $key);
        $page = CmsPage::query()->where('archetype', $key)->first();

        if ($page !== null) {
            $deleter->handle($page);
            $this->audit($audit, 'cms.archetype_template.reset', $key, []);
        }

        return back()->with('status', 'Przywrócono domyślny szablon motywu.');
    }

    /** @return array{key: string, label: string, availableTags: array<string, string>} */
    private function findRegistered(ArchetypeRegistry $registry, string $key): array
    {
        foreach ($registry->all() as $archetype) {
            if ($archetype['key'] === $key) {
                return $archetype;
            }
        }

        throw new NotFoundHttpException("Unknown archetype [{$key}].");
    }

    /**
     * @param  list<array{name: string, label: string}>  $tags
     * @return array<string, string>
     */
    private function tagsToMap(array $tags): array
    {
        return array_column($tags, 'label', 'name');
    }

    /** @param array<string, mixed> $changes */
    private function audit(AuditLogger $audit, string $action, string $key, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) request()->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'cms_archetype_template',
            subjectId: $key,
            changes: $changes,
        ));
    }
}
