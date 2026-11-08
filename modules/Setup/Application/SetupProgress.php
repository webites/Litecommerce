<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Setup\Application;

use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

/** Required steps are judged from the stored data; optional ones are remembered once done or skipped. */
final readonly class SetupProgress
{
    public const SettingKey = 'setup.completed_steps';

    public function __construct(
        private StoreSettingsStore $settings,
        private LanguageRepository $languages,
        private CurrencyRepository $currencies,
    ) {}

    public function current(): ?WizardStep
    {
        return array_find(WizardStep::cases(), fn (WizardStep $step): bool => ! $this->isDone($step));
    }

    public function isDone(WizardStep $step): bool
    {
        return match ($step) {
            WizardStep::Language => $this->languages->default() !== null,
            WizardStep::Currency => $this->currencies->base() !== null,
            default => in_array($step->value, $this->remembered(), true),
        };
    }

    public function markDone(WizardStep $step): void
    {
        if (! $step->isRequired()) {
            $this->settings->set(self::SettingKey, array_values(array_unique([...$this->remembered(), $step->value])));
        }
    }

    /** @return list<string> */
    private function remembered(): array
    {
        return array_values(array_filter((array) $this->settings->get(self::SettingKey, []), 'is_string'));
    }
}
