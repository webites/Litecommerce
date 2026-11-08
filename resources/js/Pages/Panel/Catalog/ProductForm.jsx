import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import Textarea from '@/Components/ui/Textarea';
import WysiwygEditor from '@/Components/ui/WysiwygEditor';
import { useTranslate } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

export default function ProductForm({ form, languages, types, statuses, variantSeoModes, units, brands, categories, hasAxes, onSubmit, submitLabel }) {
    const t = useTranslate();
    const isSingleUnit = (type) => Boolean(types.find((option) => option.value === type)?.sold_as_single_unit);
    const setType = (type) => {
        form.setData((current) => isSingleUnit(type) ? {
            ...current, type, unit_code: 'piece', minimum_quantity: '1', maximum_quantity: '1', quantity_step: '1',
        } : { ...current, type });
    };

    const toggleCategory = (categoryId, checked) => {
        const ids = checked
            ? [...form.data.category_ids, categoryId]
            : form.data.category_ids.filter((id) => id !== categoryId);
        form.setData((current) => ({
            ...current,
            category_ids: ids,
            primary_category_id: ids.includes(current.primary_category_id) ? current.primary_category_id : '',
        }));
    };

    return (
        <form onSubmit={onSubmit} style={{ maxWidth: 800, display: 'flex', flexDirection: 'column', gap: 20 }}>
            <Card title={t('catalog.product_form.basic_info_title', 'Basic information')} description={t('catalog.product_form.basic_info_description', 'Content is saved separately for the selected language.')}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 16 }}>
                        <Select
                            id="locale"
                            label={t('catalog.product_form.content_language', 'Content language')}
                            value={form.data.locale}
                            options={languages.map((l) => ({ label: l.name, value: l.code }))}
                            error={form.errors.locale}
                            onChange={(e) => form.setData('locale', e.target.value)}
                        />
                        <Input
                            id="name"
                            label={t('catalog.product_form.product_name', 'Product name')}
                            required
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </div>
                    <Textarea
                        id="short_description"
                        label={t('catalog.product_form.short_description', 'Short description')}
                        rows={3}
                        value={form.data.short_description}
                        error={form.errors.short_description}
                        onChange={(v) => form.setData('short_description', v)}
                    />
                    <WysiwygEditor
                        label={t('catalog.product_form.full_description', 'Full HTML description')}
                        value={form.data.description_html}
                        error={form.errors.description_html}
                        onChange={(v) => form.setData('description_html', v)}
                    />
                </div>
            </Card>

            <Card title={t('catalog.product_form.classification_title', 'Classification')} description={t('catalog.product_form.classification_description', "The primary category is used in breadcrumbs and must belong to the product's categories.")}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Select
                        id="brand_id"
                        label={t('catalog.product_form.brand', 'Brand (optional)')}
                        value={form.data.brand_id || ''}
                        options={[{ label: t('catalog.product_form.no_brand', 'No brand'), value: '' }, ...brands.map((b) => ({ label: b.name, value: b.id }))]}
                        error={form.errors.brand_id}
                        onChange={(e) => form.setData('brand_id', e.target.value ? Number(e.target.value) : '')}
                    />
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 8 }}>
                        {categories.map((category) => (
                            <label
                                key={category.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    border: '1px solid var(--border-subtle)',
                                    borderRadius: 'var(--radius-control)',
                                    padding: '8px 12px',
                                    fontSize: 13,
                                }}
                            >
                                <Checkbox
                                    checked={form.data.category_ids.includes(category.id)}
                                    onChange={(checked) => toggleCategory(category.id, checked)}
                                />
                                {category.parent_id ? '↳ ' : ''}{category.name}
                            </label>
                        ))}
                        {categories.length === 0 && (
                            <span style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.product_form.no_categories_yet', 'No categories created yet.')}</span>
                        )}
                    </div>
                    {form.errors.category_ids && <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{form.errors.category_ids}</span>}
                    <Select
                        id="primary_category_id"
                        label={t('catalog.product_form.primary_category', 'Primary category')}
                        value={form.data.primary_category_id || ''}
                        options={[
                            { label: t('catalog.product_form.no_primary_category', 'No primary category'), value: '' },
                            ...categories.filter((c) => form.data.category_ids.includes(c.id)).map((c) => ({ label: c.name, value: c.id })),
                        ]}
                        error={form.errors.primary_category_id}
                        onChange={(e) => form.setData('primary_category_id', e.target.value ? Number(e.target.value) : '')}
                    />
                </div>
            </Card>

            <Card title={t('catalog.product_form.sales_title', 'Sales')}>
                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 16 }}>
                    <Select
                        id="type"
                        label={t('catalog.product_form.product_type', 'Product type')}
                        value={form.data.type}
                        options={types}
                        error={form.errors.type}
                        onChange={(e) => setType(e.target.value)}
                    />
                    <Select
                        id="status"
                        label={t('catalog.product_form.status', 'Status')}
                        value={form.data.status}
                        options={statuses}
                        error={form.errors.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    />
                    <Select
                        id="unit_code"
                        label={t('catalog.product_form.unit', 'Unit')}
                        disabled={isSingleUnit(form.data.type)}
                        value={form.data.unit_code}
                        options={units}
                        error={form.errors.unit_code}
                        onChange={(e) => form.setData('unit_code', e.target.value)}
                    />
                    <div style={{ display: 'flex', alignItems: 'center' }}>
                        <Checkbox
                            label={t('catalog.product_form.featured_product', 'Featured product')}
                            checked={form.data.featured}
                            onChange={(checked) => form.setData('featured', checked)}
                        />
                    </div>
                    <Input
                        id="minimum_quantity"
                        label={t('catalog.product_form.minimum_quantity', 'Minimum quantity')}
                        disabled={isSingleUnit(form.data.type)}
                        value={form.data.minimum_quantity}
                        error={form.errors.minimum_quantity}
                        onChange={(e) => form.setData('minimum_quantity', e.target.value)}
                    />
                    <Input
                        id="maximum_quantity"
                        label={t('catalog.product_form.maximum_quantity_optional', 'Maximum quantity (optional)')}
                        disabled={isSingleUnit(form.data.type)}
                        value={form.data.maximum_quantity}
                        error={form.errors.maximum_quantity}
                        onChange={(e) => form.setData('maximum_quantity', e.target.value)}
                    />
                    <Input
                        id="quantity_step"
                        label={t('catalog.product_form.quantity_step', 'Quantity step')}
                        disabled={isSingleUnit(form.data.type)}
                        value={form.data.quantity_step}
                        error={form.errors.quantity_step}
                        onChange={(e) => form.setData('quantity_step', e.target.value)}
                    />
                </div>
            </Card>

            {hasAxes && (
                <Card title={t('catalog.product_form.variant_seo_title', 'Variant SEO')} description={t('catalog.product_form.variant_seo_description', 'How to treat individual variant pages (color/size etc.) for search engines.')}>
                    <Select
                        id="variant_seo_mode"
                        label={t('catalog.product_form.variant_indexing', 'Variant indexing')}
                        value={form.data.variant_seo_mode}
                        options={variantSeoModes}
                        error={form.errors.variant_seo_mode}
                        onChange={(e) => form.setData('variant_seo_mode', e.target.value)}
                    />
                </Card>
            )}

            <Card
                title={t('catalog.product_form.default_variant_ids_title', 'Default variant identifiers')}
                description={t('catalog.product_form.default_variant_ids_description', 'An empty SKU will be generated. GTIN is optional and its checksum is validated.')}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 16 }}>
                        <Input id="sku" label={t('catalog.product_form.sku', 'SKU')} value={form.data.sku} error={form.errors.sku} onChange={(e) => form.setData('sku', e.target.value)} />
                        <Input id="gtin" label={t('catalog.product_form.gtin', 'GTIN / EAN / UPC')} value={form.data.gtin} error={form.errors.gtin} onChange={(e) => form.setData('gtin', e.target.value)} />
                        <Input id="mpn" label={t('catalog.product_form.mpn', 'MPN')} value={form.data.mpn} error={form.errors.mpn} onChange={(e) => form.setData('mpn', e.target.value)} />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                        <Button type="submit" variant="solid" disabled={form.processing}>
                            {submitLabel}
                        </Button>
                        <Link href={route('panel.catalog.products.index')} style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('catalog.product_form.cancel', 'Cancel')}
                        </Link>
                    </div>
                </div>
            </Card>
        </form>
    );
}
