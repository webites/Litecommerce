<?php

declare(strict_types=1);

namespace Tests\Unit\Store;

use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Litecommerce\Modules\Store\Infrastructure\SymfonyHtmlContentFilter;
use PHPUnit\Framework\TestCase;

final class HtmlContentFilterTest extends TestCase
{
    private function filter(?bool $enabled = null): SymfonyHtmlContentFilter
    {
        $store = new class($enabled) implements StoreSettingsStore
        {
            public function __construct(private readonly ?bool $enabled) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return $key === StoreSettingKeys::FilterHtml && $this->enabled !== null ? $this->enabled : $default;
            }

            public function has(string $key): bool
            {
                return $this->enabled !== null;
            }

            public function all(?string $prefix = null): array
            {
                return [];
            }

            public function set(string $key, mixed $value): void {}

            public function setMany(array $values): void {}

            public function remove(string $key): bool
            {
                return true;
            }
        };

        return new SymfonyHtmlContentFilter($store);
    }

    public function test_it_keeps_rich_editor_markup_and_removes_active_content(): void
    {
        $html = '<h1 onclick="alert(1)">Tytuł</h1><script>alert(1)</script><table><tr><td colspan="2">x</td></tr></table>'
            .'<figure><img src="/storage/a.jpg" alt="A" onerror="alert(1)"><figcaption>Opis</figcaption></figure>'
            .'<iframe src="https://evil.example"></iframe><a href="javascript:alert(1)">zły</a><details><summary>S</summary>t</details>';

        $clean = $this->filter()->clean($html);

        foreach (['<h1>Tytuł</h1>', '<td colspan="2">x</td>', '<figcaption>Opis</figcaption>', '<summary>S</summary>', 'src="/storage/a.jpg"', 'alt="A"'] as $kept) {
            $this->assertStringContainsString($kept, $clean);
        }
        foreach (['script', 'onclick', 'onerror', 'iframe', 'javascript:', 'alert('] as $removed) {
            $this->assertStringNotContainsString($removed, $clean);
        }
    }

    public function test_links_get_safe_rel_and_unsafe_schemes_are_dropped(): void
    {
        $clean = $this->filter()->clean('<a href="https://example.com" target="_blank">ok</a><a href="data:text/html;base64,AAAA">d</a><a href="tel:48123">t</a><img src="data:image/png;base64,AAAA">');

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('href="tel:48123"', $clean);
        $this->assertStringNotContainsString('data:', $clean);
    }

    public function test_the_filter_is_on_by_default_and_the_option_turns_it_off(): void
    {
        $payload = '<p>ok</p><script>x()</script>';

        $this->assertTrue($this->filter()->enabled());
        $this->assertStringNotContainsString('script', (string) $this->filter()->clean($payload));
        $this->assertSame($payload, $this->filter(false)->clean($payload));
        $this->assertStringNotContainsString('script', $this->filter(false)->sanitize($payload));
    }

    public function test_it_cleans_html_keys_inside_a_nested_document_only(): void
    {
        $document = ['content' => [['type' => 'rich-text', 'props' => ['html' => '<p>a</p><script>x()</script>', 'title' => '<b>keep</b>']]], 'root' => ['props' => []]];

        $clean = $this->filter()->cleanDocument($document);

        $this->assertSame('<p>a</p>', $clean['content'][0]['props']['html']);
        $this->assertSame('<b>keep</b>', $clean['content'][0]['props']['title']);
        $this->assertSame($document, $this->filter(false)->cleanDocument($document));
    }

    public function test_null_and_blank_input_pass_through(): void
    {
        $this->assertNull($this->filter()->clean(null));
        $this->assertSame('', $this->filter()->clean(''));
        $this->assertSame('', $this->filter()->sanitize("  \n"));
    }
}
