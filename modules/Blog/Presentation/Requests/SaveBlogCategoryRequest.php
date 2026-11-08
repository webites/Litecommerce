<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategoryTranslation;

final class SaveBlogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $locale = (string) $this->input('locale');
        $categoryId = is_numeric($this->route('category')) ? (int) $this->route('category') : null;
        $translationId = $categoryId === null ? null : BlogCategoryTranslation::query()
            ->where('category_id', $categoryId)
            ->where('locale', $locale)
            ->value('id');

        return [
            'locale' => ['required', 'string', 'max:16', 'exists:languages,code'],
            'slug' => [
                'required', 'string', 'max:191', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('blog_category_translations', 'slug')->where('locale', $locale)->ignore($translationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
