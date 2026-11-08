import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import PageHeader from '@/Components/ui/PageHeader';
import Textarea from '@/Components/ui/Textarea';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Database, RefreshCw, ShieldCheck, Trash2, Wrench } from 'lucide-react';

export default function Advanced({ demoData, cache, htmlFilter, maintenance, embedded = false }) {
    const { flash } = usePage().props;
    const demoForm = useForm({});
    const cacheForm = useForm({});
    const filterForm = useForm({ filter_html: htmlFilter.enabled });
    const sanitizeForm = useForm({});
    const maintenanceForm = useForm({ message: '' });
    const t = useTranslate();
    const remove = () => {
        if (window.confirm(t('store.settings.demo_remove_confirm'))) {
            demoForm.delete(route('panel.settings.demo-data.destroy'), { preserveScroll: true });
        }
    };
    const sanitizeStored = () => {
        if (window.confirm(t('store.settings.html_filter_sanitize_confirm'))) {
            sanitizeForm.post(route('panel.settings.html-filter.sanitize'), { preserveScroll: true });
        }
    };
    const enableMaintenance = () => {
        if (window.confirm(t('maintenance.enable_confirm'))) {
            maintenanceForm.post(route('panel.settings.maintenance.enable'), { preserveScroll: true, onSuccess: () => maintenanceForm.reset() });
        }
    };
    const clearCache = () => {
        if (window.confirm(t('store.settings.cache_clear_confirm'))) {
            cacheForm.post(route('panel.settings.cache.clear'), { preserveScroll: true });
        }
    };

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('store.settings.advanced_title')} description={t('store.settings.advanced_description')} />}>
            <Head title={t('store.settings.advanced_title')} />
            <div style={{ padding: 24, maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                <Card title={t('store.settings.html_filter_title')} description={t('store.settings.html_filter_description')}>
                    <form onSubmit={(event) => { event.preventDefault(); filterForm.put(route('panel.settings.html-filter.update'), { preserveScroll: true }); }} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <Checkbox label={t('store.settings.html_filter_enabled')} checked={filterForm.data.filter_html} onChange={(checked) => filterForm.setData('filter_html', checked)} />
                        <div style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.5 }}>{t('store.settings.html_filter_help')}</div>
                        <div style={{ display: 'flex', gap: 12, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
                            <Button type="submit" loading={filterForm.processing}>{t('store.settings.save')}</Button>
                            <Button variant="outline" icon={<ShieldCheck size={14} />} loading={sanitizeForm.processing} onClick={sanitizeStored}>{t('store.settings.html_filter_sanitize')}</Button>
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-secondary)' }}>{t('store.settings.html_filter_sanitize_help')}</div>
                    </form>
                </Card>
                <Card title={t('maintenance.title')} description={t('maintenance.description')}>
                    {maintenance.active ? (
                        <div style={{ display: 'flex', gap: 16, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
                            <div style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.5 }}>
                                <div>{maintenance.reason === 'update' ? t('maintenance.active_update') : t('maintenance.active_manual')}</div>
                                {maintenance.message && <div>{maintenance.message}</div>}
                            </div>
                            <Button variant="outline" loading={maintenanceForm.processing} onClick={() => maintenanceForm.delete(route('panel.settings.maintenance.disable'), { preserveScroll: true })}>
                                {t('maintenance.disable')}
                            </Button>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <Textarea label={t('maintenance.message_label')} helpText={t('maintenance.message_help')} value={maintenanceForm.data.message} error={maintenanceForm.errors.message} maxLength={500} onChange={(value) => maintenanceForm.setData('message', value)} />
                            <div>
                                <Button variant="destructive" icon={<Wrench size={14} />} loading={maintenanceForm.processing} onClick={enableMaintenance}>{t('maintenance.enable')}</Button>
                            </div>
                        </div>
                    )}
                </Card>
                <Card title={t('store.settings.cache_title')} description={t('store.settings.cache_description')}>
                    <div style={{ display: 'flex', gap: 16, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
                        <div style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.5 }}>
                            <div>{t('store.settings.cache_store', undefined, { store: cache.store })}</div>
                            <div style={{ color: cache.available ? 'var(--success-text)' : 'var(--danger-text)' }}>
                                {cache.available ? t('store.settings.cache_available') : t('store.settings.cache_unavailable')}
                            </div>
                        </div>
                        <Button variant="outline" icon={<RefreshCw size={14} />} loading={cacheForm.processing} onClick={clearCache}>
                            {t('store.settings.cache_clear')}
                        </Button>
                    </div>
                </Card>
                <Card title={demoData.active ? t('store.settings.demo_installed') : t('store.settings.demo_empty')}>
                    <div style={{ display: 'flex', gap: 16, alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap' }}>
                        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {demoData.active ? t('store.settings.demo_resources', undefined, { count: demoData.resource_count }) : t('store.settings.demo_empty_description')}
                        </div>
                        {demoData.active ? (
                            <Button variant="destructive" icon={<Trash2 size={14} />} loading={demoForm.processing} onClick={remove}>{t('store.settings.demo_remove')}</Button>
                        ) : (
                            <Button icon={<Database size={14} />} loading={demoForm.processing} onClick={() => demoForm.post(route('panel.settings.demo-data.store'), { preserveScroll: true })}>{t('store.settings.demo_install')}</Button>
                        )}
                    </div>
                </Card>
            </div>
        </SettingsPageFrame>
    );
}
