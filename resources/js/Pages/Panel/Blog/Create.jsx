import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import PostForm from './PostForm';

export default function Create({ defaultLocale, languages, categories, statuses }) {
    const t = useTranslate();
    const form = useForm({ locale: defaultLocale, slug: '', title: '', excerpt: '', content_html: '<p></p>', seo_title: '', seo_description: '', status: 'draft', publish_at: '', category_ids: [] });
    const submit = (event) => { event.preventDefault(); form.post(route('panel.blog.posts.store')); };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('blog.posts.create_title', 'New post')} />}>
            <Head title={t('blog.posts.create_title', 'New post')} />
            <div style={{ padding: 24 }}><PostForm form={form} languages={languages} categories={categories} statuses={statuses} onSubmit={submit} submitLabel={t('blog.posts.create_submit', 'Create post')} /></div>
        </AuthenticatedLayout>
    );
}
