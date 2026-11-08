<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Store\Contracts\Events\LanguageProvisioned;
use Litecommerce\Modules\Store\Contracts\Events\LanguagesChanged;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;

/**
 * Sets the store's default language. Exactly one language has
 * is_default=true — enforced in a transaction, not a DB constraint
 * (same reasoning as SetBaseCurrency).
 */
final class SetDefaultLanguage
{
    public function handle(string $code, string $name): void
    {
        DB::transaction(function () use ($code, $name) {
            Language::query()->where('is_default', true)->update(['is_default' => false]);

            Language::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_default' => true, 'is_enabled' => true],
            );
        });

        DB::afterCommit(function () use ($code): void {
            CommittedEvent::dispatch(new LanguageProvisioned($code));
            CommittedEvent::dispatch(new LanguagesChanged);
        });
    }
}
