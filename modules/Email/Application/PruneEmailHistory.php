<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessageAttachment;
use Throwable;

final class PruneEmailHistory
{
    private const TerminalStatuses = ['sent', 'failed', 'delivery_unknown', 'cancelled'];

    public function pruneBatch(int $retentionDays, int $batchSize): int
    {
        if ($retentionDays < 1 || $batchSize < 1) {
            throw new InvalidArgumentException('Email history retention and batch size must be positive.');
        }

        [$deleted, $attachments] = DB::transaction(function () use ($retentionDays, $batchSize): array {
            $ids = EmailMessage::query()
                ->whereIn('status', self::TerminalStatuses)
                ->whereNotNull('terminal_at')
                ->where('terminal_at', '<', now()->subDays($retentionDays))
                ->orderBy('terminal_at')
                ->limit($batchSize)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if ($ids === []) {
                return [0, []];
            }

            $attachments = EmailMessageAttachment::query()
                ->whereIn('email_message_id', $ids)
                ->get(['disk', 'path'])
                ->map(static fn (EmailMessageAttachment $attachment): array => [
                    'disk' => $attachment->disk,
                    'path' => $attachment->path,
                ])
                ->all();

            $deleted = EmailMessage::query()->whereKey($ids)->delete();

            return [$deleted, $attachments];
        });

        foreach ($attachments as $attachment) {
            try {
                Storage::disk($attachment['disk'])->delete($attachment['path']);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $deleted;
    }
}
