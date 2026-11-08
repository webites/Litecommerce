import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import CategoryTree from '@/Components/ui/CategoryTree';
import Checkbox from '@/Components/ui/Checkbox';
import ColorPicker from '@/Components/ui/ColorPicker';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import SingleImageUpload from '@/Components/ui/SingleImageUpload';
import WysiwygEditor from '@/Components/ui/WysiwygEditor';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function buildCategoryTree(categories) {
    const byParent = new Map();
    categories.forEach((category) => {
        const key = category.parent_id || null;
        if (!byParent.has(key)) byParent.set(key, []);
        byParent.get(key).push(category);
    });

    const toNode = (category) => ({
        id: category.id,
        label: category.name,
        hidden: !category.visible_in_tree,
        children: (byParent.get(category.id) || []).map(toNode),
    });

    return (byParent.get(null) || []).map(toNode);
}

export default function References({ locale, languages, brands, categories, attributes, attributeTypes }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;

    const [editingBrandId, setEditingBrandId] = useState(null);
    const [editingCategoryId, setEditingCategoryId] = useState(null);
    const [uploadingBrandLogo, setUploadingBrandLogo] = useState(false);
    const [uploadingCategoryImage, setUploadingCategoryImage] = useState(false);

    const brand = useForm({ locale, name: '', description_html: '' });
    const category = useForm({ locale, name: '', parent_id: '', position: 0, visible_in_tree: true });
    const attribute = useForm({ locale, key: '', name: '', type: 'text', is_filterable: false });
    const value = useForm({ locale, attribute_definition_id: attributes[0]?.id || '', value_key: '', name: '', color_hex: '#2563eb' });

    const editingBrand = brands.find((item) => item.id === editingBrandId) || null;
    const editingCategory = categories.find((item) => item.id === editingCategoryId) || null;

    const startEditBrand = (item) => {
        setEditingBrandId(item.id);
        brand.clearErrors();
        brand.setData({ locale, name: item.name, description_html: item.description_html || '' });
    };
    const cancelEditBrand = () => {
        setEditingBrandId(null);
        brand.clearErrors();
        brand.setData({ locale, name: '', description_html: '' });
    };
    const submitBrand = (event) => {
        event.preventDefault();
        if (editingBrandId) {
            brand.put(route('panel.catalog.products.references.brands.update', editingBrandId), { preserveScroll: true });
        } else {
            brand.post(route('panel.catalog.products.references.brands.store'), { preserveScroll: true, onSuccess: () => brand.reset('name', 'description_html') });
        }
    };
    const uploadBrandLogo = (file) => {
        setUploadingBrandLogo(true);
        router.post(route('panel.catalog.products.references.brands.logo.store', editingBrandId), { image: file }, {
            preserveScroll: true,
            onFinish: () => setUploadingBrandLogo(false),
        });
    };
    const removeBrandLogo = () => {
        router.delete(route('panel.catalog.products.references.brands.logo.destroy', editingBrandId), { preserveScroll: true });
    };

    const startEditCategory = (id) => {
        const item = categories.find((c) => c.id === id);
        if (!item) return;
        setEditingCategoryId(id);
        category.clearErrors();
        category.setData({ locale, name: item.name, parent_id: item.parent_id || '', position: item.position, visible_in_tree: item.visible_in_tree });
    };
    const cancelEditCategory = () => {
        setEditingCategoryId(null);
        category.clearErrors();
        category.setData({ locale, name: '', parent_id: '', position: 0, visible_in_tree: true });
    };
    const submitCategory = (event) => {
        event.preventDefault();
        if (editingCategoryId) {
            category.put(route('panel.catalog.products.references.categories.update', editingCategoryId), { preserveScroll: true });
        } else {
            category.post(route('panel.catalog.products.references.categories.store'), { preserveScroll: true, onSuccess: () => category.reset('name') });
        }
    };
    const uploadCategoryImage = (file) => {
        setUploadingCategoryImage(true);
        router.post(route('panel.catalog.products.references.categories.image.store', editingCategoryId), { image: file }, {
            preserveScroll: true,
            onFinish: () => setUploadingCategoryImage(false),
        });
    };
    const removeCategoryImage = () => {
        router.delete(route('panel.catalog.products.references.categories.image.destroy', editingCategoryId), { preserveScroll: true });
    };

    const submitAttribute = (event) => {
        event.preventDefault();
        attribute.post(route('panel.catalog.products.references.attributes.store'), { preserveScroll: true, onSuccess: () => attribute.reset('key', 'name') });
    };
    const submitValue = (event) => {
        event.preventDefault();
        value.post(route('panel.catalog.products.references.attribute-values.store', value.data.attribute_definition_id), { preserveScroll: true, onSuccess: () => value.reset('value_key', 'name', 'color_hex') });
    };
    const selectedAttribute = attributes.find((item) => item.id === Number(value.data.attribute_definition_id));

    const languageOptions = languages.map((l) => ({ label: l.name, value: l.code }));
    const languageSelect = (form) => (
        <Select label={t('catalog.references.language', 'Language')} value={form.data.locale} options={languageOptions} onChange={(e) => form.setData('locale', e.target.value)} />
    );

    const pageTitle = t('catalog.references.title', 'Brands and categories');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={pageTitle}
                    actions={
                        <Link href={route('panel.catalog.products.index')} style={{ fontSize: 13, color: 'var(--text-link)', alignSelf: 'center' }}>
                            {t('catalog.references.back_to_products', 'Products')}
                        </Link>
                    }
                />
            }
        >
            <Head title={pageTitle} />

            <div className="grid grid-cols-1 lg:grid-cols-2" style={{ padding: 24, gap: 20 }}>
                {status && (
                    <div className="lg:col-span-2">
                        <Alert tone="success">{status}</Alert>
                    </div>
                )}

                <Card title={t('catalog.references.brands_title', 'Brands')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <div style={{ display: 'flex', flexDirection: 'column' }}>
                            {brands.map((item) => (
                                <div key={item.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 0', borderBottom: '1px solid var(--border-subtle)', fontSize: 13 }}>
                                    <div style={{ width: 28, height: 28, borderRadius: 'var(--radius-sm)', overflow: 'hidden', background: 'var(--gray-2)', flexShrink: 0 }}>
                                        {item.logo_url && <img src={item.logo_url} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />}
                                    </div>
                                    <div style={{ flex: 1 }}>{item.name}</div>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => startEditBrand(item)}>{t('catalog.references.edit', 'Edit')}</Button>
                                </div>
                            ))}
                            {brands.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.references.no_brands', 'No brands.')}</div>}
                        </div>
                        <form onSubmit={submitBrand} style={{ display: 'flex', flexDirection: 'column', gap: 14, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                            <div style={{ fontSize: 14, fontWeight: 600 }}>{editingBrandId ? t('catalog.references.edit_brand_prefix', 'Edit brand: :name', { name: editingBrand?.name ?? '' }) : t('catalog.references.add_brand', 'Add brand')}</div>
                            {languageSelect(brand)}
                            <Input label={t('catalog.references.brand_name', 'Brand name')} required value={brand.data.name} error={brand.errors.name} onChange={(e) => brand.setData('name', e.target.value)} />
                            <WysiwygEditor
                                minHeight={160}
                                label={t('catalog.references.brand_description_optional', 'Brand description (optional)')}
                                value={brand.data.description_html}
                                error={brand.errors.description_html}
                                onChange={(v) => brand.setData('description_html', v)}
                            />
                            {editingBrandId && (
                                <SingleImageUpload
                                    label={t('catalog.references.brand_logo', 'Brand logo')}
                                    imageUrl={editingBrand?.logo_url}
                                    uploading={uploadingBrandLogo}
                                    onUpload={uploadBrandLogo}
                                    onRemove={removeBrandLogo}
                                />
                            )}
                            <div style={{ display: 'flex', gap: 8 }}>
                                <Button type="submit" variant="solid" disabled={brand.processing}>{editingBrandId ? t('catalog.references.save_brand', 'Save brand') : t('catalog.references.add_brand', 'Add brand')}</Button>
                                {editingBrandId && <Button type="button" variant="ghost" onClick={cancelEditBrand}>{t('catalog.references.cancel', 'Cancel')}</Button>}
                            </div>
                        </form>
                    </div>
                </Card>

                <Card title={t('catalog.references.category_tree_title', 'Category tree')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <CategoryTree nodes={buildCategoryTree(categories)} selectedId={editingCategoryId} onSelect={startEditCategory} />
                        <form onSubmit={submitCategory} style={{ display: 'flex', flexDirection: 'column', gap: 14, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                            <div style={{ fontSize: 14, fontWeight: 600 }}>{editingCategoryId ? t('catalog.references.edit_category_prefix', 'Edit category: :name', { name: editingCategory?.name ?? '' }) : t('catalog.references.add_category', 'Add category')}</div>
                            {languageSelect(category)}
                            <Input label={t('catalog.references.category_name', 'Category name')} required value={category.data.name} error={category.errors.name} onChange={(e) => category.setData('name', e.target.value)} />
                            <Select
                                label={t('catalog.references.parent_category', 'Parent category')}
                                value={category.data.parent_id}
                                options={[
                                    { label: t('catalog.references.no_parent', 'None — top level'), value: '' },
                                    ...categories.filter((c) => c.id !== editingCategoryId).map((c) => ({ label: c.name, value: c.id })),
                                ]}
                                error={category.errors.parent_id}
                                onChange={(e) => category.setData('parent_id', e.target.value ? Number(e.target.value) : '')}
                            />
                            {editingCategoryId && (
                                <Input
                                    type="number"
                                    min="0"
                                    label={t('catalog.references.position_among_siblings', 'Position among siblings')}
                                    value={category.data.position}
                                    error={category.errors.position}
                                    onChange={(e) => category.setData('position', Number(e.target.value))}
                                />
                            )}
                            <Checkbox
                                label={t('catalog.references.visible_in_tree', 'Visible in the category tree')}
                                checked={category.data.visible_in_tree}
                                onChange={(checked) => category.setData('visible_in_tree', checked)}
                            />
                            {editingCategoryId && (
                                <SingleImageUpload
                                    label={t('catalog.references.category_image', 'Category image')}
                                    imageUrl={editingCategory?.image_url}
                                    uploading={uploadingCategoryImage}
                                    onUpload={uploadCategoryImage}
                                    onRemove={removeCategoryImage}
                                />
                            )}
                            <div style={{ display: 'flex', gap: 8 }}>
                                <Button type="submit" variant="solid" disabled={category.processing}>{editingCategoryId ? t('catalog.references.save_category', 'Save category') : t('catalog.references.add_category', 'Add category')}</Button>
                                {editingCategoryId && <Button type="button" variant="ghost" onClick={cancelEditCategory}>{t('catalog.references.cancel', 'Cancel')}</Button>}
                            </div>
                        </form>
                    </div>
                </Card>

                <div className="lg:col-span-2">
                    <Card title={t('catalog.references.attributes_title', 'Attributes and shared values')}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3" style={{ gap: 12 }}>
                                {attributes.map((item) => (
                                    <div key={item.id} style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 14 }}>
                                        <div style={{ fontWeight: 600, fontSize: 13 }}>{item.name}</div>
                                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginTop: 2 }}>
                                            {item.key} · {attributeTypes.find((at) => at.value === item.type)?.label}
                                            {item.is_filterable ? ` · ${t('catalog.references.filter_suffix', 'filter')}` : ''}
                                        </div>
                                        <div style={{ marginTop: 8, display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                            {item.values.map((entry) => (
                                                <span
                                                    key={entry.id}
                                                    style={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 4,
                                                        background: 'var(--gray-3)',
                                                        borderRadius: 'var(--radius-sm)',
                                                        padding: '3px 8px',
                                                        fontSize: 12,
                                                    }}
                                                >
                                                    {entry.color_hex && (
                                                        <span style={{ width: 10, height: 10, borderRadius: '50%', border: '1px solid var(--border-default)', background: entry.color_hex }} />
                                                    )}
                                                    {entry.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {attributes.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.references.no_attributes', 'No attributes.')}</div>}
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2" style={{ gap: 24, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                                <form onSubmit={submitAttribute} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                    <div style={{ fontSize: 14, fontWeight: 600 }}>{t('catalog.references.add_attribute_title', 'Add attribute')}</div>
                                    {languageSelect(attribute)}
                                    <Input label={t('catalog.references.attribute_name', 'Name')} required value={attribute.data.name} error={attribute.errors.name} onChange={(e) => attribute.setData('name', e.target.value)} />
                                    <Input
                                        label={t('catalog.references.technical_key', 'Technical key')}
                                        required
                                        placeholder={t('catalog.references.key_placeholder', 'e.g. color')}
                                        value={attribute.data.key}
                                        error={attribute.errors.key}
                                        onChange={(e) => attribute.setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                                    />
                                    <Select
                                        label={t('catalog.references.attribute_type', 'Type')}
                                        value={attribute.data.type}
                                        options={attributeTypes}
                                        onChange={(e) => attribute.setData('type', e.target.value)}
                                    />
                                    <Checkbox
                                        label={t('catalog.references.available_as_filter', 'Available as filter')}
                                        checked={attribute.data.is_filterable}
                                        onChange={(checked) => attribute.setData('is_filterable', checked)}
                                    />
                                    <div>
                                        <Button type="submit" variant="solid" disabled={attribute.processing}>{t('catalog.references.add_attribute', 'Add attribute')}</Button>
                                    </div>
                                </form>

                                <form onSubmit={submitValue} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                    <div style={{ fontSize: 14, fontWeight: 600 }}>{t('catalog.references.add_value_title', 'Add dictionary value')}</div>
                                    <Select
                                        label={t('catalog.references.attribute_label', 'Attribute')}
                                        value={value.data.attribute_definition_id}
                                        placeholder={t('catalog.references.select', 'Select')}
                                        options={attributes.map((a) => ({ label: a.name, value: a.id }))}
                                        onChange={(e) => value.setData('attribute_definition_id', Number(e.target.value))}
                                    />
                                    {languageSelect(value)}
                                    <Input label={t('catalog.references.value_name', 'Value name')} required value={value.data.name} error={value.errors.name} onChange={(e) => value.setData('name', e.target.value)} />
                                    <Input
                                        label={t('catalog.references.value_key', 'Value key')}
                                        required
                                        placeholder={t('catalog.references.value_key_placeholder', 'e.g. red')}
                                        value={value.data.value_key}
                                        error={value.errors.value_key}
                                        onChange={(e) => value.setData('value_key', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))}
                                    />
                                    {selectedAttribute?.type === 'color' && (
                                        <ColorPicker
                                            label={t('catalog.references.color_hex', 'Color hex')}
                                            value={value.data.color_hex}
                                            error={value.errors.color_hex}
                                            onChange={(v) => value.setData('color_hex', v)}
                                        />
                                    )}
                                    <div>
                                        <Button type="submit" variant="solid" disabled={value.processing || !value.data.attribute_definition_id}>
                                            {t('catalog.references.add_value', 'Add value')}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
