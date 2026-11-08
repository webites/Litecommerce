<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Identity\Contracts\PermissionDefinition;
use Litecommerce\Modules\Identity\Contracts\PermissionRegistry;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;

final class StaffAccountController extends Controller
{
    public function index(PermissionRegistry $permissions): Response
    {
        return Inertia::render('Panel/Staff/Index', [
            'staffAccounts' => StaffAccount::query()
                ->orderBy('name')
                ->paginate(25)
                ->through(fn (StaffAccount $account): array => $this->accountData($account)),
            'roles' => $this->roles(),
            'permissions' => $this->permissions($permissions),
        ]);
    }

    public function store(Request $request, PermissionRegistry $permissions, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request, $permissions);
        $account = StaffAccount::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'permission_overrides' => $this->overrides($validated['permissions']),
        ]);

        $this->audit($request, $audit, 'identity.staff.created', $account, [
            'email' => $account->email,
            'role' => $account->getRawOriginal('role'),
            'permission_overrides' => $account->permission_overrides,
        ]);

        return redirect()->route('panel.staff.edit', $account)->with('status', trans('identity::flash.staff_created'));
    }

    public function edit(StaffAccount $staffAccount, PermissionRegistry $permissions): Response
    {
        return Inertia::render('Panel/Staff/Edit', [
            'staffAccount' => $this->accountData($staffAccount),
            'roles' => $this->roles(),
            'permissions' => $this->permissions($permissions),
        ]);
    }

    public function update(
        Request $request,
        StaffAccount $staffAccount,
        PermissionRegistry $permissions,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $this->validated($request, $permissions, $staffAccount);

        if ($staffAccount->is($request->user()) && ! $validated['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => trans('identity::flash.cannot_deactivate_self'),
            ]);
        }

        /** @var array<string, bool> $currentOverrides */
        $currentOverrides = $staffAccount->permission_overrides ?? [];
        $registeredKeys = array_keys($permissions->all());
        $unregisteredOverrides = array_diff_key($currentOverrides, array_flip($registeredKeys));
        $newOverrides = $unregisteredOverrides + $this->overrides($validated['permissions']);

        $before = [
            'name' => $staffAccount->name,
            'email' => $staffAccount->email,
            'role' => $staffAccount->getRawOriginal('role'),
            'permission_overrides' => $currentOverrides,
            'is_active' => $staffAccount->is_active,
        ];

        $staffAccount->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'permission_overrides' => $newOverrides,
            'is_active' => $validated['is_active'],
            'deactivated_at' => $validated['is_active'] ? null : now(),
        ]);

        if ($validated['password'] !== null) {
            $staffAccount->password = Hash::make($validated['password']);
        }

        $staffAccount->save();
        $this->audit($request, $audit, 'identity.staff.updated', $staffAccount, [
            'before' => $before,
            'after' => [
                'name' => $staffAccount->name,
                'email' => $staffAccount->email,
                'role' => $staffAccount->getRawOriginal('role'),
                'permission_overrides' => $newOverrides,
                'is_active' => $staffAccount->is_active,
                'password_changed' => $validated['password'] !== null,
            ],
        ]);

        return back()->with('status', trans('identity::flash.staff_saved'));
    }

    /** @return array{name:string,email:string,password:?string,role:string,permissions:array<string,string>,is_active:bool} */
    private function validated(Request $request, PermissionRegistry $registry, ?StaffAccount $account = null): array
    {
        $passwordRules = $account === null
            ? ['required', Password::min(12)->mixedCase()->numbers()->symbols()]
            : ['nullable', Password::min(12)->mixedCase()->numbers()->symbols()];

        /** @var array{name:string,email:string,password?:string,role:string,permissions:array<string,string>,is_active?:bool} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique(StaffAccount::class)->ignore($account?->id)],
            'password' => $passwordRules,
            'role' => ['required', Rule::enum(SystemStaffRole::class)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', Rule::in(['inherit', 'allow', 'deny'])],
            'is_active' => [$account === null ? 'sometimes' : 'required', 'boolean'],
        ]);

        foreach (array_keys($validated['permissions']) as $key) {
            if ($registry->find($key) === null) {
                throw ValidationException::withMessages(['permissions' => "Nieznane uprawnienie [{$key}]."]);
            }
        }

        $validated['password'] = $validated['password'] ?? null;
        $validated['is_active'] = $validated['is_active'] ?? true;

        return $validated;
    }

    /** @param array<string, string> $values @return array<string, bool> */
    private function overrides(array $values): array
    {
        $overrides = [];

        foreach ($values as $key => $value) {
            if ($value !== 'inherit') {
                $overrides[$key] = $value === 'allow';
            }
        }

        return $overrides;
    }

    /** @return list<array{value:string,label:string}> */
    private function roles(): array
    {
        return [
            ['value' => SystemStaffRole::Administrator->value, 'label' => trans('identity::labels.role_administrator')],
            ['value' => SystemStaffRole::StoreManager->value, 'label' => trans('identity::labels.role_store_manager')],
            ['value' => SystemStaffRole::WarehouseWorker->value, 'label' => trans('identity::labels.role_warehouse_worker')],
            ['value' => SystemStaffRole::ContentEditor->value, 'label' => trans('identity::labels.role_content_editor')],
        ];
    }

    /** @return list<array{key:string,label:string,group:string,granted_to_roles:list<string>}> */
    private function permissions(PermissionRegistry $registry): array
    {
        return array_values(array_map(
            static fn (PermissionDefinition $permission): array => [
                'key' => $permission->key,
                'label' => $permission->label,
                'group' => $permission->group,
                'granted_to_roles' => $permission->grantedToRoles,
            ],
            $registry->all(),
        ));
    }

    /** @return array{id:int,name:string,email:string,role:string,permission_overrides:array<string,bool>,is_active:bool} */
    private function accountData(StaffAccount $account): array
    {
        /** @var array<string, bool> $overrides */
        $overrides = $account->permission_overrides ?? [];

        return [
            'id' => (int) $account->getKey(),
            'name' => $account->name,
            'email' => $account->email,
            'role' => (string) $account->getRawOriginal('role'),
            'permission_overrides' => $overrides,
            'is_active' => (bool) $account->is_active,
        ];
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, AuditLogger $audit, string $action, StaffAccount $subject, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'staff_account',
            subjectId: (string) $subject->getKey(),
            changes: $changes,
        ));
    }
}
