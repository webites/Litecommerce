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
import { Plug, Upload } from 'lucide-react';

export default function Index({ extensions }) {
    const t = useTranslate();
    const flash = usePage().props.flash;
    const uploadForm = useForm({ package: null, acknowledge_untrusted: false });

    const statusLabel = { disabled: t('extensions.status.disabled', 'Disabled'), enabled: t('extensions.status.enabled', 'Enabled'), winding_down: t('extensions.status.winding_down', 'Winding down') };
    const statusTone = { disabled: 'neutral', enabled: 'success', winding_down: 'warning' };

    const submitUpload = (event) => {
        event.preventDefault();
        uploadForm.post(route('panel.extensions.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => uploadForm.reset(),
        });
    };

    const pageTitle = t('extensions.index.title', 'Extensions');

    return (
        <AuthenticatedLayout header={<PageHeader title={pageTitle} description={t('extensions.index.description', 'Installed from a local ZIP package — has core-level access, like trusted application code.')} />}>
            <Head title={pageTitle} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                {uploadForm.errors.package && <Alert tone="danger">{uploadForm.errors.package}</Alert>}
                {uploadForm.errors.acknowledge_untrusted && <Alert tone="danger">{uploadForm.errors.acknowledge_untrusted}</Alert>}

                <Card title={t('extensions.index.upload_card_title', 'Upload package')} description={t('extensions.index.upload_card_description', 'A ZIP file with manifest.json at the root. After upload the extension is disabled — install it from its details page.')}>
                    <form onSubmit={submitUpload} style={{ display: 'flex', flexDirection: 'column', gap: 12, alignItems: 'flex-start' }}>
                        <input
                            type="file" accept=".zip"
                            onChange={(e) => uploadForm.setData('package', e.target.files[0] ?? null)}
                            style={{ fontSize: 14 }}
                        />
                        <Checkbox
                            checked={uploadForm.data.acknowledge_untrusted}
                            onChange={(checked) => uploadForm.setData('acknowledge_untrusted', checked)}
                            label={t('extensions.index.untrusted_ack', 'I understand a plugin is PHP code with full access to the store and its authenticity is not verified.')}
                        />
                        <Button type="submit" variant="solid" icon={<Upload size={14} />} loading={uploadForm.processing} disabled={!uploadForm.data.package || !uploadForm.data.acknowledge_untrusted}>
                            {t('extensions.index.upload_button', 'Upload ZIP')}
                        </Button>
                    </form>
                </Card>

                <Card title={t('extensions.index.installed_card_title', 'Installed extensions')}>
                    {extensions.length === 0 ? (
                        <EmptyState icon={Plug} title={t('extensions.index.empty_title', 'No extensions')} description={t('extensions.index.empty_description', 'Upload your first ZIP package above.')} />
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 14 }}>
                            {extensions.map((extension) => (
                                <div key={extension.id} style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <Plug size={20} color="var(--text-tertiary)" />
                                        <Badge tone={extension.installed_at ? statusTone[extension.status] : 'neutral'}>
                                            {extension.installed_at ? statusLabel[extension.status] : t('extensions.status.not_installed', 'Not installed')}
                                        </Badge>
                                    </div>
                                    <div>
                                        <div style={{ fontWeight: 600, fontSize: 14 }}>{extension.name}</div>
                                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('extensions.index.version_prefix', 'version')} {extension.version} · {extension.id}</div>
                                    </div>
                                    <Link href={route('panel.extensions.show', extension.id)}>
                                        <Button variant="outline" size="sm">{t('extensions.index.details', 'Details')}</Button>
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
