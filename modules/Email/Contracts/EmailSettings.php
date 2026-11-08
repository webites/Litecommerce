<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailSettings
{
    public function current(): EmailSettingsData;

    /** @param array<string, mixed> $values */
    public function update(array $values): void;

    public function smtpPassword(): ?string;
}
