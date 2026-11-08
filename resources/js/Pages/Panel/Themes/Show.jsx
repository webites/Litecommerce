import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Show({ theme, settings_schema: settingsSchema, settings_values: settingsValues }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;
    const promptHomepageReset = usePage().props.flash?.promptHomepageReset;
    const errors = usePage().props.errors;
    const [busy, setBusy] = useState(false);
    const [homepageModalOpen, setHomepageModalOpen] = useState(false);
    const [homepageResetBusy, setHomepageResetBusy] = useState(false);

    const statusLabel = {
        installed: t('themes.status.installed', 'Installed'),
        active: t('themes.status.active', 'Active'),
    };
    const statusTone = { installed: 'neutral', active: 'success' };

    useEffect(() => {
        if (promptHomepageReset) setHomepageModalOpen(true);
    }, [promptHomepageReset]);

    const run = (routeName) => {
        setBusy(true);
        router[routeName === 'destroy' ? 'delete' : 'post'](
            routeName === 'destroy' ? route('panel.themes.destroy', theme.id) : route(`panel.themes.${routeName}`, theme.id),
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    const resetHomepage = () => {
        setHomepageResetBusy(true);
        router.post(route('panel.cms.reset-to-theme-defaults'), {}, {
            preserveScroll: true,
            onFinish: () => {
                setHomepageResetBusy(false);
                setHomepageModalOpen(false);
            },
        });
    };

    const settingsForm = useForm({
        values: Object.fromEntries(settingsSchema.map((field) => [field.key, settingsValues[field.key] ?? field.default ?? ''])),
    });
    const saveSettings = (event) => {
        event.preventDefault();
        settingsForm.put(route('panel.themes.settings.update', theme.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={theme.name} description={t('themes.identifier_label', 'Identifier: :id', { id: theme.id })} />}>
            <Head title={theme.name} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 800 }}>
                {status && <Alert tone="success">{status}</Alert>}
                {errors?.theme && <Alert tone="danger">{errors.theme}</Alert>}

                <Card title={t('themes.state_card_title', 'State')}>
                    <div style={{ display: 'flex', gap: 24, alignItems: 'center', flexWrap: 'wrap' }}>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('themes.status_label', 'Status')}</div>
                            <Badge tone={statusTone[theme.status]}>{statusLabel[theme.status]}</Badge>
                        </div>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('themes.fields.version', 'Version')}</div>
                            <div>{theme.version}</div>
                        </div>
                        {theme.package_sha256 && (
                            <div style={{ gridColumn: '1 / -1' }}>
                                <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('themes.fields.package_sha256', 'Package SHA-256')}</div>
                                <div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, wordBreak: 'break-all' }}>{theme.package_sha256}</div>
                            </div>
                        )}
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('themes.fields.engine', 'Engine')}</div>
                            <div>{theme.engine}</div>
                        </div>
                        {theme.required_plugin_ids.length > 0 && (
                            <div>
                                <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('themes.fields.required_plugins', 'Required plugins')}</div>
                                <div>{theme.required_plugin_ids.join(', ')}</div>
                            </div>
                        )}
                    </div>

                    <div style={{ display: 'flex', gap: 10, marginTop: 20, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        {theme.status === 'installed' && (
                            <Button variant="solid" loading={busy} onClick={() => run('activate')}>{t('themes.activate', 'Activate')}</Button>
                        )}
                        {theme.status === 'active' && (
                            <Button variant="outline" loading={busy} onClick={() => run('deactivate')}>{t('themes.deactivate', 'Deactivate')}</Button>
                        )}
                        {theme.status === 'installed' && (
                            <Button
                                variant="destructive" loading={busy}
                                onClick={() => confirm(t('themes.confirm_delete', 'Delete theme ":name"? This cannot be undone.', { name: theme.name })) && run('destroy')}
                            >
                                {t('themes.delete', 'Delete')}
                            </Button>
                        )}
                    </div>
                </Card>

                {settingsSchema.length > 0 && (
                    <Card title={t('themes.settings_card_title', 'Settings')}>
                        <form onSubmit={saveSettings} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {settingsSchema.map((field) => (
                                <Input
                                    key={field.key} label={field.label}
                                    value={settingsForm.data.values[field.key] ?? ''}
                                    onChange={(e) => settingsForm.setData('values', { ...settingsForm.data.values, [field.key]: e.target.value })}
                                />
                            ))}
                            <div><Button type="submit" loading={settingsForm.processing}>{t('themes.save', 'Save')}</Button></div>
                        </form>
                    </Card>
                )}
            </div>

            <Modal show={homepageModalOpen} onClose={() => setHomepageModalOpen(false)} title={t('themes.homepage_modal_title', "Set this theme's default content?")} maxWidth={520}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <p style={{ margin: 0, fontSize: 14, color: 'var(--text-secondary)' }}>
                        {t('themes.homepage_modal_intro', 'The theme ":name" is now active. It may provide its own default views for the homepage and category/brand archives — you can load them now instead of what you currently have:', { name: theme.name })}
                    </p>
                    <div>
                        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)', marginBottom: 6 }}>{t('themes.homepage_modal_changes_title', 'What will change')}</div>
                        <ul style={{ margin: 0, paddingLeft: 20, fontSize: 13, color: 'var(--text-secondary)', display: 'flex', flexDirection: 'column', gap: 4 }}>
                            <li><strong>{t('themes.homepage_modal_change_homepage_label', 'Homepage')}</strong>{t('themes.homepage_modal_change_homepage_text', " — the current content will be replaced by this theme's default (still fully editable).")}</li>
                            <li><strong>{t('themes.homepage_modal_change_archives_label', 'Archive templates')}</strong>{t('themes.homepage_modal_change_archives_text', " (categories, brands) — the seller's own overrides will be removed; each category/brand will revert to this theme's default view.")}</li>
                        </ul>
                    </div>
                    <div>
                        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)', marginBottom: 6 }}>{t('themes.homepage_modal_unchanged_title', 'What will NOT change')}</div>
                        <ul style={{ margin: 0, paddingLeft: 20, fontSize: 13, color: 'var(--text-secondary)' }}>
                            <li>{t('themes.homepage_modal_unchanged_item', 'Other, standalone CMS pages (e.g. "About us") — unaffected.')}</li>
                        </ul>
                    </div>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                        <Button variant="outline" onClick={() => setHomepageModalOpen(false)}>{t('themes.homepage_modal_keep', 'No, keep current')}</Button>
                        <Button variant="solid" loading={homepageResetBusy} onClick={resetHomepage}>{t('themes.homepage_modal_apply', 'Yes, set defaults')}</Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
