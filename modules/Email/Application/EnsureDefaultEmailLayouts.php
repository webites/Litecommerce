<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use Illuminate\Support\Facades\Schema;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailLayout;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final readonly class EnsureDefaultEmailLayouts
{
    public const Key = 'core.default';

    public const Html = '<div style="background-color: #f6f7f9; color: #1f2937; font-family: Arial, sans-serif; padding: 32px 16px"><div style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; margin: 0 auto; max-width: 640px; padding: 32px">{{content}}</div><p style="color: #6b7280; font-size: 12px; margin: 16px 0 0; text-align: center">Powered by <strong>Litecommerce</strong></p></div>';

    public function __construct(private LanguageRepository $languages) {}

    public function handle(?string $locale = null): void
    {
        if (! Schema::hasTable('email_layouts') || ! Schema::hasTable('languages')) {
            return;
        }

        foreach ($this->languages->all() as $language) {
            if ($locale !== null && $language->code !== $locale) {
                continue;
            }

            $hasDefault = EmailLayout::query()
                ->where('locale', $language->code)
                ->where('is_default', true)
                ->exists();

            EmailLayout::query()->firstOrCreate(
                ['key' => self::Key, 'locale' => $language->code],
                [
                    'name' => $language->code === 'pl' ? 'Domyślny layout Litecommerce' : 'Litecommerce default layout',
                    'html' => self::Html,
                    'is_default' => ! $hasDefault,
                ],
            );
        }
    }
}
