<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureBlogEnabled
{
    public function __construct(private StoreSettingsReader $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->current()->blogEnabled, 404);

        return $next($request);
    }
}
