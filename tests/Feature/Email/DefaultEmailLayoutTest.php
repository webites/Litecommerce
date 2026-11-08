<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Email\Application\EnsureDefaultEmailLayouts;
use Litecommerce\Modules\Store\Application\CreateLanguage;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Litecommerce\Modules\Store\Application\UpdateLanguage;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class DefaultEmailLayoutTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_selecting_the_store_language_creates_the_default_branded_layout(): void
    {
        app(SetDefaultLanguage::class)->handle('pl', 'Polski');

        $layout = DB::table('email_layouts')->where('key', EnsureDefaultEmailLayouts::Key)->where('locale', 'pl')->first();

        $this->assertNotNull($layout);
        $this->assertSame('Domyślny layout Litecommerce', $layout->name);
        $this->assertSame(1, $layout->is_default);
        $this->assertStringContainsString('{{content}}', $layout->html);
        $this->assertStringContainsString('Powered by <strong>Litecommerce</strong>', $layout->html);
    }

    public function test_language_changes_add_missing_locales_without_overwriting_an_edited_layout(): void
    {
        app(SetDefaultLanguage::class)->handle('pl', 'Polski');
        DB::table('email_layouts')->where('key', EnsureDefaultEmailLayouts::Key)->where('locale', 'pl')->update(['html' => '<div>{{content}} custom</div>']);

        app(CreateLanguage::class)->handle('en', 'English', true, false);

        $this->assertDatabaseHas('email_layouts', ['key' => EnsureDefaultEmailLayouts::Key, 'locale' => 'en', 'is_default' => true]);
        $this->assertSame('<div>{{content}} custom</div>', DB::table('email_layouts')->where('key', EnsureDefaultEmailLayouts::Key)->where('locale', 'pl')->value('html'));
        $this->assertDatabaseCount('email_layouts', 2);
    }

    public function test_data_migration_backfills_an_existing_store_language(): void
    {
        DB::table('languages')->insert([
            'code' => 'pl',
            'name' => 'Polski',
            'is_default' => true,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_26_110000_seed_default_email_layouts.php');
        $migration->up();

        $this->assertDatabaseHas('email_layouts', [
            'key' => EnsureDefaultEmailLayouts::Key,
            'locale' => 'pl',
            'is_default' => true,
        ]);
    }

    public function test_editing_a_language_does_not_recreate_a_layout_deleted_by_the_merchant(): void
    {
        app(SetDefaultLanguage::class)->handle('pl', 'Polski');
        DB::table('email_layouts')->where('key', EnsureDefaultEmailLayouts::Key)->where('locale', 'pl')->delete();
        $language = Language::query()->where('code', 'pl')->firstOrFail();

        app(UpdateLanguage::class)->handle($language, 'Polski', true, true, false);

        $this->assertDatabaseMissing('email_layouts', ['key' => EnsureDefaultEmailLayouts::Key, 'locale' => 'pl']);
    }
}
