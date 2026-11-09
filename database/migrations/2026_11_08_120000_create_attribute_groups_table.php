<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 96)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_group_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_group_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('name');
            $table->timestamps();
            $table->unique(['attribute_group_id', 'locale']);
        });

        Schema::table('attribute_definitions', function (Blueprint $table): void {
            $table->foreignId('attribute_group_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('attribute_group_id');
            $table->boolean('visible_on_product')->default(true)->after('is_filterable');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attribute_group_id');
            $table->dropColumn(['position', 'visible_on_product']);
        });

        Schema::dropIfExists('attribute_group_translations');
        Schema::dropIfExists('attribute_groups');
    }
};
