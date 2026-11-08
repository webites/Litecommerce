<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, StaffTwoFactorManager $twoFactor): Response
    {
        $staffId = (int) $request->user()?->getAuthIdentifier();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => false,
            'status' => session('status'),
            'twoFactor' => [
                'enabled' => $twoFactor->isEnabled($staffId),
                'pending' => $twoFactor->isPending($staffId),
                'setup' => session('two_factor_setup'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }
}
