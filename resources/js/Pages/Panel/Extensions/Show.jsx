import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ extension, settings_schema: settingsSchema, settings_values: settingsValues }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;
    const errors = usePage().props.errors;
    const [busy, setBusy] = useState(false);

    const statusLabel = { disabled: t('extensions.status.disabled', 'Disabled'), enabled: t('extensions.status.enabled', 'Enabled'), winding_down: t('extensions.status.winding_down', 'Winding down') };
    const statusTone = { disabled: 'neutral', enabled: 'success', winding_down: 'warning' };

    const run = (routeName) => {
        setBusy(true);
        router[routeName === 'destroy' ? 'delete' : 'post'](
            routeName === 'destroy' ? route('panel.extensions.destroy', extension.id) : route(`panel.extensions.${routeName}`, extension.id),
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    const settingsForm = useForm({
        values: Object.fromEntries(settingsSchema.map((field) => [field.key, settingsValues[field.key] ?? field.default ?? ''])),
    });
    const saveSettings = (event) => {
        event.preventDefault();
        settingsForm.put(route('panel.extensions.settings.update', extension.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={extension.name} description={t('extensions.show.identifier', 'Identifier: :id', { id: extension.id })} />}>
            <Head title={extension.name} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 800 }}>
                {status && <Alert tone="success">{status}</Alert>}
                {errors?.extension && <Alert tone="danger">{errors.extension}</Alert>}

                <Card title={t('extensions.show.state_title', 'State')}>
                    <div style={{ display: 'flex', gap: 24, alignItems: 'center', flexWrap: 'wrap' }}>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('extensions.show.status_label', 'Status')}</div>
                            <Badge tone={extension.installed_at ? statusTone[extension.status] : 'neutral'}>
                                {extension.installed_at ? statusLabel[extension.status] : t('extensions.status.not_installed', 'Not installed')}
                            </Badge>
                        </div>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('extensions.show.version_label', 'Version')}</div>
                            <div>{extension.version}</div>
                        </div>
                        {extension.package_sha256 && (
                            <div style={{ gridColumn: '1 / -1' }}>
                                <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('extensions.show.sha256_label', 'Package SHA-256')}</div>
                                <div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, wordBreak: 'break-all' }}>{extension.package_sha256}</div>
                            </div>
                        )}
                        {extension.dependency_ids.length > 0 && (
                            <div>
                                <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('extensions.show.dependencies_label', 'Dependencies')}</div>
                                <div>{extension.dependency_ids.join(', ')}</div>
                            </div>
                        )}
                    </div>

                    <div style={{ display: 'flex', gap: 10, marginTop: 20, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        {!extension.installed_at && (
                            <Button variant="solid" loading={busy} onClick={() => run('install')}>{t('extensions.show.install', 'Install')}</Button>
                        )}
                        {extension.installed_at && extension.status !== 'enabled' && (
                            <Button variant="solid" loading={busy} onClick={() => run('enable')}>{t('extensions.show.enable', 'Enable')}</Button>
                        )}
                        {extension.status === 'enabled' && (
                            <Button variant="outline" loading={busy} onClick={() => run('disable')}>{t('extensions.show.disable', 'Disable')}</Button>
                        )}
                        {extension.status === 'disabled' && (
                            <Button
                                variant="destructive" loading={busy}
                                onClick={() => confirm(t('extensions.show.uninstall_confirm', 'Uninstall ":name"? The plugin author decides the fate of its data.', { name: extension.name })) && run('destroy')}
                            >
                                {t('extensions.show.uninstall', 'Uninstall')}
                            </Button>
                        )}
                    </div>
                </Card>

                {settingsSchema.length > 0 && (
                    <Card title={t('extensions.show.settings_title', 'Settings')}>
                        <form onSubmit={saveSettings} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {settingsSchema.map((field) => (
                                field.type === 'select' ? (
                                    <Select
                                        key={field.key} label={field.label}
                                        options={field.options ?? []}
                                        value={settingsForm.data.values[field.key] ?? ''}
                                        onChange={(e) => settingsForm.setData('values', { ...settingsForm.data.values, [field.key]: e.target.value })}
                                    />
                                ) : field.type === 'secret' ? (
                                    <Input
                                        key={field.key} label={field.label} type="password" autoComplete="new-password"
                                        placeholder={field.has_value ? t('extensions.show.secret_saved', 'Saved — leave empty to keep') : ''}
                                        value={settingsForm.data.values[field.key] ?? ''}
                                        onChange={(e) => settingsForm.setData('values', { ...settingsForm.data.values, [field.key]: e.target.value })}
                                    />
                                ) : (
                                    <Input
                                        key={field.key} label={field.label}
                                        value={settingsForm.data.values[field.key] ?? ''}
                                        onChange={(e) => settingsForm.setData('values', { ...settingsForm.data.values, [field.key]: e.target.value })}
                                    />
                                )
                            ))}
                            <div><Button type="submit" loading={settingsForm.processing}>{t('extensions.show.save', 'Save')}</Button></div>
                        </form>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
