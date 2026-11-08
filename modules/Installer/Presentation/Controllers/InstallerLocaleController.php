<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\UiLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InstallerLocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', UiLocale::supported())],
        ]);

        $request->session()->put(UiLocale::SESSION_KEY, $data['locale']);

        return back()->withCookie(UiLocale::cookie($data['locale']));
    }
}
