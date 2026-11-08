import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Menu as MenuIcon, Plus } from 'lucide-react';
import { useState } from 'react';

export default function Index({ menus }) {
    const t = useTranslate();
    const [createOpen, setCreateOpen] = useState(false);
    const form = useForm({ name: '' });
    const error = usePage().props.flash?.error;

    const submitCreate = (event) => {
        event.preventDefault();
        form.post(route('panel.cms.menus.store'), {
            preserveScroll: true,
            onSuccess: () => setCreateOpen(false),
        });
    };

    const destroy = (menu) => {
        if (confirm(t('cms.menus.delete_confirm', 'Delete menu “:name”? This cannot be undone.', { name: menu.name }))) {
            router.delete(route('panel.cms.menus.destroy', menu.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={t('cms.menus.title')}
                    description={t('cms.menus.description')}
                    actions={<Button icon={<Plus size={16} />} onClick={() => setCreateOpen(true)}>{t('cms.menus.add')}</Button>}
                />
            )}
        >
            <Head title={t('cms.menus.title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {error && <Alert tone="danger">{error}</Alert>}
                {menus.length === 0 ? (
                    <EmptyState
                        icon={MenuIcon}
                        title={t('cms.menus.empty_title')}
                        description={t('cms.menus.empty_description')}
                        action={<Button icon={<Plus size={16} />} onClick={() => setCreateOpen(true)}>{t('cms.menus.add')}</Button>}
                    />
                ) : (
                    <Table
                        columns={[
                            { header: t('cms.menus.column_name'), render: (m) => <span style={{ fontWeight: 600 }}>{m.name}</span> },
                            {
                                header: t('cms.menus.column_location'),
                                render: (m) => (m.locationLabel ? <Badge tone="success">{m.locationLabel}</Badge> : <Badge tone="neutral">{t('cms.menus.unassigned')}</Badge>),
                            },
                            { header: t('cms.menus.column_items'), render: (m) => m.itemCount },
                            {
                                header: t('cms.menus.column_actions'),
                                render: (m) => (
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <Button variant="outline" size="sm" href={route('panel.cms.menus.edit', m.id)}>{t('cms.menus.edit')}</Button>
                                        <Button variant="ghost" size="sm" onClick={() => destroy(m)}>{t('cms.menus.delete')}</Button>
                                    </div>
                                ),
                            },
                        ]}
                        rows={menus}
                    />
                )}
            </div>

            <Modal show={createOpen} onClose={() => setCreateOpen(false)} title={t('cms.menus.create_title')} maxWidth={420}>
                <form onSubmit={submitCreate} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Input
                        label={t('cms.menus.name_field')}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={form.errors.name}
                        autoFocus
                    />
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                        <Button variant="outline" type="button" onClick={() => setCreateOpen(false)}>{t('cms.menus.cancel')}</Button>
                        <Button variant="solid" type="submit" loading={form.processing}>{t('cms.menus.create_submit')}</Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
