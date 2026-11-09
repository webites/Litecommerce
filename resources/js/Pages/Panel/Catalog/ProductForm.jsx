import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import CategoryPicker from '@/Components/ui/CategoryPicker';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import Textarea from '@/Components/ui/Textarea';
import WysiwygEditor from '@/Components/ui/WysiwygEditor';
import { useTranslate } from '@/lib/i18n';
import { Link } from '@inertiajs/react';

function TierHeading({ title, description }) {
    return (
        <div style={{ marginTop: 8 }}>
            <div style={{ fontFamily: 'var(--font-display)', fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--text-secondary)' }}>{title}</div>
            {description && <div style={{ marginTop: 2, fontSize: 12, color: 'var(--text-tertiary)' }}>{description}</div>}
        </div>
    );
}

/**
 * Product form split into four importance tiers: required essentials
 * (always open), then optional-but-important, less important and least
 * important sections, all collapsed until needed. `extras` lets the edit
 * screen add its own cards (images, variants, ...) to a tier.
 */
export default function ProductForm({ form, languages, types, statuses, variantSeoModes, units, brands, categories, hasAxes, onSubmit, submitLabel, extras = {} }) {
    const t = useTranslate();
    const hasError = (...fields) => fields.some((field) => form.errors[field]);
    const isSingleUnit = (type) => Boolean(types.find((option) => option.value === type)?.sold_as_single_unit);
    const setType = (type) => {
        form.setData((current) => isSingleUnit(type) ? {
            ...current, type, unit_code: 'piece', minimum_quantity: '1', maximum_quantity: '1', quantity_step: '1',
        } : { ...current, type });
    };
    const setCategories = (ids, primaryId) => form.setData((current) => ({ ...current, category_ids: ids, primary_category_id: primaryId }));

    const categorySummary = [
        form.data.category_ids.length > 0 ? t('catalog.product_form.summary_categories', 'Categories: :count', { count: form.data.category_ids.length }) : t('catalog.product_form.summary_no_categories', 'No categories'),
        brands.find((brand) => brand.id === form.data.brand_id)?.name,
    ].filter(Boolean).join(' · ');
    const descriptionSummary = form.data.short_description || form.data.description_html ? t('catalog.product_form.summary_filled', 'filled in') : t('catalog.product_form.summary_empty', 'empty');

    const pickerLabels = {
        expand: t('catalog.product_form.category_expand', 'Expand'),
        collapse: t('catalog.product_form.category_collapse', 'Collapse'),
        primary: t('catalog.product_form.category_primary_badge', 'Primary'),
        makePrimary: t('catalog.product_form.category_make_primary', 'Make primary'),
        empty: t('catalog.product_form.no_categories_yet', 'No categories created yet.'),
        search: t('catalog.product_form.category_search', 'Search categories…'),
        noMatches: t('catalog.product_form.category_no_matches', 'No matching categories.'),
        noneSelected: t('catalog.product_form.category_none_selected', 'No category selected.'),
        selectedCount: t('catalog.product_form.category_selected_count', 'Selected: :count'),
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <TierHeading title={t('catalog.product_form.tier_required', 'Essentials — required')} />
            <Card title={t('catalog.product_form.basic_info_title', 'Basic information')} description={t('catalog.product_form.basic_info_description', 'Content is saved separately for the selected language.')}>
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
                </div>
            </Card>

            <TierHeading title={t('catalog.product_form.tier_important', 'Important — optional')} />
            <Card
                collapsible
                defaultOpen={false}
                forceOpen={hasError('short_description', 'description_html')}
                summary={descriptionSummary}
                title={t('catalog.product_form.descriptions_title', 'Descriptions')}
                description={t('catalog.product_form.descriptions_description', 'Shown on the product page. Saved for the selected content language.')}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
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

            <Card
                collapsible
                defaultOpen={false}
                forceOpen={hasError('category_ids', 'primary_category_id', 'brand_id')}
                summary={categorySummary}
                title={t('catalog.product_form.classification_title', 'Categories and brand')}
                description={t('catalog.product_form.classification_description', 'Tick every category the product belongs to; a parent category also includes its subcategories. The primary category is used in breadcrumbs.')}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <CategoryPicker
                        categories={categories}
                        selectedIds={form.data.category_ids}
                        primaryId={form.data.primary_category_id}
                        onChange={setCategories}
                        labels={pickerLabels}
                    />
                    {(form.errors.category_ids || form.errors.primary_category_id) && (
                        <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{form.errors.category_ids || form.errors.primary_category_id}</span>
                    )}
                    <Select
                        id="brand_id"
                        label={t('catalog.product_form.brand', 'Brand (optional)')}
                        value={form.data.brand_id || ''}
                        options={[{ label: t('catalog.product_form.no_brand', 'No brand'), value: '' }, ...brands.map((b) => ({ label: b.name, value: b.id }))]}
                        error={form.errors.brand_id}
                        onChange={(e) => form.setData('brand_id', e.target.value ? Number(e.target.value) : '')}
                    />
                </div>
            </Card>
            {extras.important}

            <TierHeading title={t('catalog.product_form.tier_minor', 'Less important')} />
            <Card
                collapsible
                defaultOpen={false}
                forceOpen={hasError('unit_code', 'minimum_quantity', 'maximum_quantity', 'quantity_step', 'featured')}
                summary={t('catalog.product_form.summary_unit', 'Unit: :unit', { unit: units.find((unit) => unit.value === form.data.unit_code)?.label ?? form.data.unit_code })}
                title={t('catalog.product_form.sales_title', 'Unit and quantities')}
            >
                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 16 }}>
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

            <Card
                collapsible
                defaultOpen={false}
                forceOpen={hasError('sku', 'gtin', 'mpn')}
                summary={form.data.sku || undefined}
                title={t('catalog.product_form.default_variant_ids_title', 'Default variant identifiers')}
                description={t('catalog.product_form.default_variant_ids_description', 'An empty SKU will be generated. GTIN is optional and its checksum is validated.')}
            >
                <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 16 }}>
                    <Input id="sku" label={t('catalog.product_form.sku', 'SKU')} value={form.data.sku} error={form.errors.sku} onChange={(e) => form.setData('sku', e.target.value)} />
                    <Input id="gtin" label={t('catalog.product_form.gtin', 'GTIN / EAN / UPC')} value={form.data.gtin} error={form.errors.gtin} onChange={(e) => form.setData('gtin', e.target.value)} />
                    <Input id="mpn" label={t('catalog.product_form.mpn', 'MPN')} value={form.data.mpn} error={form.errors.mpn} onChange={(e) => form.setData('mpn', e.target.value)} />
                </div>
            </Card>
            {extras.minor}

            {(hasAxes || extras.least) && <TierHeading title={t('catalog.product_form.tier_least', 'Least important')} />}
            {hasAxes && (
                <Card
                    collapsible
                    defaultOpen={false}
                    forceOpen={hasError('variant_seo_mode')}
                    title={t('catalog.product_form.variant_seo_title', 'Variant SEO')}
                    description={t('catalog.product_form.variant_seo_description', 'How to treat individual variant pages (color/size etc.) for search engines.')}
                >
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
            {extras.least}

            <div
                style={{
                    position: 'sticky', bottom: 0, zIndex: 5, display: 'flex', alignItems: 'center', gap: 16,
                    padding: '12px 16px', background: 'var(--bg-surface)', border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-card)', boxShadow: 'var(--shadow-sm)',
                }}
            >
                <Button type="button" variant="solid" disabled={form.processing} onClick={onSubmit}>
                    {submitLabel}
                </Button>
                <Link href={route('panel.catalog.products.index')} style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                    {t('catalog.product_form.cancel', 'Cancel')}
                </Link>
            </div>
        </div>
    );
}
