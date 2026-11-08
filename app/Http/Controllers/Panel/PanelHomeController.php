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

final class PanelHomeController extends Controller
{
    public function show(Request $request, CurrencyRepository $currencies, LanguageRepository $languages, PanelInbox $inbox): Response|RedirectResponse
    {
        if ($currencies->base() === null || $languages->default() === null) {
            return redirect()->route('panel.wizard.show');
        }

        $staffId = (int) $request->user()?->getAuthIdentifier();
        $unread = array_flip(array_map(static fn (PanelMessage $message): string => $message->key, $inbox->unread($staffId)));

        return Inertia::render('Panel/Dashboard', [
            'messages' => array_map(
                static fn (PanelMessage $message): array => $message->toArray() + ['read' => ! isset($unread[$message->key])],
                $inbox->active($staffId),
            ),
        ]);
    }
}
