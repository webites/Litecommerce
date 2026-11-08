<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

use DomainException;

final class DigitalGrantRefusedException extends DomainException
{
    public const NOT_LATE_PAYMENT = 'not_late_payment';

    public const TEST_ORDER = 'test_order';

    public const NO_DIGITAL_LINES = 'no_digital_lines';

    public const NO_PURCHASABLE_FILE = 'no_purchasable_file';

    public const REASON_REQUIRED = 'reason_required';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
