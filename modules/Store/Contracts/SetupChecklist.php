<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface SetupChecklist
{
    /**
     * @param  \Closure(): bool  $isDone  Evaluated lazily, so a module reads only its own data.
     * @param  \Closure(): bool  $visible  Hides the step from staff who cannot act on it.
     */
    public function register(string $key, int $position, string $titleKey, string $descriptionKey, string $url, \Closure $isDone, \Closure $visible): void;

    /** @return list<SetupStepData> */
    public function steps(): array;
}
