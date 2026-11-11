<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

final class OrderReopenNotConfirmedException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Reopening a cancelled order requires explicit confirmation.');
    }
}
