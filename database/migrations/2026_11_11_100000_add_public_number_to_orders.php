<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIRST_NUMBER = 1001;

    public function up(): void
    {
        Schema::create('order_number_counters', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_number');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('number')->nullable()->after('id');
            $table->string('public_number', 40)->nullable()->after('number');
        });

        $next = self::FIRST_NUMBER;
        DB::table('orders')->orderBy('created_at')->orderBy('id')->select('id')->chunk(1000, function ($orders) use (&$next): void {
            DB::transaction(function () use ($orders, &$next): void {
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update(['number' => $next, 'public_number' => (string) $next]);
                    $next++;
                }
            });
        });

        DB::table('order_number_counters')->insert(['id' => 1, 'next_number' => $next]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('number');
            $table->index('public_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['number']);
            $table->dropIndex(['public_number']);
            $table->dropColumn(['number', 'public_number']);
        });

        Schema::dropIfExists('order_number_counters');
    }
};
