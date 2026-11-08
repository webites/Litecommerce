<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Infrastructure\Repositories;

use Litecommerce\Modules\Redirects\Contracts\RedirectResolver;
use Litecommerce\Modules\Redirects\Infrastructure\Models\Redirect;

final class EloquentRedirectResolver implements RedirectResolver
{
    public function resolve(string $locale, string $path): ?string
    {
        return Redirect::query()
            ->where('locale', $locale)
            ->where('from_path', $path)
            ->value('to_path');
    }
}
