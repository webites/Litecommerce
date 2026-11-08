<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Litecommerce\Modules\Cms\Infrastructure\ThemeDefaultContentResolver;
use Tests\TestCase;

/** Shared by ThemeArchetypeResolver and ResetHomepageToThemeDefault — the ultimate fallback when no theme (active or default-storefront) ships the requested view at all. */
final class ThemeDefaultContentResolverTest extends TestCase
{
    public function test_falls_back_when_the_view_does_not_exist_anywhere(): void
    {
        $fallback = ['content' => [['type' => 'archive-heading', 'props' => []]]];

        $result = (new ThemeDefaultContentResolver)->resolve('theme::this-view-does-not-exist-anywhere', $fallback);

        $this->assertSame($fallback, $result);
    }
}
