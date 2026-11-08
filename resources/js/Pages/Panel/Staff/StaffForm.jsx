import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import { useTranslate } from '@/lib/i18n';

export default function StaffForm({ form, roles, permissions, editing = false, onSubmit }) {
    const t = useTranslate();
    const roleDefaults = Object.fromEntries(
        permissions.map((permission) => [
            permission.key,
            permission.granted_to_roles.includes(form.data.role),
        ]),
    );

    return (
        <form onSubmit={onSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
            <Input
                id="name"
                label={t('identity.staff_form.name_label', 'Full name')}
                value={form.data.name}
                error={form.errors.name}
                onChange={(event) => form.setData('name', event.target.value)}
            />

            <Input
                id="email"
                label={t('identity.staff_form.email_label', 'Email')}
                type="email"
                value={form.data.email}
                error={form.errors.email}
                onChange={(event) => form.setData('email', event.target.value)}
            />

            <Input
                id="password"
                label={
                    editing
                        ? t('identity.staff_form.password_label_new', 'New password (leave blank to keep current)')
                        : t('identity.staff_form.password_label_temp', 'Temporary password')
                }
                type="password"
                value={form.data.password}
                autoComplete="new-password"
                helpText={t(
                    'identity.staff_form.password_help',
                    'At least 12 characters, one lowercase and one uppercase letter, a digit, and a symbol.',
                )}
                error={form.errors.password}
                onChange={(event) => form.setData('password', event.target.value)}
            />

            <Select
                id="role"
                label={t('identity.staff_form.role_label', 'System role')}
                value={form.data.role}
                options={roles}
                error={form.errors.role}
                onChange={(event) => form.setData('role', event.target.value)}
            />

            {editing && (
                <Checkbox
                    label={t('identity.staff_form.is_active_label', 'Account active')}
                    checked={form.data.is_active}
                    onChange={(checked) => form.setData('is_active', checked)}
                />
            )}

            <div>
                <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>
                    {t('identity.staff_form.permissions_title', 'Permission overrides')}
                </div>
                <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 4 }}>
                    {t(
                        'identity.staff_form.permissions_description',
                        '"Inherit" uses the role. An explicit allow or deny always overrides it, even for an administrator.',
                    )}
                </div>

                <div
                    style={{
                        marginTop: 14,
                        border: '1px solid var(--border-subtle)',
                        borderRadius: 'var(--radius-md)',
                        overflow: 'hidden',
                    }}
                >
                    {permissions.map((permission, i) => (
                        <div
                            key={permission.key}
                            className="grid grid-cols-1 sm:grid-cols-[1fr_13rem]"
                            style={{
                                gap: 12,
                                alignItems: 'center',
                                padding: 14,
                                borderTop: i > 0 ? '1px solid var(--border-subtle)' : 'none',
                            }}
                        >
                            <div>
                                <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{permission.label}</div>
                                <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                                    {permission.group} · {permission.key} · {t('identity.staff_form.role_prefix', 'role:')}{' '}
                                    {roleDefaults[permission.key]
                                        ? t('identity.staff_form.role_allows', 'allows')
                                        : t('identity.staff_form.role_denies', 'denies')}
                                </div>
                            </div>
                            <Select
                                value={form.data.permissions[permission.key] ?? 'inherit'}
                                options={[
                                    { label: t('identity.staff_form.permission_inherit', 'Inherit from role'), value: 'inherit' },
                                    { label: t('identity.staff_form.permission_allow', 'Allow'), value: 'allow' },
                                    { label: t('identity.staff_form.permission_deny', 'Deny'), value: 'deny' },
                                ]}
                                onChange={(event) =>
                                    form.setData('permissions', {
                                        ...form.data.permissions,
                                        [permission.key]: event.target.value,
                                    })
                                }
                            />
                        </div>
                    ))}
                </div>
                {form.errors.permissions && (
                    <span style={{ fontSize: 12, color: 'var(--danger-text)', display: 'block', marginTop: 8 }}>
                        {form.errors.permissions}
                    </span>
                )}
            </div>

            <div>
                <Button type="submit" variant="solid" disabled={form.processing}>
                    {editing
                        ? t('identity.staff_form.submit_edit', 'Save account')
                        : t('identity.staff_form.submit_create', 'Create account')}
                </Button>
            </div>
        </form>
    );
}
