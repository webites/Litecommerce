<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Litecommerce\Modules\Cms\Presentation\Concerns\ResolvesEditingLocale;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

/** Counterpart of SavePageRequest for a whole-archetype override — no slug/SEO/status: saving always publishes immediately (see UpdateArchetypeTemplate), and going back to the theme default is the dedicated "restore default" action (deletes the row), not a status field here. */
final class SaveArchetypeTemplateRequest extends FormRequest
{
    use ResolvesEditingLocale;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'root.props.title' => ['required', 'string', 'max:255'],
            'content' => ['present', 'array'],
        ];
    }

    public function resolveLocale(): string
    {
        return $this->resolveRequestedLocale((string) $this->query('locale', ''), app(LanguageRepository::class));
    }
}
