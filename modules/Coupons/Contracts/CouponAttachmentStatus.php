<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

/**
 * Outcome of attaching a code to a cart (Contracts\CouponAttachmentValidator)
 * or of re-validating an already-attached code during pricing
 * (Contracts\CouponPricer). Applied is the only status that contributes a
 * discount; every other value is shown to the shopper without revealing why
 * a code exists at all when it simply does not (InvalidCode covers both an
 * unknown code and a code belonging to a different, unrelated context).
 */
enum CouponAttachmentStatus: string
{
    case Applied = 'applied';
    case InvalidCode = 'invalid_code';
    case Blocked = 'blocked';
    case Expired = 'expired';
    case AccountRequired = 'account_required';
    case AccountMismatch = 'account_mismatch';
    case CurrencyUnavailable = 'currency_unavailable';
    case PinRequired = 'pin_required';
    case PinIncorrect = 'pin_incorrect';
    case PinLocked = 'pin_locked';
    case NotCombinable = 'not_combinable';
    case ModuleDisabled = 'module_disabled';
}
