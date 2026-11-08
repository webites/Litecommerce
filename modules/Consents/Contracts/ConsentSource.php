<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Contracts;

enum ConsentSource: string
{
    case Checkout = 'checkout';
    case Registration = 'registration';
    case Account = 'account';
    case UnsubscribeLink = 'unsubscribe_link';
    case AccountDeletion = 'account_deletion';
    case Extension = 'extension';
}
