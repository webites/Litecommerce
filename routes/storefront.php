<?php

use Illuminate\Support\Facades\Route;
use Litecommerce\Modules\Cart\Presentation\Controllers\Storefront\CartController;
use Litecommerce\Modules\Cart\Presentation\Controllers\Storefront\CheckoutController;
use Litecommerce\Modules\Consents\Presentation\Controllers\MarketingConsentController;
use Litecommerce\Modules\Digital\Presentation\Controllers\Storefront\DownloadController;
use Litecommerce\Modules\Identity\Presentation\Controllers\CustomerAccountDeletionController;
use Litecommerce\Modules\Identity\Presentation\Controllers\CustomerPasswordResetController;
use Litecommerce\Modules\Identity\Presentation\Controllers\CustomerRegistrationController;
use Litecommerce\Modules\Identity\Presentation\Controllers\CustomerSessionController;
use Litecommerce\Modules\Payments\Presentation\Controllers\PaymentWebhookController;

/*
|--------------------------------------------------------------------------
| Storefront-facing JSON API
|--------------------------------------------------------------------------
|
| Cart/checkout/customer-auth, for guests and customer accounts alike —
| no storefront theme exists yet (P1-013), so this is the API a future
| theme calls into rather than server-rendered pages. Runs under the
| default 'web' group (from routes/web.php), so it has sessions, cookies
| and CSRF like the panel — deliberately no 'auth' gate: guests must be
| allowed, and account identity is resolved internally per request via
| the optional 'customer' guard.
|
*/

Route::prefix('customer')->name('customer.')->middleware('no-store')->group(function (): void {
    Route::post('/register', [CustomerRegistrationController::class, 'store'])->name('register');
    Route::post('/login', [CustomerSessionController::class, 'store'])->name('login');
    Route::post('/logout', [CustomerSessionController::class, 'destroy'])->name('logout');
    Route::post('/forgot-password', [CustomerPasswordResetController::class, 'request'])->name('password.email');
    Route::post('/reset-password', [CustomerPasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/account/deletion', [CustomerAccountDeletionController::class, 'request'])->middleware('auth:customer')->name('account.deletion.request');
    Route::post('/account/deletion/confirm', [CustomerAccountDeletionController::class, 'confirm'])->middleware('throttle:10,1')->name('account.deletion.confirm');
});

Route::put('/customer/account/marketing-consent', [MarketingConsentController::class, 'update'])->middleware(['auth:customer', 'no-store'])->name('customer.account.marketing-consent.update');

Route::post('/marketing/unsubscribe/{token}', [MarketingConsentController::class, 'unsubscribe'])
    ->middleware(['no-store', 'throttle:30,1'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->name('marketing.unsubscribe.store');

Route::prefix('cart')->name('cart.')->middleware('no-store')->group(function (): void {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('/items', [CartController::class, 'addItem'])->name('items.store');
    Route::put('/items/{variantId}', [CartController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{variantId}', [CartController::class, 'removeItem'])->name('items.destroy');
    Route::put('/currency', [CartController::class, 'setCurrency'])->name('currency.update');
    Route::post('/coupons', [CartController::class, 'attachCoupon'])->name('coupons.store');
    Route::delete('/coupons/{code}', [CartController::class, 'detachCoupon'])->name('coupons.destroy');
});

Route::prefix('checkout')->name('checkout.')->middleware('no-store')->group(function (): void {
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    // Registered before the '{draft}' wildcard below — otherwise it would
    // swallow this literal segment instead of the country-list endpoint.
    Route::get('/countries', [CheckoutController::class, 'countries'])->name('countries');
    Route::get('/{draft}', [CheckoutController::class, 'show'])->name('show');
    Route::put('/{draft}', [CheckoutController::class, 'update'])->name('update');
    Route::get('/{draft}/payment-methods', [CheckoutController::class, 'paymentMethods'])->name('payment-methods');
    Route::post('/{draft}/confirm', [CheckoutController::class, 'confirm'])->name('confirm');
});

Route::post('/payments/webhooks/{methodId}', PaymentWebhookController::class)
    ->middleware('no-store')
    ->where('methodId', '[A-Za-z0-9._-]+')
    ->name('payments.webhook');

Route::get('/downloads/{token}', [DownloadController::class, 'show'])
    ->middleware('no-store')
    ->where('token', '[A-Za-z0-9]+')
    ->name('downloads.show');
