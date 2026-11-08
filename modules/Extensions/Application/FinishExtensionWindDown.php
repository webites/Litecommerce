<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Application;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Extensions\Contracts\PluginWindDownHook;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\PluginProviderResolver;

final class FinishExtensionWindDown
{
    public function __construct(
        private readonly PluginProviderResolver $providers,
        private readonly Application $app,
    ) {}

    public function run(): int
    {
        $finished = 0;

        foreach (Extension::query()->where('status', 'winding_down')->get() as $extension) {
            try {
                $provider = $this->providers->resolve($extension);
                $outstanding = $provider instanceof PluginWindDownHook && $provider->hasOutstandingObligations($this->app);
            } catch (\Throwable $exception) {
                Log::warning("Wind-down check failed for extension [{$extension->id}].", ['exception' => $exception]);

                continue;
            }

            if (! $outstanding) {
                $extension->update(['status' => 'disabled']);
                $finished++;
            }
        }

        return $finished;
    }
}
