<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Events\CommittedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Catalog\Contracts\CategoryCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductSummaryData;
use Litecommerce\Modules\Cms\Application\ArchivePage;
use Litecommerce\Modules\Cms\Application\CreatePage;
use Litecommerce\Modules\Cms\Application\CreatePageData;
use Litecommerce\Modules\Cms\Application\ResetStorefrontToThemeDefaults;
use Litecommerce\Modules\Cms\Application\SetHomepage;
use Litecommerce\Modules\Cms\Application\UpdatePage;
use Litecommerce\Modules\Cms\Application\UpdatePageContent;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Presentation\Concerns\ResolvesEditingLocale;
use Litecommerce\Modules\Cms\Presentation\Requests\SavePageRequest;
use Litecommerce\Modules\Cms\Presentation\Requests\StorePageRequest;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;

final class PageController extends Controller
{
    use ResolvesEditingLocale;

    public function index(LanguageRepository $languages): Response
    {
        $locale = $this->defaultLocale($languages);

        return Inertia::render('Panel/Cms/Index', [
            'pages' => CmsPage::query()
                ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
                ->latest('id')
                ->paginate(25)
                ->through(function (CmsPage $page): array {
                    $translation = $page->translations->first();

                    return [
                        'id' => (int) $page->getKey(),
                        'title' => $translation === null ? trans('cms::labels.no_translation') : $translation->title,
                        'slug' => $translation === null ? '' : $translation->slug,
                        'status' => $page->status,
                        'is_homepage' => $page->is_homepage,
                    ];
                }),
        ]);
    }

    public function create(LanguageRepository $languages): Response
    {
        return Inertia::render('Panel/Cms/Create', $this->formOptions($languages));
    }

