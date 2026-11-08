import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Palette, Upload } from 'lucide-react';

export default function Index({ themes }) {
    const t = useTranslate();
    const flash = usePage().props.flash;
    const uploadForm = useForm({ package: null, acknowledge_untrusted: false });

    const statusLabel = {
        installed: t('themes.status.installed', 'Installed'),
        active: t('themes.status.active', 'Active'),
    };
    const statusTone = { installed: 'neutral', active: 'success' };

    const submitUpload = (event) => {
        event.preventDefault();
        uploadForm.post(route('panel.themes.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => uploadForm.reset(),
        });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('themes.page_title', 'Themes')} description={t('themes.page_description', 'Installed from a local ZIP package. Exactly one theme can be active — it then serves the store at the public address.')} />}>
            <Head title={t('themes.page_title', 'Themes')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                {uploadForm.errors.package && <Alert tone="danger">{uploadForm.errors.package}</Alert>}
                {uploadForm.errors.acknowledge_untrusted && <Alert tone="danger">{uploadForm.errors.acknowledge_untrusted}</Alert>}

                <Card title={t('themes.upload_card_title', 'Upload package')} description={t('themes.upload_card_description', 'A ZIP file with manifest.json at its root. Once uploaded, the theme is installed but inactive — activate it from its details.')}>
                    <form onSubmit={submitUpload} style={{ display: 'flex', flexDirection: 'column', gap: 12, alignItems: 'flex-start' }}>
                        <input
                            type="file" accept=".zip"
                            onChange={(e) => uploadForm.setData('package', e.target.files[0] ?? null)}
                            style={{ fontSize: 14 }}
                        />
                        <Checkbox
                            checked={uploadForm.data.acknowledge_untrusted}
                            onChange={(checked) => uploadForm.setData('acknowledge_untrusted', checked)}
                            label={t('themes.untrusted_ack', 'I understand a theme can run scripts in customers\' browsers and its authenticity is not verified.')}
                        />
                        <Button type="submit" variant="solid" icon={<Upload size={14} />} loading={uploadForm.processing} disabled={!uploadForm.data.package || !uploadForm.data.acknowledge_untrusted}>
                            {t('themes.upload_submit', 'Upload ZIP')}
                        </Button>
                    </form>
                </Card>

                <Card title={t('themes.installed_card_title', 'Installed themes')}>
                    {themes.length === 0 ? (
                        <EmptyState icon={Palette} title={t('themes.empty_title', 'No themes')} description={t('themes.empty_description', 'Upload your first ZIP package above.')} />
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 14 }}>
                            {themes.map((theme) => (
                                <div key={theme.id} style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <Palette size={20} color="var(--text-tertiary)" />
                                        <Badge tone={statusTone[theme.status]}>{statusLabel[theme.status]}</Badge>
                                    </div>
                                    <div>
                                        <div style={{ fontWeight: 600, fontSize: 14 }}>{theme.name}</div>
                                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('themes.version_and_id', 'version :version · :id', { version: theme.version, id: theme.id })}</div>
                                    </div>
                                    <Link href={route('panel.themes.show', theme.id)}>
                                        <Button variant="outline" size="sm">{t('themes.details', 'Details')}</Button>
                                    </Link>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
