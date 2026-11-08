<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Litecommerce\Modules\Installer\Application\EnvironmentRequirementsChecker;

final class EnvironmentRequirementsController extends Controller
{
    public function show(EnvironmentRequirementsChecker $checker): View
    {
        return view('installer.environment', [
            'result' => $checker->check(),
        ]);
    }
}
