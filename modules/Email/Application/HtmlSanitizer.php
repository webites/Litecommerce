<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

final class HtmlSanitizer
{
    private const Tags = ['a', 'b', 'blockquote', 'br', 'div', 'em', 'h1', 'h2', 'h3', 'hr', 'img', 'li', 'ol', 'p', 'span', 'strong', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul'];

    private const Attributes = ['alt', 'class', 'height', 'href', 'src', 'style', 'target', 'title', 'width', 'align', 'cellpadding', 'cellspacing', 'role'];

    private const Css = ['background-color', 'border', 'border-collapse', 'border-radius', 'color', 'display', 'font-family', 'font-size', 'font-weight', 'line-height', 'margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top', 'max-width', 'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top', 'text-align', 'text-decoration', 'width'];

    public function sanitize(string $html): string
    {
        if (preg_match('/<\s*(script|iframe|form|object|embed|svg|math|style|link|meta)\b/i', $html) === 1) {
            throw new InvalidArgumentException('Email HTML contains a forbidden element.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?><div id="email-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new InvalidArgumentException('Email HTML is invalid.');
        }

        $root = $document->getElementById('email-root');
        if (! $root instanceof DOMElement) {
            throw new InvalidArgumentException('Email HTML root is invalid.');
        }
        $this->cleanChildren($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }
            if (! in_array(strtolower($node->tagName), self::Tags, true)) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (str_starts_with($name, 'on') || ! in_array($name, self::Attributes, true)) {
                    $node->removeAttribute($attribute->name);

                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && ! $this->safeUrl($attribute->value)) {
                    throw new InvalidArgumentException('Email HTML contains an unsafe URL.');
                }
                if ($name === 'style') {
                    $node->setAttribute('style', $this->sanitizeStyle($attribute->value));
                }
            }
            $this->cleanChildren($node);
        }
    }

    private function safeUrl(string $url): bool
    {
        if (preg_match('/^\{\{\s*[a-z][a-z0-9_.-]*\s*\}\}$/i', trim($url)) === 1) {
            return true;
        }

        $scheme = strtolower((string) parse_url(trim($url), PHP_URL_SCHEME));

        return $scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    private function sanitizeStyle(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (! in_array($property, self::Css, true) || preg_match('/url\s*\(|expression\s*\(|@import|javascript:/i', $value) === 1) {
                continue;
            }
            $safe[] = $property.': '.$value;
        }

        return implode('; ', $safe);
    }
}
