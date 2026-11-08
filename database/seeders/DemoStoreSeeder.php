<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;

final class DemoStoreSeeder extends Seeder
{
    public function run(DemoDataManager $demoData): void
    {
        $demoData->install();
    }
}
