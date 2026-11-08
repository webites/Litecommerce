import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Plus } from 'lucide-react';
import { useState } from 'react';

const empty = (locale) => ({ locale, from_path: '', to_path: '' });

export default function Index({ redirects, languages, embedded = false }) {
    const t = useTranslate();
    const defaultLocale = languages[0]?.code ?? '';
    const [editing, setEditing] = useState(undefined);
    const form = useForm(empty(defaultLocale));
    const { flash } = usePage().props;

    const openCreate = () => { setEditing(null); form.setData(empty(defaultLocale)); form.clearErrors(); };
    const openEdit = (redirect) => { setEditing(redirect); form.setData({ locale: redirect.locale, from_path: redirect.from_path, to_path: redirect.to_path }); form.clearErrors(); };
    const close = () => { setEditing(undefined); form.reset(); form.clearErrors(); };
    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (editing?.id) form.put(route('panel.redirects.update', editing.id), options);
        else form.post(route('panel.redirects.store'), options);
    };
    const destroy = (redirect) => {
        if (confirm(t('store.redirects.delete_confirm', 'Delete the redirect :from → :to?', { from: redirect.from_path, to: redirect.to_path }))) {
            router.delete(route('panel.redirects.destroy', redirect.id), { preserveScroll: true });
        }
    };

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('store.redirects.title', 'Redirects')} description={t('store.redirects.description', 'Single 301 redirects from an old address to a new one.')} actions={<Button icon={<Plus size={15} />} onClick={openCreate}>{t('store.redirects.add', 'Add redirect')}</Button>} />}>
            <Head title={t('store.redirects.title', 'Redirects')} />
            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {flash.status && <Alert tone="success">{flash.status}</Alert>}
                {redirects.length === 0 ? (
                    <EmptyState icon={ArrowRightLeft} title={t('store.redirects.empty_title', 'No redirects')} description={t('store.redirects.empty_description', 'Add a redirect when a page, post, or category address has changed.')} action={<Button onClick={openCreate}>{t('store.redirects.add', 'Add redirect')}</Button>} />
                ) : (
                    <Table columns={[
                        { header: t('store.redirects.column_locale', 'Language'), render: (redirect) => redirect.locale },
                        { header: t('store.redirects.column_from', 'From'), render: (redirect) => <span style={{ fontFamily: 'var(--font-mono)' }}>{redirect.from_path}</span> },
                        { header: t('store.redirects.column_to', 'To'), render: (redirect) => <span style={{ fontFamily: 'var(--font-mono)' }}>{redirect.to_path}</span> },
                        { header: t('store.redirects.column_actions', 'Actions'), render: (redirect) => <div style={{ display: 'flex', gap: 8 }}><Button variant="outline" size="sm" onClick={() => openEdit(redirect)}>{t('store.redirects.edit', 'Edit')}</Button><Button variant="ghost" size="sm" onClick={() => destroy(redirect)}>{t('store.redirects.delete', 'Delete')}</Button></div> },
                    ]} rows={redirects} />
                )}
            </div>

            <Modal show={editing !== undefined} onClose={close} title={editing?.id ? t('store.redirects.edit_title', 'Edit redirect') : t('store.redirects.create_title', 'New redirect')} maxWidth={520}>
                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Select label={t('store.redirects.column_locale', 'Language')} value={form.data.locale} options={languages.map((language) => ({ label: language.name, value: language.code }))} error={form.errors.locale} onChange={(event) => form.setData('locale', event.target.value)} />
                    <Input label={t('store.redirects.column_from', 'From')} prefix="/" value={form.data.from_path.replace(/^\//, '')} error={form.errors.from_path} onChange={(event) => form.setData('from_path', '/'.concat(event.target.value.replace(/^\//, '')))} />
                    <Input label={t('store.redirects.column_to', 'To')} prefix="/" value={form.data.to_path.replace(/^\//, '')} error={form.errors.to_path} onChange={(event) => form.setData('to_path', '/'.concat(event.target.value.replace(/^\//, '')))} />
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}><Button type="button" variant="outline" onClick={close}>{t('store.redirects.cancel', 'Cancel')}</Button><Button type="submit" loading={form.processing}>{t('store.redirects.save', 'Save')}</Button></div>
                </form>
            </Modal>
        </SettingsPageFrame>
    );
}
