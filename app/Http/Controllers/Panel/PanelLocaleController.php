<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\UiLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PanelLocaleController extends Controller
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
