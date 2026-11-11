<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Cart\Application\CartValidationException;

trait RespondsWithCartRejection
{
    private function rejected(CartValidationException $exception): JsonResponse
    {
        if (in_array($exception->errorCode, (array) config('cart.monitored_rejections', []), true)) {
            Log::warning('Checkout rejected with a monitored code.', [
                'code' => $exception->errorCode,
                'draft' => request()->route('draft'),
                'route' => request()->route()?->getName(),
                'locale' => app()->getLocale(),
            ]);
        }

        return response()->json(['code' => $exception->errorCode, 'message' => $this->rejectionMessage($exception)], 422);
    }

    private function rejectionMessage(CartValidationException $exception): string
    {
        $reason = $exception->params['reason'] ?? null;
        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        $key = "cart::errors.{$exception->errorCode}";

        return Lang::has($key) ? (string) trans($key, $exception->params) : $exception->getMessage();
    }
}
