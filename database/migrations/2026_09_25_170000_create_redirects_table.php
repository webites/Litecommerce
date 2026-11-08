<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 10);
            // Path without the {locale} prefix, always leading with "/" — e.g.
            // "/o-nas", "/blog/stary-wpis". Always a 301; see Redirects\Application\
            // SaveRedirect for the chain/loop rules these columns' indexes back.
            $table->string('from_path');
            $table->string('to_path');
            $table->timestamps();

            $table->unique(['locale', 'from_path']);
            $table->index(['locale', 'to_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
