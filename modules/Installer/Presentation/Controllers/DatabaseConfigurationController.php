<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Litecommerce\Modules\Installer\Application\DatabaseConnectionInstaller;
use Litecommerce\Modules\Installer\Application\InstallWizardState;
use Litecommerce\Modules\Installer\Infrastructure\EnvFileWriter;
use Litecommerce\Modules\Installer\Presentation\Requests\DatabaseConfigurationRequest;

final class DatabaseConfigurationController extends Controller
{
    public function show(): View
    {
        return view('installer.database');
    }

    public function store(
        DatabaseConfigurationRequest $request,
        DatabaseConnectionInstaller $installer,
        EnvFileWriter $envFileWriter,
        InstallWizardState $state,
    ): RedirectResponse {
        $credentials = $request->validated();
        $credentials['password'] ??= '';

        $result = $installer->connect($credentials);

        if (! $result->successful) {
            return back()->withErrors(['connection' => $result->errorMessage])->withInput();
        }

        $envFileWriter->writeDatabaseCredentials($credentials);
        $envFileWriter->writeApplicationSettings($request->getSchemeAndHttpHost().$request->getBasePath());
        $state->rememberDatabaseCredentials($credentials);

        return redirect()->route('install.administrator.show');
    }
}
