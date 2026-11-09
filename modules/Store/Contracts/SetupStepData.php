<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

final readonly class SetupStepData
{
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $url,
        public bool $done,
    ) {}

    /** @return array{key: string, title: string, description: string, url: string, done: bool} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'title' => $this->title, 'description' => $this->description, 'url' => $this->url, 'done' => $this->done];
    }
}
