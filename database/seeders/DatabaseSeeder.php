<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        StaffAccount::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
