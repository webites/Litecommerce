import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import RedirectSuggestionModal from '@/Components/ui/RedirectSuggestionModal';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import Textarea from '@/Components/ui/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FolderTree, Plus } from 'lucide-react';
import { useState } from 'react';

const empty = (locale) => ({ locale, name: '', slug: '', description: '', seo_title: '', seo_description: '' });

export default function Categories({ categories, languages, defaultLocale }) {
    const t = useTranslate();
    const [editing, setEditing] = useState(undefined);
    const form = useForm(empty(defaultLocale));
    const error = usePage().props.flash?.error;

    const openCreate = () => { setEditing(null); form.setData(empty(defaultLocale)); form.clearErrors(); };
    const openEdit = (category) => { setEditing(category); form.setData({ locale: defaultLocale, name: category.name, slug: category.slug, description: category.description, seo_title: category.seo_title, seo_description: category.seo_description }); form.clearErrors(); };
    const close = () => { setEditing(undefined); form.reset(); form.clearErrors(); };
    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (editing?.id) form.put(route('panel.blog.categories.update', editing.id), options);
        else form.post(route('panel.blog.categories.store'), options);
    };
    const archive = (category) => { if (confirm(t('blog.categories.archive_confirm', 'Archive category ":name"?', { name: category.name }))) router.post(route('panel.blog.categories.archive', category.id), {}, { preserveScroll: true }); };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('blog.categories.title', 'Blog categories')} actions={<Button icon={<Plus size={15} />} onClick={openCreate}>{t('blog.categories.add', 'Add category')}</Button>} />}>
            <Head title={t('blog.categories.title', 'Blog categories')} />
            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {error && <Alert tone="danger">{error}</Alert>}
                {categories.length === 0 ? <EmptyState icon={FolderTree} title={t('blog.categories.empty_title', 'No blog categories')} description={t('blog.categories.empty_description', 'Categories organize posts and have their own archive based on the archetype template.')} action={<Button onClick={openCreate}>{t('blog.categories.add', 'Add category')}</Button>} /> : (
                    <Table columns={[
                        { header: t('blog.categories.column_category', 'Category'), render: (category) => <span style={{ fontWeight: 600 }}>{category.name}</span> },
                        { header: t('blog.categories.column_address', 'Address'), render: (category) => <span style={{ fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)' }}>/blog/category/{category.slug}</span> },
                        { header: t('blog.categories.column_actions', 'Actions'), render: (category) => <div style={{ display: 'flex', gap: 8 }}><Button variant="outline" size="sm" onClick={() => openEdit(category)}>{t('blog.categories.edit', 'Edit')}</Button><Button variant="ghost" size="sm" onClick={() => archive(category)}>{t('blog.categories.archive', 'Archive')}</Button></div> },
                    ]} rows={categories} />
                )}
            </div>

            <Modal show={editing !== undefined} onClose={close} title={editing?.id ? t('blog.categories.modal_edit_title', 'Edit category') : t('blog.categories.modal_create_title', 'New category')} maxWidth={560}>
                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Select label={t('blog.categories.field_language', 'Language')} value={form.data.locale} options={languages.map((language) => ({ label: language.name, value: language.code }))} error={form.errors.locale} onChange={(event) => form.setData('locale', event.target.value)} />
                    <Input label={t('blog.categories.field_name', 'Name')} value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} />
                    <Input label={t('blog.categories.field_slug', 'Slug')} prefix="/blog/category/" value={form.data.slug} error={form.errors.slug} onChange={(event) => form.setData('slug', event.target.value)} />
                    <Textarea label={t('blog.categories.field_description', 'Description')} rows={3} value={form.data.description} error={form.errors.description} onChange={(value) => form.setData('description', value)} />
                    <Input label={t('blog.categories.field_seo_title', 'SEO title')} value={form.data.seo_title} error={form.errors.seo_title} onChange={(event) => form.setData('seo_title', event.target.value)} />
                    <Textarea label={t('blog.categories.field_seo_description', 'SEO description')} rows={3} value={form.data.seo_description} error={form.errors.seo_description} onChange={(value) => form.setData('seo_description', value)} />
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}><Button type="button" variant="outline" onClick={close}>{t('blog.categories.cancel', 'Cancel')}</Button><Button type="submit" loading={form.processing}>{t('blog.categories.save', 'Save')}</Button></div>
                </form>
            </Modal>
            <RedirectSuggestionModal />
        </AuthenticatedLayout>
    );
}
