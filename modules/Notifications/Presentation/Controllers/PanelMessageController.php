<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Notifications\Contracts\PanelInbox;

final class PanelMessageController extends Controller
{
    public function read(Request $request, PanelInbox $inbox): RedirectResponse
    {
        $key = $request->validate(['key' => ['required', 'string', 'max:150']])['key'];
        $inbox->markRead((int) $request->user()?->getAuthIdentifier(), $key);

        return back();
    }
}
