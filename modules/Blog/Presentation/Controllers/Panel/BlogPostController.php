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
use Litecommerce\Modules\Blog\Application\SaveBlogPost;
use Litecommerce\Modules\Blog\Application\SaveBlogPostData;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Blog\Contracts\BlogStatus;
use Litecommerce\Modules\Blog\Contracts\Events\BlogContentChanged;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPost;
use Litecommerce\Modules\Blog\Presentation\Concerns\ResolvesBlogLocale;
use Litecommerce\Modules\Blog\Presentation\Requests\SaveBlogPostRequest;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class BlogPostController extends Controller
{
    use ResolvesBlogLocale;

    public function index(LanguageRepository $languages): Response
    {
        $locale = $this->defaultLocale($languages);

        return Inertia::render('Panel/Blog/Index', [
            'posts' => BlogPost::query()
                ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
                ->latest('id')
                ->paginate(25)
                ->through(static function (BlogPost $post): array {
                    $translation = $post->translations->first();
                    $scheduled = $post->status === BlogStatus::Published->value && $post->publish_at?->isFuture();

                    if ($translation === null) {
                        return [
                            'id' => (int) $post->getKey(),
                            'title' => 'Brak tłumaczenia',
                            'slug' => '',
                            'status' => $scheduled ? 'scheduled' : $post->status,
                            'date' => ($post->publish_at ?? $post->created_at)?->toAtomString(),
                        ];
                    }

                    return [
                        'id' => (int) $post->getKey(),
                        'title' => $translation->title,
                        'slug' => $translation->slug,
                        'status' => $scheduled ? 'scheduled' : $post->status,
                        'date' => ($post->publish_at ?? $post->created_at)?->toAtomString(),
                    ];
                }),
        ]);
    }

    public function create(LanguageRepository $languages, BlogCatalog $catalog): Response
    {
        $locale = $this->defaultLocale($languages);

        return Inertia::render('Panel/Blog/Create', $this->formOptions($languages, $catalog, $locale));
    }

    public function store(SaveBlogPostRequest $request, SaveBlogPost $saver, AuditLogger $audit): RedirectResponse
    {
        $post = $saver->handle(null, $this->data($request));
        $this->audit($request, $audit, 'blog.post.created', $post);

        return redirect()->route('panel.blog.posts.edit', $post)->with('status', 'Wpis został utworzony.');
    }

    public function edit(int $post, Request $request, LanguageRepository $languages, BlogCatalog $catalog, MediaLibrary $media): Response|RedirectResponse
    {
        $postModel = BlogPost::query()->with('translations')->find($post);
        if ($postModel === null) {
            return $this->notFound();
        }

        $defaultLocale = $this->defaultLocale($languages);
        $locale = $this->requestedLocale((string) $request->query('locale', ''), $languages);
        $translation = $postModel->translations->firstWhere('locale', $locale);
        $source = $translation ?? $postModel->translations->firstWhere('locale', $defaultLocale) ?? $postModel->translations->first();
        abort_if($source === null, 404);

        return Inertia::render('Panel/Blog/Edit', [
            ...$this->formOptions($languages, $catalog, $locale),
            'post' => [
                'id' => (int) $postModel->getKey(),
                'locale' => $locale,
                'isUntranslated' => $translation === null,
                'slug' => $source->slug,
                'title' => $source->title,
                'excerpt' => $source->excerpt ?? '',
                'content_html' => $source->content_html,
                'seo_title' => $source->seo_title ?? '',
                'seo_description' => $source->seo_description ?? '',
                'status' => $postModel->status,
                'publish_at' => $postModel->publish_at?->format('Y-m-d\TH:i') ?? '',
                'category_ids' => $postModel->categories()->pluck('blog_categories.id')->map(static fn ($id): int => (int) $id)->all(),
                'featured_image_url' => $postModel->featured_media_id === null ? null : $media->find($postModel->featured_media_id)?->url,
            ],
        ]);
    }

    public function update(SaveBlogPostRequest $request, int $post, SaveBlogPost $saver, AuditLogger $audit): RedirectResponse
    {
        $postModel = BlogPost::find($post);
        if ($postModel === null) {
            return $this->notFound();
        }

        $locale = (string) $request->validated('locale');
        $newSlug = (string) $request->validated('slug');
        $wasPublished = $postModel->status === BlogStatus::Published->value;
        $oldSlug = $postModel->translations()->where('locale', $locale)->value('slug');

        $saver->handle($postModel, $this->data($request));
        $this->audit($request, $audit, 'blog.post.saved', $postModel);

        $response = back()->with('status', 'Wpis został zapisany.');

        if ($wasPublished && $oldSlug !== null && $oldSlug !== $newSlug) {
            $response->with('redirectSuggestion', ['locale' => $locale, 'from_path' => "/blog/{$oldSlug}", 'to_path' => "/blog/{$newSlug}"]);
        }

        return $response;
    }

    public function archive(Request $request, int $post, AuditLogger $audit): RedirectResponse
    {
        $postModel = BlogPost::find($post);
        if ($postModel === null) {
            return $this->notFound();
        }

        $postModel->forceFill(['status' => BlogStatus::Archived->value, 'archived_at' => now()])->save();
        $this->audit($request, $audit, 'blog.post.archived', $postModel);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new BlogContentChanged));

        return redirect()->route('panel.blog.posts.index')->with('status', 'Wpis został zarchiwizowany.');
    }

    public function uploadFeaturedImage(Request $request, int $post, MediaLibrary $media, AuditLogger $audit): RedirectResponse
    {
        $postModel = BlogPost::query()->with('translations')->find($post);
        if ($postModel === null) {
            return $this->notFound();
        }

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('media.max_size_kb')],
        ]);
        $file = $request->file('image');
        $uploaded = $media->store(
            contents: $file->get(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            altText: $postModel->translations->first()?->title,
            uploadedByStaffId: (string) $request->user()?->getAuthIdentifier(),
        );
        $postModel->update(['featured_media_id' => $uploaded->id]);
        $this->audit($request, $audit, 'blog.post.featured_image_uploaded', $postModel);

        return back()->with('status', 'Obraz wyróżniający został zapisany.');
    }

    public function removeFeaturedImage(Request $request, int $post, AuditLogger $audit): RedirectResponse
    {
        $postModel = BlogPost::find($post);
        if ($postModel === null) {
            return $this->notFound();
        }

        $postModel->update(['featured_media_id' => null]);
        $this->audit($request, $audit, 'blog.post.featured_image_removed', $postModel);

        return back()->with('status', 'Obraz wyróżniający został usunięty.');
    }

    /** @return array<string, mixed> */
    private function formOptions(LanguageRepository $languages, BlogCatalog $catalog, string $locale): array
    {
        return [
            'defaultLocale' => $this->defaultLocale($languages),
            'languages' => $this->languageOptions($languages),
            'categories' => array_map(static fn ($category): array => ['id' => $category->id, 'name' => $category->name], $catalog->listCategories($locale)),
            'statuses' => [
                ['label' => 'Szkic', 'value' => BlogStatus::Draft->value],
                ['label' => 'Opublikowany', 'value' => BlogStatus::Published->value],
            ],
        ];
    }

    private function data(SaveBlogPostRequest $request): SaveBlogPostData
    {
        return new SaveBlogPostData(
            locale: (string) $request->validated('locale'),
            slug: (string) $request->validated('slug'),
            title: (string) $request->validated('title'),
            excerpt: $this->nullable($request->validated('excerpt')),
            contentHtml: (string) $request->validated('content_html'),
            seoTitle: $this->nullable($request->validated('seo_title')),
            seoDescription: $this->nullable($request->validated('seo_description')),
            status: (string) $request->validated('status'),
            publishAt: $this->nullable($request->validated('publish_at')),
            categoryIds: array_map('intval', (array) $request->validated('category_ids')),
        );
    }

    private function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function notFound(): RedirectResponse
    {
        return redirect()->route('panel.blog.posts.index')->with('error', 'Wpis nie został znaleziony.');
    }

    private function audit(Request $request, AuditLogger $audit, string $action, BlogPost $post): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'blog_post',
            subjectId: (string) $post->getKey(),
            changes: [],
        ));
    }
}
