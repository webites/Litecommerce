<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Litecommerce\Modules\Installer\Application\FinalizeInstallation;
use Litecommerce\Modules\Installer\Application\InstallWizardState;

final class FinalizeInstallationController extends Controller
{
    public function show(InstallWizardState $state): View|RedirectResponse
    {
        if ($redirect = $this->ensurePreviousStepsComplete($state)) {
            return $redirect;
        }

        return view('installer.finalize');
    }

    public function store(InstallWizardState $state, FinalizeInstallation $finalize): RedirectResponse
    {
        if ($redirect = $this->ensurePreviousStepsComplete($state)) {
            return $redirect;
        }

        $finalize->handle($state->administrator());
        $state->clear();

        return redirect()->route('login');
    }

    private function ensurePreviousStepsComplete(InstallWizardState $state): ?RedirectResponse
    {
        if ($state->databaseCredentials() === null) {
            return redirect()->route('install.database.show');
        }

        if ($state->administrator() === null) {
            return redirect()->route('install.administrator.show');
        }

        return null;
    }
}
