<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Presentation\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\Events\CommittedEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Blog\Application\SaveBlogCategory;
use Litecommerce\Modules\Blog\Application\SaveBlogCategoryData;
use Litecommerce\Modules\Blog\Contracts\Events\BlogContentChanged;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategory;
use Litecommerce\Modules\Blog\Presentation\Concerns\ResolvesBlogLocale;
use Litecommerce\Modules\Blog\Presentation\Requests\SaveBlogCategoryRequest;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class BlogCategoryController extends Controller
{
    use ResolvesBlogLocale;

    public function index(LanguageRepository $languages): Response
    {
        $locale = $this->defaultLocale($languages);

        return Inertia::render('Panel/Blog/Categories', [
            'defaultLocale' => $locale,
            'languages' => $this->languageOptions($languages),
            'categories' => BlogCategory::query()
                ->whereNull('archived_at')
                ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
                ->latest('id')
                ->get()
                ->map(static function (BlogCategory $category): array {
                    $translation = $category->translations->first();

                    if ($translation === null) {
                        return ['id' => (int) $category->getKey(), 'name' => 'Brak tłumaczenia', 'slug' => '', 'description' => '', 'seo_title' => '', 'seo_description' => ''];
                    }

                    return [
                        'id' => (int) $category->getKey(),
                        'name' => $translation->name,
                        'slug' => $translation->slug,
                        'description' => $translation->description ?? '',
                        'seo_title' => $translation->seo_title ?? '',
                        'seo_description' => $translation->seo_description ?? '',
                    ];
                })->all(),
        ]);
    }

    public function store(SaveBlogCategoryRequest $request, SaveBlogCategory $saver, AuditLogger $audit): RedirectResponse
    {
        $category = $saver->handle(null, $this->data($request));
        $this->audit($request, $audit, 'blog.category.created', $category);

        return back()->with('status', 'Kategoria bloga została utworzona.');
    }

    public function update(SaveBlogCategoryRequest $request, int $category, SaveBlogCategory $saver, AuditLogger $audit): RedirectResponse
    {
        $categoryModel = BlogCategory::find($category);
        if ($categoryModel === null) {
            return $this->notFound();
        }

        $locale = (string) $request->validated('locale');
        $newSlug = (string) $request->validated('slug');
        $wasPublished = $categoryModel->archived_at === null;
        $oldSlug = $categoryModel->translations()->where('locale', $locale)->value('slug');

        $saver->handle($categoryModel, $this->data($request));
        $this->audit($request, $audit, 'blog.category.saved', $categoryModel);

        $response = back()->with('status', 'Kategoria bloga została zapisana.');

        if ($wasPublished && $oldSlug !== null && $oldSlug !== $newSlug) {
            $response->with('redirectSuggestion', ['locale' => $locale, 'from_path' => "/blog/category/{$oldSlug}", 'to_path' => "/blog/category/{$newSlug}"]);
        }

        return $response;
    }

    public function archive(Request $request, int $category, AuditLogger $audit): RedirectResponse
    {
        $categoryModel = BlogCategory::find($category);
        if ($categoryModel === null) {
            return $this->notFound();
        }

        $categoryModel->forceFill(['archived_at' => now()])->save();
        $this->audit($request, $audit, 'blog.category.archived', $categoryModel);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new BlogContentChanged));

        return back()->with('status', 'Kategoria bloga została zarchiwizowana.');
    }

    private function data(SaveBlogCategoryRequest $request): SaveBlogCategoryData
    {
        return new SaveBlogCategoryData(
            locale: (string) $request->validated('locale'),
            slug: (string) $request->validated('slug'),
            name: (string) $request->validated('name'),
            description: $this->nullable($request->validated('description')),
            seoTitle: $this->nullable($request->validated('seo_title')),
            seoDescription: $this->nullable($request->validated('seo_description')),
        );
    }

    private function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function notFound(): RedirectResponse
    {
        return redirect()->route('panel.blog.categories.index')->with('error', 'Kategoria bloga nie została znaleziona.');
    }

    private function audit(Request $request, AuditLogger $audit, string $action, BlogCategory $category): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'blog_category',
            subjectId: (string) $category->getKey(),
            changes: [],
        ));
    }
}
