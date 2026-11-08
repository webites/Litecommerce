<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure;

use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class SymfonyHtmlContentFilter implements HtmlContentFilter
{
    private const MAX_INPUT_LENGTH = 2_000_000;

    private const GLOBAL_ATTRIBUTES = ['title', 'lang', 'dir'];

    private const ELEMENTS = [
        'a' => ['href', 'target', 'rel'],
        'abbr' => [], 'address' => [], 'article' => [], 'aside' => [], 'b' => [], 'bdi' => [], 'bdo' => [],
        'blockquote' => ['cite'], 'br' => [], 'caption' => [], 'cite' => [], 'code' => [],
        'col' => ['span'], 'colgroup' => ['span'], 'dd' => [], 'del' => ['cite', 'datetime'],
        'details' => ['open'], 'dfn' => [], 'div' => [], 'dl' => [], 'dt' => [], 'em' => [],
        'figcaption' => [], 'figure' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'hr' => [], 'i' => [], 'img' => ['src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading'],
        'ins' => ['cite', 'datetime'], 'kbd' => [], 'li' => ['value'], 'mark' => [],
        'ol' => ['start', 'type', 'reversed'], 'p' => [], 'picture' => [], 'pre' => [], 'q' => ['cite'],
        's' => [], 'samp' => [], 'section' => [], 'small' => [], 'source' => ['src', 'srcset', 'sizes', 'type', 'media'],
        'span' => [], 'strong' => [], 'sub' => [], 'summary' => [], 'sup' => [],
        'table' => [], 'tbody' => [], 'td' => ['colspan', 'rowspan', 'headers'], 'tfoot' => [],
        'th' => ['colspan', 'rowspan', 'headers', 'scope', 'abbr'], 'thead' => [], 'time' => ['datetime'], 'tr' => [],
        'u' => [], 'ul' => [], 'var' => [], 'wbr' => [],
        'audio' => ['src', 'controls', 'loop', 'preload'],
        'video' => ['src', 'controls', 'loop', 'muted', 'poster', 'preload', 'width', 'height', 'playsinline'],
    ];

    private const HTML_KEYS = ['html', 'content_html', 'description_html'];

    private ?HtmlSanitizer $sanitizer = null;

    public function __construct(private readonly StoreSettingsStore $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(StoreSettingKeys::FilterHtml, true);
    }

    public function clean(?string $html): ?string
    {
        if ($html === null || $html === '' || ! $this->enabled()) {
            return $html;
        }

        return $this->sanitize($html);
    }

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        return $this->sanitizer()->sanitize($html);
    }

    public function cleanDocument(array $document, bool $force = false): array
    {
        if (! $force && ! $this->enabled()) {
            return $document;
        }

        return $this->walk($document);
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private function walk(array $node): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->walk($value);
            } elseif (is_string($value) && is_string($key) && in_array($key, self::HTML_KEYS, true)) {
                $node[$key] = $this->sanitize($value);
            }
        }

        return $node;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->withMaxInputLength(self::MAX_INPUT_LENGTH)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        foreach (self::ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, [...self::GLOBAL_ATTRIBUTES, ...$attributes]);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }
}
