<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure;

use Litecommerce\Modules\Store\Contracts\SetupChecklist;
use Litecommerce\Modules\Store\Contracts\SetupStepData;

final class InMemorySetupChecklist implements SetupChecklist
{
    /** @var array<string, array{position: int, title: string, description: string, url: string, done: \Closure(): bool, visible: \Closure(): bool}> */
    private array $steps = [];

    public function register(string $key, int $position, string $titleKey, string $descriptionKey, string $url, \Closure $isDone, \Closure $visible): void
    {
        $this->steps[$key] = [
            'position' => $position, 'title' => $titleKey, 'description' => $descriptionKey,
            'url' => $url, 'done' => $isDone, 'visible' => $visible,
        ];
    }

    public function steps(): array
    {
        $steps = $this->steps;
        uasort($steps, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        $result = [];

        foreach ($steps as $key => $step) {
            if (($step['visible'])()) {
                $result[] = new SetupStepData($key, trans($step['title']), trans($step['description']), $step['url'], ($step['done'])());
            }
        }

        return $result;
    }
}
