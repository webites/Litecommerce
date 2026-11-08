<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const Html = '<div style="background-color: #f6f7f9; color: #1f2937; font-family: Arial, sans-serif; padding: 32px 16px"><div style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 auto; max-width: 640px; padding: 32px">{{content}}</div><p style="color: #6b7280; font-size: 12px; margin: 16px 0 0; text-align: center">Powered by <strong>Litecommerce</strong></p></div>';

    public function up(): void
    {
        if (! Schema::hasTable('email_layouts') || ! Schema::hasTable('languages')) {
            return;
        }

        $now = now();
        foreach (DB::table('languages')->pluck('code') as $locale) {
            $exists = DB::table('email_layouts')
                ->where('key', 'core.default')
                ->where('locale', $locale)
                ->exists();
            if ($exists) {
                continue;
            }

            $hasDefault = DB::table('email_layouts')
                ->where('locale', $locale)
                ->where('is_default', true)
                ->exists();

            DB::table('email_layouts')->insert([
                'key' => 'core.default',
                'locale' => $locale,
                'name' => $locale === 'pl' ? 'Domyślny layout Litecommerce' : 'Litecommerce default layout',
                'html' => self::Html,
                'is_default' => ! $hasDefault,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_layouts') || ! Schema::hasTable('email_templates')) {
            return;
        }

        DB::table('email_layouts')
            ->where('key', 'core.default')
            ->where('html', self::Html)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('email_templates')
                    ->whereColumn('email_templates.layout_id', 'email_layouts.id');
            })
            ->delete();
    }
};
