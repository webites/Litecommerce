<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Application;

use InvalidArgumentException;

final class SafeTemplateRenderer
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /** @param array<string, mixed> $variables */
    public function subject(string $template, array $variables): string
    {
        $rendered = $this->render($template, $variables, false);
        $rendered = trim(preg_replace('/\s+/', ' ', strip_tags($rendered)) ?? '');
        if ($rendered === '') {
            throw new InvalidArgumentException('Rendered email subject cannot be empty.');
        }

        return mb_substr($rendered, 0, 255);
    }

    /** @param array<string, mixed> $variables */
    public function html(string $template, string $layout, array $variables): string
    {
        $body = $this->sanitizer->sanitize($this->render($template, $variables, true));
        $sentinel = '__LITECOMMERCE_EMAIL_CONTENT__';
        $layoutSource = str_replace('{{content}}', $sentinel, $layout);
        $renderedLayout = $this->render($layoutSource, $variables, true);
        $renderedLayout = str_replace($sentinel, $body, $renderedLayout);

        return $this->sanitizer->sanitize($renderedLayout);
    }

    /** @param array<string, mixed> $context */
    private function render(string $template, array $context, bool $escape): string
    {
        if (str_contains($template, '{{{') || str_contains($template, '{!!')) {
            throw new InvalidArgumentException('Raw email template expressions are forbidden.');
        }

        $pattern = '/\{\{#\s*([a-z][a-z0-9_.-]*)\s*\}\}([\s\S]*?)\{\{\/\s*\1\s*\}\}/i';
        while (preg_match($pattern, $template) === 1) {
            $template = (string) preg_replace_callback($pattern, function (array $match) use ($context, $escape): string {
                $value = $this->value($context, $match[1]);
                if (! is_array($value)) {
                    return $value ? $this->render($match[2], $context, $escape) : '';
                }
                if (! array_is_list($value)) {
                    return $this->render($match[2], [...$context, ...$value], $escape);
                }

                return implode('', array_map(function (mixed $item) use ($match, $context, $escape): string {
                    $local = is_array($item) ? [...$context, ...$item] : [...$context, 'value' => $item];

                    return $this->render($match[2], $local, $escape);
                }, $value));
            }, $template);
        }

        return (string) preg_replace_callback('/\{\{\s*([a-z][a-z0-9_.-]*)\s*\}\}/i', function (array $match) use ($context, $escape): string {
            $value = $this->value($context, $match[1]);
            if (is_array($value) || is_object($value)) {
                throw new InvalidArgumentException("Email variable [{$match[1]}] is not scalar in this context.");
            }
            $text = (string) ($value ?? '');

            return $escape ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $text;
        }, $template);
    }

    /** @param array<string, mixed> $context */
    private function value(array $context, string $path): mixed
    {
        $value = $context;
        foreach (explode('.', $path) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                throw new InvalidArgumentException("Unknown email variable [{$path}].");
            }
            $value = $value[$part];
        }

        return $value;
    }
}
