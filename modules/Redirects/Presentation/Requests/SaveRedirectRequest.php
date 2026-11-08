<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class SaveRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $enabledCodes = array_map(
            static fn (LanguageData $language): string => $language->code,
            array_values(array_filter(app(LanguageRepository::class)->all(), static fn (LanguageData $language): bool => $language->isEnabled)),
        );

        $pathRules = [
            'required', 'string', 'max:255', 'starts_with:/', 'regex:/^\/[a-zA-Z0-9\-_\/.]*$/',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && str_contains($value, '..')) {
                    $fail('Adres nie może zawierać „..”.');
                }
            },
        ];

        return [
            'locale' => ['required', 'string', Rule::in($enabledCodes)],
            'from_path' => $pathRules,
            'to_path' => $pathRules,
        ];
    }
}
