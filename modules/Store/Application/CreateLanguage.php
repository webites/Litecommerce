<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Store\Contracts\Events\LanguageProvisioned;
use Litecommerce\Modules\Store\Contracts\Events\LanguagesChanged;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;

/**
 * A newly added language is never the default (the store already has exactly
 * one, from the install wizard or a later UpdateLanguage promotion) — promote
 * it separately via UpdateLanguage if needed. Adding a language needs no
 * translation-scaffolding step: existing untranslated content under it simply
 * 404s (storefront) or shows the existing "no translation yet" banner (CMS/blog
 * editors), exactly as it already does for any locale a merchant hasn't
 * finished translating into.
 */
final class CreateLanguage
{
    public function handle(string $code, string $name, bool $isEnabled, bool $noindex): Language
    {
        $language = Language::query()->create([
            'code' => $code,
            'name' => $name,
            'is_default' => false,
            'is_enabled' => $isEnabled,
            'noindex' => $noindex,
        ]);

        DB::afterCommit(function () use ($code): void {
            CommittedEvent::dispatch(new LanguageProvisioned($code));
            CommittedEvent::dispatch(new LanguagesChanged);
        });

        return $language;
    }
}
