<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Contracts;

final readonly class BackupVerification
{
    /**
     * @param  list<string>  $problems
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(public bool $valid, public array $problems, public array $manifest) {}
}