    public function edit(Request $request, int $page, LanguageRepository $languages, CategoryCatalog $categories, ActiveThemeResolver $theme, GlobalTagRegistry $globalTags): Response|RedirectResponse
    {
        $page = CmsPage::find($page);

        if ($page === null) {
            return $this->pageNotFound();
        }

        $defaultLocale = $this->defaultLocale($languages);
        $locale = $this->resolveRequestedLocale((string) $request->query('locale', ''), $languages);
        $translations = $page->translations;
        $translation = $translations->firstWhere('locale', $locale);
        $isUntranslated = $translation === null;
        // No version in the requested language yet — copy the default locale's
        // content as a starting point (never saved until the merchant publishes),
        // per the user's own spec: "skopiuj z jezyka domyslnego" (copy from the
        // default language) when the target locale has no row, "zaladuj domyslnie
        // to co jest" (load whatever's there) when it does.
        $sourceTranslation = $translation ?? $translations->firstWhere('locale', $defaultLocale) ?? $translations->first();
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
            'rootFields' => config('cms.root_fields'),
            // Puck's canvas iframe renders the ACTIVE theme's own CSS (not the
            // panel's) so the in-editor preview matches the real storefront —
            // see packages/page-builder/src/PageBuilderEditor.jsx.
            'themeCssUrl' => $theme->assetUrl('css/theme.css'),
            'endpoints' => [
                'productSearchUrl' => route('panel.cms.pages.products.search'),
                'imageUploadUrl' => route('panel.cms.media.store'),
            ],
            'saveUrl' => route('panel.cms.pages.update', $page),
            'backUrl' => route('panel.cms.pages.index'),
            'backLabel' => trans('cms::labels.back_to_pages'),
            // No context-specific tags on a plain page (no "current category/brand")
            // — only whatever's registered globally, e.g. by a future plugin/theme.
            'generalTags' => array_column($globalTags->all(), 'label', 'name'),
            'editUrl' => route('panel.cms.pages.edit', $page),
            'currentLocale' => $locale,
            'isUntranslated' => $isUntranslated,
            'languages' => $this->languageSwitcherOptions($languages, $translations),
            'data' => [
                'content' => $sourceTranslation->content['content'] ?? [],
                'root' => ['props' => [
                    'title' => $sourceTranslation->title,
                    'slug' => $sourceTranslation->slug,
                    'seo_title' => $sourceTranslation->seo_title ?? '',
                    'seo_description' => $sourceTranslation->seo_description ?? '',
                    'status' => $page->status,
                ]],
            ],
        ]);
    }

    public function store(StorePageRequest $request, CreatePage $creator, AuditLogger $audit): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $page = $creator->handle($this->pageData($validated));

        $this->audit($request, $audit, 'cms.page.created', $page, ['slug' => $validated['slug'], 'status' => $validated['status']]);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CmsContentChanged));

        return redirect()->route('panel.cms.pages.edit', $page)->with('status', trans('cms::flash.page_created'));
    }

    /**
     * The page builder's single "Publish" button lands here — the whole
     * Puck document in one request, root.props (this project's own
     * title/slug/SEO/status fields, see config('cms.root_fields')) and
     * content (Puck's own block list) saved together in one transaction.
     */
    public function save(SavePageRequest $request, CmsPage $page, LanguageRepository $languages, UpdatePage $updater, UpdatePageContent $contentUpdater, AuditLogger $audit): RedirectResponse
    {
        $locale = $request->resolveLocale();
        /** @var array<string, mixed> $rootProps */
        $rootProps = (array) $request->validated('root.props');
        /** @var list<array<string, mixed>> $content */
        $content = $request->validated('content');
        $before = ['status' => $page->status];
        $wasPublished = $page->status === PageStatus::Published->value;
        $oldSlug = $page->translations()->where('locale', $locale)->value('slug');

        DB::transaction(function () use ($page, $locale, $rootProps, $content, $updater, $contentUpdater): void {
            $updater->handle($page, new CreatePageData(
                locale: $locale,
                slug: (string) $rootProps['slug'],
                title: (string) $rootProps['title'],
                seoTitle: isset($rootProps['seo_title']) && $rootProps['seo_title'] !== '' ? (string) $rootProps['seo_title'] : null,
                seoDescription: isset($rootProps['seo_description']) && $rootProps['seo_description'] !== '' ? (string) $rootProps['seo_description'] : null,
                status: (string) $rootProps['status'],
            ));
            $contentUpdater->handle($page, $locale, ['content' => $content]);
        });

        $page->refresh();
        $this->audit($request, $audit, 'cms.page.saved', $page, ['before' => $before, 'after' => ['status' => $page->status]]);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CmsContentChanged));

        $response = back()->with('status', trans('cms::flash.page_saved'));

        if ($wasPublished && $oldSlug !== null && $oldSlug !== $rootProps['slug']) {
            $response->with('redirectSuggestion', ['locale' => $locale, 'from_path' => "/{$oldSlug}", 'to_path' => '/'.$rootProps['slug']]);
        }

        return $response;
    }

    public function setHomepage(Request $request, CmsPage $page, SetHomepage $setter, AuditLogger $audit): RedirectResponse
    {
        $setter->handle($page);

        $this->audit($request, $audit, 'cms.page.homepage_set', $page, []);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CmsContentChanged));

        return back()->with('status', trans('cms::flash.page_set_as_home'));
    }

    public function archive(Request $request, CmsPage $page, ArchivePage $archiver, AuditLogger $audit): RedirectResponse
    {
        $archiver->handle($page);

        $this->audit($request, $audit, 'cms.page.archived', $page, []);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CmsContentChanged));

        return redirect()->route('panel.cms.pages.index')->with('status', trans('cms::flash.page_archived'));
    }

    /** Offered to the merchant right after activating a theme (Themes\Presentation\Controllers\Panel\ThemeController::activate()'s flash prompt) — resets the homepage's content and every archetype override to the new theme's own defaults, see ResetStorefrontToThemeDefaults. */
    public function resetToThemeDefaults(Request $request, ResetStorefrontToThemeDefaults $resetter, LanguageRepository $languages, AuditLogger $audit): RedirectResponse
    {
        $page = $resetter->handle($this->defaultLocale($languages));

        $this->audit($request, $audit, 'cms.reset_to_theme_defaults', $page, []);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CmsContentChanged));

        return back()->with('status', trans('cms::flash.theme_defaults_restored'));
    }

    public function searchProducts(Request $request, ProductCatalog $products, LanguageRepository $languages): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $results = $products->search($term, $this->defaultLocale($languages), 20);

        return response()->json([
            'data' => array_map(static fn (ProductSummaryData $product): array => [
                'id' => $product->id,
                'name' => $product->name,
            ], $results),
        ]);
    }

    public function uploadImage(Request $request, MediaLibrary $media): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('media.max_size_kb')],
        ]);
        $file = $request->file('image');
        // No MediaAttachments owner record: a block image's only reference is the media_id
        // stored inside the page's own content JSON, not a gallery like a product's images.
        $uploaded = $media->store(
            contents: $file->get(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            uploadedByStaffId: (string) $request->user()?->getAuthIdentifier(),
        );

        return response()->json(['id' => $uploaded->id, 'url' => $uploaded->url]);
    }

    private function pageNotFound(): RedirectResponse
    {
        return redirect()->route('panel.cms.pages.index')->with('error', trans('cms::flash.page_not_found'));
    }

    /** @return array<string, mixed> */
    private function formOptions(LanguageRepository $languages): array
    {
        return [
            'defaultLocale' => $this->defaultLocale($languages),
            'languages' => array_map(static fn (LanguageData $language): array => [
                'code' => $language->code,
                'name' => $language->name,
            ], array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled))),
            'statuses' => array_map(static fn (PageStatus $case): array => ['value' => $case->value, 'label' => match ($case) {
                PageStatus::Draft => 'Szkic',
                PageStatus::Published => 'Opublikowana',
                PageStatus::Archived => 'Zarchiwizowana',
            }], PageStatus::cases()),
        ];
    }

    /** @param array<string, mixed> $validated */
    private function pageData(array $validated): CreatePageData
    {
        return new CreatePageData(
            locale: (string) $validated['locale'],
            slug: (string) $validated['slug'],
            title: (string) $validated['title'],
            seoTitle: isset($validated['seo_title']) && $validated['seo_title'] !== '' ? (string) $validated['seo_title'] : null,
            seoDescription: isset($validated['seo_description']) && $validated['seo_description'] !== '' ? (string) $validated['seo_description'] : null,
            status: (string) $validated['status'],
        );
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, AuditLogger $audit, string $action, CmsPage $page, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'cms_page',
            subjectId: (string) $page->getKey(),
            changes: $changes,
        ));
    }
}
