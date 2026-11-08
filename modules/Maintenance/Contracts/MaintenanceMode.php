<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Contracts;

/** Store-wide maintenance flag kept outside the database so it works while the schema is being changed. */
interface MaintenanceMode
{
    public function state(): ?MaintenanceState;

    public function isActive(): bool;

    /** Returns false when maintenance was already active (the existing state is kept). */
    public function enable(MaintenanceReason $reason, ?string $message = null, ?string $actorId = null, ?int $retryAfterSeconds = null): bool;

    /** Returns false when maintenance was not active. */
    public function disable(): bool;
}
