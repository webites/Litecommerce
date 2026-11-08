import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import ProductForm from './ProductForm';

export default function Create({ defaultLocale, languages, types, statuses, units, brands, categories }) {
    const t = useTranslate();
    const form = useForm({ locale: defaultLocale, name: '', short_description: '', description_html: '', type: 'physical', status: 'draft', featured: false, unit_code: 'piece', minimum_quantity: '1', maximum_quantity: '', quantity_step: '1', sku: '', gtin: '', mpn: '', brand_id: '', category_ids: [], primary_category_id: '' });
    const submit = (event) => { event.preventDefault(); form.post(route('panel.catalog.products.store')); };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('catalog.create.title')} />}>
            <Head title={t('catalog.create.title')} />
            <div style={{ padding: 24 }}>
                <ProductForm form={form} languages={languages} types={types} statuses={statuses} units={units} brands={brands} categories={categories} onSubmit={submit} submitLabel={t('catalog.create.title')} />
            </div>
        </AuthenticatedLayout>
    );
}
