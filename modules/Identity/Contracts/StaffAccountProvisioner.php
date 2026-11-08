<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface StaffAccountProvisioner
{
    public function ensureAdministratorExists(AdministratorData $administrator): void;
}
