<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a language is added
 * or updated (is_enabled/is_default/noindex) — same DB::afterCommit
 * convention as Themes\Contracts\Events\ThemeActivated. Storefront's sitemap
 * root index (/sitemap.xml) depends on exactly this — which locales are
 * enabled and not noindexed — so this is the one sitemap-cache event that
 * also flushes the root index, not just the per-type/locale sitemaps.
 */
final readonly class LanguagesChanged {}
