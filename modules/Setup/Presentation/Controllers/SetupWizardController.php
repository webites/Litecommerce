<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Setup\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Email\Contracts\EmailTransportException;
use Litecommerce\Modules\Email\Contracts\TestEmailSender;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PricingSettingsCatalog;
use Litecommerce\Modules\Setup\Application\ConfigureStore;
use Litecommerce\Modules\Setup\Application\SetupProgress;
use Litecommerce\Modules\Setup\Application\WizardStep;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

final class SetupWizardController extends Controller
{
    public function __construct(private readonly SetupProgress $progress, private readonly ConfigureStore $configure) {}

    public function show(Request $request, StoreSettingsReader $store, PricingSettingsCatalog $pricing): Response
    {
        $step = $this->progress->current();
        $steps = array_map(static fn (WizardStep $candidate): string => $candidate->value, WizardStep::cases());

        if ($step === null) {
            return Inertia::render('Panel/Wizard/Done', ['steps' => $steps]);
        }

        return Inertia::render('Panel/Wizard/'.ucfirst($step->value), [
            'steps' => $steps,
            'step' => $step->value,
            'skippable' => ! $step->isRequired(),
            'languages' => $this->languageOptions(),
            'storeName' => $store->current()->storeName,
            'priceEntryMode' => $pricing->entryMode()->value,
            'adminEmail' => (string) $request->user()?->email,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $step = $this->progress->current();
        if ($step === null) {
            return redirect()->route('panel.wizard.show');
        }

        try {
            $this->apply($step, $request);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $this->completed($step, $request, $audit, 'saved');
    }

    public function skip(Request $request, AuditLogger $audit): RedirectResponse
    {
        $step = $this->progress->current();
        abort_if($step === null || $step->isRequired(), 422);

        return $this->completed($step, $request, $audit, 'skipped');
    }

    public function testEmail(Request $request, TestEmailSender $sender): RedirectResponse
    {
        abort_unless($this->progress->current() === WizardStep::Email, 422);

        $this->saveEmail($request);
        $recipient = (string) $request->user()?->email;

        try {
            $sender->send($recipient, app()->getLocale());
        } catch (EmailTransportException $exception) {
            $reason = trim((string) $exception->getPrevious()?->getMessage());

            return back()->with('error', trans('setup::panel.email.test_failed', ['reason' => $reason !== '' ? mb_substr($reason, 0, 300) : $exception->safeCode]));
        }

        return back()->with('status', trans('setup::panel.email.test_sent', ['email' => $recipient]));
    }

    private function apply(WizardStep $step, Request $request): void
    {
        match ($step) {
            WizardStep::Language => $this->saveLanguage($request),
            WizardStep::Currency => $this->saveCurrency($request),
            WizardStep::Profile => $this->configure->profile($request->validate(['store_name' => ['required', 'string', 'max:255']])['store_name']),
            WizardStep::Pricing => $this->savePricing($request),
            WizardStep::Email => $this->saveEmail($request),
            WizardStep::Demo => $this->configure->demoData(),
        };
    }

    private function saveLanguage(Request $request): void
    {
        $names = (array) config('app.ui_locale_names');
        $data = $request->validate(['code' => ['required', Rule::in(array_keys($names))]]);

        $this->configure->language($data['code'], (string) $names[$data['code']]);
    }

    private function saveCurrency(Request $request): void
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:8'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
        ]);

        $this->configure->currency(strtoupper($data['code']), $data['name'], $data['symbol'], (int) $data['decimal_places']);
    }

    private function savePricing(Request $request): void
    {
        $data = $request->validate([
            'price_entry_mode' => ['required', Rule::enum(PriceMode::class)],
            'tax_rate_name' => ['nullable', 'string', 'max:255', 'required_with:tax_rate_percentage'],
            'tax_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:tax_rate_name'],
        ]);

        $this->configure->pricing(
            PriceMode::from($data['price_entry_mode']),
            filled($data['tax_rate_name'] ?? null) ? trim($data['tax_rate_name']) : null,
            filled($data['tax_rate_percentage'] ?? null) ? (string) $data['tax_rate_percentage'] : null,
        );
    }

    private function saveEmail(Request $request): void
    {
        $this->configure->email($request->validate([
            'from_name' => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:1000'],
        ]));
    }

    private function completed(WizardStep $step, Request $request, AuditLogger $audit, string $outcome): RedirectResponse
    {
        $this->progress->markDone($step);
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'setup.step_'.$outcome,
            subjectType: 'setup_wizard',
            subjectId: $step->value,
        ));

        return redirect()->route('panel.wizard.show');
    }

    /** @return list<array{code: string, name: string}> */
    private function languageOptions(): array
    {
        $options = [];
        foreach ((array) config('app.ui_locale_names') as $code => $name) {
            $options[] = ['code' => (string) $code, 'name' => (string) $name];
        }

        return $options;
    }
}
