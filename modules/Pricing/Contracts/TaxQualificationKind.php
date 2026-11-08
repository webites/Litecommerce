<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

enum TaxQualificationKind: string
{
    case Percentage = 'percentage';
    case Exempt = 'exempt';
    case NotApplicable = 'not_applicable';
}
