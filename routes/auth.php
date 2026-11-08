<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;
use Litecommerce\Modules\Identity\Presentation\Controllers\StaffTwoFactorChallengeController;

// No self-registration: only the installer (modules/Installer) creates the
// first admin. Email verification omitted: StaffAccount doesn't
// implement MustVerifyEmail — no decision yet on whether/how staff
// accounts need it (P1-002).

Route::middleware(['guest', 'no-store'])->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('two-factor-challenge', [StaffTwoFactorChallengeController::class, 'create'])
    ->middleware(['guest', 'no-store'])
    ->name('two-factor.challenge');
Route::post('two-factor-challenge', [StaffTwoFactorChallengeController::class, 'store'])
    ->middleware(['guest', 'no-store']);

Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
