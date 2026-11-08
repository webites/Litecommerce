<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Setup\Application;

enum WizardStep: string
{
    case Language = 'language';
    case Currency = 'currency';
    case Profile = 'profile';
    case Pricing = 'pricing';
    case Email = 'email';
    case Demo = 'demo';

    /** The shop cannot work without the required steps; the others can be skipped. */
    public function isRequired(): bool
    {
        return $this === self::Language || $this === self::Currency;
    }
}
