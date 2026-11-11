<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final class OrderSettingKeys
{
    public const NumberPrefix = 'orders.number_prefix';

    public const NumberPrefixPattern = '/^[A-Za-z0-9._\/-]{0,10}$/';
}
