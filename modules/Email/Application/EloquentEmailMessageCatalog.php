<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Litecommerce\Modules\Email\Contracts\EmailMessageCatalog;
use Litecommerce\Modules\Email\Contracts\EmailMessageSummary;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;

final class EloquentEmailMessageCatalog implements EmailMessageCatalog
{
    public function forSource(string $sourceType, string $sourceId, ?string $definitionKey = null): array
    {
        return EmailMessage::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->when($definitionKey !== null, fn ($query) => $query->where('definition_key', $definitionKey))
            ->latest()
            ->get()
            ->map(static fn (EmailMessage $message): EmailMessageSummary => new EmailMessageSummary(
                $message->id,
                $message->status,
                $message->actor_id,
                $message->created_at === null ? null : \DateTimeImmutable::createFromInterface($message->created_at),
                $message->definition_key === 'core.manual_customer_message' && is_string($message->variables['message'] ?? null)
                    ? $message->variables['message']
                    : null,
            ))
            ->all();
    }
}
