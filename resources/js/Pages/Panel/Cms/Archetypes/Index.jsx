import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router } from '@inertiajs/react';

export default function Index({ archetypes }) {
    const t = useTranslate();
    const restoreDefault = (archetype) => {
        if (confirm(t('cms.archetypes.restore_confirm', 'Restore the theme default template for “:label”? The saved custom template will be permanently deleted.', { label: archetype.label }))) {
            router.post(route('panel.cms.archetypes.restore', archetype.key), {}, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('cms.archetypes.title')} />}>
            <Head title={t('cms.archetypes.title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                <p style={{ color: 'var(--text-secondary)', maxWidth: 640 }}>
                    {t('cms.archetypes.description')}
                </p>
                <Table
                    columns={[
                        { header: t('cms.archetypes.column_type'), render: (a) => <span style={{ fontWeight: 600 }}>{a.label}</span> },
                        {
                            header: t('cms.archetypes.column_template'),
                            render: (a) => (a.hasOverride ? (
                                <Badge tone="success">{t('cms.archetypes.custom_template')}</Badge>
                            ) : (
                                <Badge tone="neutral">{t('cms.archetypes.default_template')}</Badge>
                            )),
                        },
                        {
                            header: t('cms.archetypes.column_actions'),
                            render: (a) => (
                                <div style={{ display: 'flex', gap: 8 }}>
                                    <Button variant="outline" size="sm" href={route('panel.cms.archetypes.edit', a.key)}>
                                        {a.hasOverride ? t('cms.archetypes.edit') : t('cms.archetypes.customize')}
                                    </Button>
                                    {a.hasOverride && (
                                        <Button variant="ghost" size="sm" onClick={() => restoreDefault(a)}>
                                            {t('cms.archetypes.restore_default')}
                                        </Button>
                                    )}
                                </div>
                            ),
                        },
                    ]}
                    rows={archetypes}
                />
            </div>
        </AuthenticatedLayout>
    );
}
