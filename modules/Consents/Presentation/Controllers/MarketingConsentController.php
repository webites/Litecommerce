<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Litecommerce\Modules\Consents\Contracts\ConsentSource;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;

final class MarketingConsentController extends Controller
{
    public function update(Request $request, MarketingConsentRegistry $consents, CustomerAccountLookup $accounts): JsonResponse
    {
        $data = $request->validate(['granted' => ['required', 'boolean'], 'locale' => ['nullable', 'string', 'max:10']]);
        $email = $accounts->emailFor((int) Auth::guard('customer')->id());
        abort_if($email === null, 401);

        if ($data['granted']) {
            $consents->grant($email, ConsentSource::Account, $data['locale'] ?? null);
        } else {
            $consents->withdraw($email, ConsentSource::Account);
        }

        return response()->json([
            'granted' => $consents->isGranted($email),
        ]);
    }

    public function unsubscribe(string $token, MarketingConsentRegistry $consents): JsonResponse
    {
        $consents->withdrawByToken($token);

        return response()->json(['message' => trans('consents::marketing.unsubscribed')]);
    }
}
