<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;
use Litecommerce\Modules\Cms\Presentation\Concerns\ResolvesEditingLocale;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

/**
 * Validates the whole Puck document the page builder's "Publish" button
 * sends in one request — root.props is this project's own page-level
 * fields (title/slug/SEO/status, see config('cms.root_fields')), content
 * is Puck's own block list, stored verbatim.
 */
final class SavePageRequest extends FormRequest
{
    use ResolvesEditingLocale;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $locale = $this->resolveLocale();
        $page = $this->route('page');
        $translationId = $page instanceof CmsPage
            ? CmsPageTranslation::query()->where('page_id', $page->getKey())->where('locale', $locale)->value('id')
            : null;

        return [
            'root.props.title' => ['required', 'string', 'max:255'],
            'root.props.slug' => [
                'required', 'string', 'max:191', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('cms_page_translations', 'slug')->where('locale', $locale)->ignore($translationId),
            ],
            'root.props.seo_title' => ['nullable', 'string', 'max:255'],
            'root.props.seo_description' => ['nullable', 'string', 'max:1000'],
            'root.props.status' => ['required', Rule::enum(PageStatus::class)],
            'content' => ['present', 'array'],
        ];
    }

    public function resolveLocale(): string
    {
        return $this->resolveRequestedLocale((string) $this->query('locale', ''), app(LanguageRepository::class));
    }
}
