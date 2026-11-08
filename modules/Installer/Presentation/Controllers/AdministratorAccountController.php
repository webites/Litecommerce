<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Litecommerce\Modules\Installer\Application\InstallWizardState;
use Litecommerce\Modules\Installer\Presentation\Requests\AdministratorAccountRequest;

final class AdministratorAccountController extends Controller
{
    public function show(InstallWizardState $state): View|RedirectResponse
    {
        if ($state->databaseCredentials() === null) {
            return redirect()->route('install.database.show');
        }

        return view('installer.administrator');
    }

    public function store(AdministratorAccountRequest $request, InstallWizardState $state): RedirectResponse
    {
        if ($state->databaseCredentials() === null) {
            return redirect()->route('install.database.show');
        }

        $state->rememberAdministrator([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'hashed_password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('install.finalize.show');
    }
}
