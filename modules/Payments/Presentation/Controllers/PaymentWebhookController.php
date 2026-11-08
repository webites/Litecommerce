<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Payments\Application\DeferPaymentWebhook;
use Litecommerce\Modules\Payments\Application\ProcessPaymentNotification;
use Litecommerce\Modules\Payments\Contracts\InvalidPaymentNotificationException;

final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $methodId, ProcessPaymentNotification $processor, MaintenanceMode $maintenance, DeferPaymentWebhook $deferral): Response
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = implode(', ', $values);
        }
        if ($maintenance->isActive()) {
            return $this->defer($methodId, $request->getContent(), $headers, $deferral);
        }

        try {
            $processor->execute($methodId, $request->getContent(), $headers);
        } catch (InvalidPaymentNotificationException) {
            return response('Invalid payment notification.', 401);
        }

        return response()->noContent();
    }

    /** @param array<string, string> $headers */
    private function defer(string $methodId, string $rawBody, array $headers, DeferPaymentWebhook $deferral): Response
    {
        return match ($deferral->execute($methodId, $rawBody, $headers)) {
            DeferPaymentWebhook::ACCEPTED => response()->noContent(202),
            DeferPaymentWebhook::TOO_LARGE => response('Payload too large.', 413),
            default => response('Temporarily unavailable.', 503, ['Retry-After' => (string) config('maintenance.retry_after_seconds', 300)]),
        };
    }
}
