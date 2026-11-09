<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attribute_definitions')->where('type', 'select')->update(['type' => 'single_select']);
    }

    public function down(): void {}
};
