<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Notifications\Contracts\PanelInbox;
use Litecommerce\Modules\Notifications\Contracts\PanelMessage;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\SetupChecklist;
use Litecommerce\Modules\Store\Contracts\SetupStepData;

final class PanelHomeController extends Controller
{
    public function show(Request $request, CurrencyRepository $currencies, LanguageRepository $languages, PanelInbox $inbox, SetupChecklist $checklist): Response|RedirectResponse
    {
        if ($currencies->base() === null || $languages->default() === null) {
            return redirect()->route('panel.wizard.show');
        }

        $staffId = (int) $request->user()?->getAuthIdentifier();
        $unread = array_flip(array_map(static fn (PanelMessage $message): string => $message->key, $inbox->unread($staffId)));

        return Inertia::render('Panel/Dashboard', [
            'setup_steps' => array_map(static fn (SetupStepData $step): array => $step->toArray(), $checklist->steps()),
            'messages' => array_map(
                static fn (PanelMessage $message): array => $message->toArray() + ['read' => ! isset($unread[$message->key])],
                $inbox->active($staffId),
            ),
        ]);
    }
}
