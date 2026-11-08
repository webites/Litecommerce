<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureStaffHasPermission
{
    public function __construct(private StaffPermissionChecker $permissions) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $staffId = $request->user()?->getAuthIdentifier();

        abort_unless(is_int($staffId) && array_any($permissions, fn (string $permission): bool => $this->permissions->allows($staffId, $permission)), 403);

        return $next($request);
    }
}
