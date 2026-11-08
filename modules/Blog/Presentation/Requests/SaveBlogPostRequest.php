<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Blog\Contracts\BlogStatus;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPostTranslation;

final class SaveBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $locale = (string) $this->input('locale');
        $postId = is_numeric($this->route('post')) ? (int) $this->route('post') : null;
        $translationId = $postId === null ? null : BlogPostTranslation::query()
            ->where('post_id', $postId)
            ->where('locale', $locale)
            ->value('id');

        return [
            'locale' => ['required', 'string', 'max:16', 'exists:languages,code'],
            'slug' => [
                'required', 'string', 'max:191', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('blog_post_translations', 'slug')->where('locale', $locale)->ignore($translationId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'content_html' => ['required', 'string', 'max:1000000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(BlogStatus::class)],
            'publish_at' => ['nullable', 'date'],
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', Rule::exists('blog_categories', 'id')->whereNull('archived_at')],
        ];
    }
}
