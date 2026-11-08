<?php

declare(strict_types=1);

namespace Tests\Unit\I18n;

use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

final class TranslationParityTest extends TestCase
{
    public function test_every_polish_translation_file_has_the_same_keys_in_english(): void
    {
        $problems = [];

        foreach ($this->langRoots() as $root) {
            foreach (['pl' => 'en', 'en' => 'pl'] as $from => $to) {
                foreach (glob("{$root}/{$from}/*.php") ?: [] as $file) {
                    $counterpart = "{$root}/{$to}/".basename($file);

                    if (! is_file($counterpart)) {
                        $problems[] = "missing file {$counterpart}";

                        continue;
                    }

                    $missing = array_diff(array_keys(Arr::dot(require $file)), array_keys(Arr::dot(require $counterpart)));

                    foreach ($missing as $key) {
                        $problems[] = "{$counterpart} lacks {$key}";
                    }
                }
            }
        }

        $this->assertSame([], $problems);
    }

    /** @return list<string> */
    private function langRoots(): array
    {
        $base = dirname(__DIR__, 3);

        return array_merge([$base.'/lang'], glob($base.'/modules/*/lang') ?: []);
    }
}
