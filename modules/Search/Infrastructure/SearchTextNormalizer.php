<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Infrastructure;

use Illuminate\Support\Str;

final class SearchTextNormalizer
{
    /** @return list<string> */
    public function tokens(string $text, string $locale): array
    {
        $normalized = Str::lower(Str::ascii(strip_tags($text)));
        $tokens = preg_split('/[^a-z0-9]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_map(fn (string $token): string => $this->stem($token, $locale), $tokens);

        return array_values(array_unique(array_filter($tokens, static fn (string $token): bool => mb_strlen($token) >= 2)));
    }

    public function phrase(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii(strip_tags($text)))) ?? '');
    }

    public function paddedTerms(string $text, string $locale): string
    {
        $tokens = $this->tokens($text, $locale);

        return $tokens === [] ? ' ' : ' '.implode(' ', $tokens).' ';
    }

    private function stem(string $token, string $locale): string
    {
        if ($locale !== 'pl' || strlen($token) < 6) {
            return $token;
        }

        foreach (['owego', 'owemu', 'owej', 'owych', 'owymi', 'ami', 'ach', 'ego', 'emu', 'owa', 'owe', 'owy', 'ie', 'ia', 'iu', 'om', 'em', 'a', 'y', 'i', 'u', 'e'] as $suffix) {
            if (str_ends_with($token, $suffix) && strlen($token) - strlen($suffix) >= 4) {
                return substr($token, 0, -strlen($suffix));
            }
        }

        return $token;
    }
}
