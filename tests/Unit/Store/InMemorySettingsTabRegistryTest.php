<?php

declare(strict_types=1);

namespace Tests\Unit\Store;

use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Infrastructure\InMemorySettingsTabRegistry;
use Tests\TestCase;

final class InMemorySettingsTabRegistryTest extends TestCase
{
    public function test_extension_can_register_a_tab_rendered_by_an_object_method(): void
    {
        $registry = new InMemorySettingsTabRegistry;
        $renderer = new class
        {
            public function render(SettingsTabContext $context): SettingsTabContent
            {
                return SettingsTabContent::html("<p>{$context->locale}</p>");
            }
        };

        $registry->register('extension', 'Extension', 20, [$renderer, 'render'], static fn (): bool => true);
        $registry->register('hidden', 'Hidden', 10, static fn (): SettingsTabContent => SettingsTabContent::html('hidden'), static fn (): bool => false);

        $this->assertSame([['id' => 'extension', 'label' => 'Extension']], $registry->visible());
        $this->assertSame('<p>en</p>', $registry->render('extension', new SettingsTabContext(1, 'en'))?->html);
        $this->assertNull($registry->render('hidden', new SettingsTabContext(1, 'en')));
    }
}
