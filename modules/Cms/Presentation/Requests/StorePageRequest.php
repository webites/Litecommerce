<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Cms\Contracts\PageStatus;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'max:16', 'exists:languages,code'],
            'slug' => [
                'required', 'string', 'max:191', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('cms_page_translations', 'slug')->where('locale', $this->input('locale')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(PageStatus::class)],
        ];
    }
}
