<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';
    case Color = 'color';
}
