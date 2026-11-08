<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('store_name');
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('blog_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('store_settings')->insert([
            'id' => 1,
            'store_name' => 'Litecommerce',
            'blog_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
