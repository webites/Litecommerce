<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Infrastructure;

use Illuminate\Contracts\Translation\Translator;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;

final readonly class TranslatorDemoDataContent implements DemoDataContent
{
    public function __construct(private Translator $translator) {}

    public function load(string $translationGroup, string $locale): array
    {
        $content = $this->translator->get($translationGroup, [], $locale);

        if (! is_array($content)) {
            throw new \RuntimeException("Brakuje treści demonstracyjnych: {$translationGroup}.");
        }

        return $content;
    }
}
