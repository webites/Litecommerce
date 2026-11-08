<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Store\Application\AddCurrency;

final class CurrencyController extends Controller
{
    public function store(Request $request, AddCurrency $addCurrency, AuditLogger $audit): RedirectResponse
    {
        /** @var array{code:string,name:string,symbol:string,decimal_places:int} $data */
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/', 'unique:shop_currencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:8'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
        ]);

        $code = strtoupper($data['code']);
        $currency = $addCurrency->handle($code, $data['name'], $data['symbol'], (int) $data['decimal_places']);

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'store.currency.added',
            subjectType: 'shop_currency',
            subjectId: (string) $currency->getKey(),
            changes: ['code' => $code],
        ));

        return back()->with('status', trans('store::panel.currency_added', ['code' => $code]));
    }
}
