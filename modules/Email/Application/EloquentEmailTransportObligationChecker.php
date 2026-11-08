<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Litecommerce\Modules\Email\Contracts\EmailTransportObligationChecker;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;

final class EloquentEmailTransportObligationChecker implements EmailTransportObligationChecker
{
    public function hasOutstandingForOwner(string $owner): bool
    {
        return EmailMessage::query()
            ->where('transport_owner', $owner)
            ->whereIn('status', ['queued', 'processing', 'delivery_unknown'])
            ->exists();
    }
}
