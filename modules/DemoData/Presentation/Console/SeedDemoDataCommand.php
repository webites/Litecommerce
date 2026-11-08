<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Presentation\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;

final class SeedDemoDataCommand extends Command
{
    protected $signature = 'demo-data:seed {--locale= : Kod aktywnego języka sklepu}';

    protected $description = 'Dodaje odwracalny zestaw danych demonstracyjnych sklepu';

    public function handle(DemoDataManager $manager): int
    {
        try {
            $locale = $this->option('locale');
            $batch = $manager->install(is_string($locale) && $locale !== '' ? $locale : null);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Dodano dane demonstracyjne. Batch: {$batch->id}, zasoby: {$batch->resourceCount}.");

        return self::SUCCESS;
    }
}
