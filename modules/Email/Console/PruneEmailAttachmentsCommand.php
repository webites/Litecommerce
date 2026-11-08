<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessageAttachment;
use Throwable;

final class PruneEmailAttachmentsCommand extends Command
{
    protected $signature = 'email:prune-attachments';

    protected $description = 'Remove attachment files that are no longer referenced by an email message';

    public function handle(): int
    {
        $disk = (string) config('email.private_disk', 'local');
        $directory = trim((string) config('email.attachment_directory', 'email-attachments'), '/');
        $storage = Storage::disk($disk);
        $referenced = array_fill_keys(EmailMessageAttachment::query()->where('disk', $disk)->pluck('path')->all(), true);
        $cutoff = now()->subHours((int) config('email.orphan_grace_hours', 24))->getTimestamp();
        $removed = 0;

        foreach ($storage->allFiles($directory) as $path) {
            if (isset($referenced[$path])) {
                continue;
            }
            try {
                if ($storage->lastModified($path) > $cutoff) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }
            if ($storage->delete($path)) {
                $removed++;
            }
        }

        $this->components->info("Removed {$removed} orphaned email attachment file(s).");

        return self::SUCCESS;
    }
}
