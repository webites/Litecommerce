<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Presentation\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;

final class ListDemoDataCommand extends Command
{
    protected $signature = 'demo-data:list';

    protected $description = 'Wyświetla batche danych demonstracyjnych';

    public function handle(DemoDataManager $manager): int
    {
        $this->table(
            ['Batch', 'Status', 'Język', 'Waluta', 'Zasoby', 'Utworzono', 'Usunięto'],
            array_map(static fn ($batch): array => [
                $batch->id,
                $batch->status,
                $batch->locale,
                $batch->currencyCode,
                $batch->resourceCount,
                $batch->createdAt,
                $batch->removedAt ?? '—',
            ], $manager->all()),
        );

        return self::SUCCESS;
    }
}
