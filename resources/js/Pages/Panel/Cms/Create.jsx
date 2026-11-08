import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import PageForm from './PageForm';

export default function Create({ defaultLocale, languages, statuses }) {
    const t = useTranslate();
    const form = useForm({ locale: defaultLocale, slug: '', title: '', seo_title: '', seo_description: '', status: 'draft' });
    const submit = (event) => { event.preventDefault(); form.post(route('panel.cms.pages.store')); };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('cms.pages.create_title', 'New page')} />}>
            <Head title={t('cms.pages.create_title', 'New page')} />
            <div style={{ padding: 24 }}>
                <PageForm form={form} languages={languages} statuses={statuses} onSubmit={submit} submitLabel={t('cms.pages.create_submit', 'Create page')} />
            </div>
        </AuthenticatedLayout>
    );
}
