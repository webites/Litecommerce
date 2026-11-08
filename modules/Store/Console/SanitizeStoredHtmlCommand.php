<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Store\Application\SanitizeStoredHtml;

final class SanitizeStoredHtmlCommand extends Command
{
    protected $signature = 'litecommerce:sanitize-html';

    protected $description = 'Runs the HTML filter over already stored descriptions, pages and posts';

    public function handle(SanitizeStoredHtml $sanitize): int
    {
        foreach ($sanitize->handle() as $label => $changed) {
            $this->line("{$label}: {$changed}");
        }

        return self::SUCCESS;
    }
}
