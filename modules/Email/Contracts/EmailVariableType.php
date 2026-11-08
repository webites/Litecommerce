<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

enum EmailVariableType: string
{
    case Text = 'text';
    case Url = 'url';
    case Number = 'number';
    case Money = 'money';
    case Date = 'date';
    case Boolean = 'boolean';
    case List = 'list';
}
