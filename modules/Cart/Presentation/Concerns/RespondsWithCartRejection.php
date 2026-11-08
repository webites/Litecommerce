<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Lang;
use Litecommerce\Modules\Cart\Application\CartValidationException;

trait RespondsWithCartRejection
{
    private function rejected(CartValidationException $exception): JsonResponse
    {
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
