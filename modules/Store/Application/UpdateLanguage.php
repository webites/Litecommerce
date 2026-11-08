<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Litecommerce\Modules\Store\Contracts\Events\LanguagesChanged;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;

/**
 * Two invariants the panel form can't fully express as plain checkboxes:
 * a store always needs exactly one default language (requesting is_default=false
 * on the CURRENT default with no other promotion is a no-op, not an error — the
 * only way to change the default is to promote a different language), and at
 * least one enabled language (disabling the last one is rejected outright,
 * since ResolveStorefrontLocale would then 404 every storefront request).
 * The default language is always enabled — promoting a disabled one to
 * default enables it too, same transaction as SetDefaultLanguage.
 */
final class UpdateLanguage
{
    public function handle(Language $language, string $name, bool $isEnabled, bool $isDefault, bool $noindex): void
    {
        $finalIsDefault = $isDefault ? true : $language->is_default;
        $finalIsEnabled = $finalIsDefault ? true : $isEnabled;

        if (! $finalIsEnabled && ! Language::query()->where('id', '!=', $language->getKey())->where('is_enabled', true)->exists()) {
            throw ValidationException::withMessages(['is_enabled' => trans('store::panel.language_must_have_one_enabled')]);
        }

        DB::transaction(function () use ($language, $name, $finalIsEnabled, $finalIsDefault, $noindex): void {
            if ($finalIsDefault) {
                // Excludes $language itself: if it was ALREADY the default, this
                // must not touch its row — a bulk query updates the database
                // without refreshing this loaded model's in-memory attributes,
                // so the update() below (which only writes attributes Eloquent
                // considers dirty) would then see is_default unchanged (still
                // true in memory) and silently skip writing it back, leaving
                // the bulk unset as the last word in the database.
                Language::query()->where('id', '!=', $language->getKey())->where('is_default', true)->update(['is_default' => false]);
            }

            $language->update([
                'name' => $name,
                'is_enabled' => $finalIsEnabled,
                'is_default' => $finalIsDefault,
                'noindex' => $noindex,
            ]);
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new LanguagesChanged));
    }
}
