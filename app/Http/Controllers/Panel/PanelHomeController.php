<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class PanelHomeController extends Controller
{
    public function show(CurrencyRepository $currencies, LanguageRepository $languages): Response|RedirectResponse
    {
        if ($currencies->base() === null || $languages->default() === null) {
            return redirect()->route('panel.wizard.show');
        }

        return Inertia::render('Panel/Dashboard');
    }
}
