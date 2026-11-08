<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Presentation\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;

final class RemoveDemoDataCommand extends Command
{
    protected $signature = 'demo-data:remove {batch? : ID batcha; domyślnie aktywny} {--force : Usuń bez pytania}';

    protected $description = 'Usuwa wyłącznie zasoby zapisane w manifeście danych demonstracyjnych';

    public function handle(DemoDataManager $manager): int
    {
        if (! $this->option('force') && ! $this->confirm('Usunąć dane demonstracyjne zapisane w tym batchu?')) {
            return self::SUCCESS;
        }

        try {
            $batchId = $this->argument('batch');
            $batch = $manager->remove(is_string($batchId) && $batchId !== '' ? $batchId : null);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Usunięto dane demonstracyjne batcha {$batch->id}.");

        return self::SUCCESS;
    }
}
