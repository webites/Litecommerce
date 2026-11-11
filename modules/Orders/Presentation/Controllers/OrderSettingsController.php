<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Orders\Application\OrderNumberAllocator;
use Litecommerce\Modules\Orders\Contracts\OrderSettingKeys;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

final class OrderSettingsController extends Controller
{
    public function __construct(
        private readonly OrderNumberAllocator $numbers,
        private readonly StoreSettingsStore $settings,
    ) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        return SettingsTabContent::component('orders', [
            'number_prefix' => $this->numbers->prefix(),
            'next_number' => $this->numbers->peekNextNumber(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'number_prefix' => ['nullable', 'string', 'regex:'.OrderSettingKeys::NumberPrefixPattern],
        ]);
        $prefix = (string) ($validated['number_prefix'] ?? '');
        $previous = $this->numbers->prefix();

        $this->settings->set(OrderSettingKeys::NumberPrefix, $prefix);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'orders.settings.updated',
            subjectType: 'store',
            subjectId: 'orders',
            changes: ['number_prefix' => ['from' => $previous, 'to' => $prefix]],
        ));

        return back()->with('status', trans('orders::panel.settings.saved'));
    }
}
