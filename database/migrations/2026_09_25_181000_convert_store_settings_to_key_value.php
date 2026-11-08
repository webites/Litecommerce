<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_settings') || Schema::hasColumn('store_settings', 'key')) {
            return;
        }

        $legacy = DB::table('store_settings')->where('id', 1)->first();
        Schema::create('store_settings_key_value', function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->longText('value');
            $table->timestamps();
        });

        $now = now();
        DB::table('store_settings_key_value')->insert([
            ['key' => 'store.name', 'value' => json_encode((string) ($legacy->store_name ?? 'Litecommerce'), JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'blog.enabled', 'value' => json_encode((bool) ($legacy->blog_enabled ?? true), JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now],
        ]);

        if (($legacy->logo_media_id ?? null) !== null) {
            DB::table('store_settings_key_value')->insert([
                'key' => 'store.logo_media_id',
                'value' => json_encode((int) $legacy->logo_media_id, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::drop('store_settings');
        Schema::rename('store_settings_key_value', 'store_settings');
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_settings') || ! Schema::hasColumn('store_settings', 'key')) {
            return;
        }

        $values = DB::table('store_settings')->pluck('value', 'key');
        Schema::rename('store_settings', 'store_settings_key_value');
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('store_name');
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('blog_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('store_settings')->insert([
            'id' => 1,
            'store_name' => json_decode((string) ($values['store.name'] ?? '"Litecommerce"'), true, flags: JSON_THROW_ON_ERROR),
            'logo_media_id' => isset($values['store.logo_media_id']) ? json_decode((string) $values['store.logo_media_id'], true, flags: JSON_THROW_ON_ERROR) : null,
            'blog_enabled' => json_decode((string) ($values['blog.enabled'] ?? 'true'), true, flags: JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::drop('store_settings_key_value');
    }
};
