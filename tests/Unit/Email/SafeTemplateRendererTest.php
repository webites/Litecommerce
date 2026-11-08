<?php

declare(strict_types=1);

namespace Tests\Unit\Email;

use InvalidArgumentException;
use Litecommerce\Modules\Email\Application\HtmlSanitizer;
use Litecommerce\Modules\Email\Application\SafeTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class SafeTemplateRendererTest extends TestCase
{
    public function test_it_escapes_values_and_renders_lists(): void
    {
        $renderer = new SafeTemplateRenderer(new HtmlSanitizer);
        $html = $renderer->html('<ul>{{#items}}<li>{{name}}</li>{{/items}}</ul>', '<main>{{content}}</main>', [
            'items' => [['name' => '<script>alert(1)</script>'], ['name' => 'Second']],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Second', $html);
    }

    public function test_it_rejects_raw_expressions_and_unknown_variables(): void
    {
        $renderer = new SafeTemplateRenderer(new HtmlSanitizer);
        $this->expectException(InvalidArgumentException::class);
        $renderer->subject('{{{secret}}}', ['secret' => 'value']);
    }
}
