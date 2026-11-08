import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import ColorPicker from '@/Components/ui/ColorPicker';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import Textarea from '@/Components/ui/Textarea';
import WysiwygEditor from '@/Components/ui/WysiwygEditor';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown as MoveDown, ChevronUp as MoveUp, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import ProductForm from './ProductForm';

export default function Edit({ product, languages, types, statuses, variantSeoModes, hasAxes, units, brands, categories, variantAttributes, variants, maxVariants, productAttributes, images, relations, relationTypes, taxRates, baseCurrencyCode, currencies }) {
    const t = useTranslate();
    const form = useForm(product);
    const generator = useForm({ options: variantAttributes.map((attribute) => ({ attribute_definition_id: attribute.id, attribute_value_ids: [] })) });
    const [editedVariantId, setEditedVariantId] = useState(null);
    const editingVariant = variants.find((v) => v.id === editedVariantId) || null;
    const variantForm = useForm({ locale: product.locale, sku: '', gtin: '', mpn: '', enabled: true, name: '', short_description: '', description_html: '', weight_grams: '', length_mm: '', width_mm: '', height_mm: '' });
    const attributesForm = useForm({ attributes: productAttributes });
    const taxForm = useForm({ tax_rate_id: product.tax_rate_id || '' });
    const saveTax = (event) => {
        event.preventDefault();
        taxForm.put(route('panel.catalog.products.tax.update', product.id), { preserveScroll: true, preserveState: true });
    };
    const priceForm = useForm({ regular_amount: '', regular_mode: 'gross', promotional_amount: '', promotional_mode: 'gross' });
    const savePrice = (event) => {
        event.preventDefault();
        priceForm.put(route('panel.catalog.products.variants.price.update', [product.id, editedVariantId]), { preserveScroll: true, preserveState: true });
    };
    const [overrideDrafts, setOverrideDrafts] = useState({});
    const [overrideErrors, setOverrideErrors] = useState({});
    const [savingOverride, setSavingOverride] = useState(null);
    const setOverrideField = (code, field, value) => {
        setOverrideDrafts((current) => ({ ...current, [code]: { ...current[code], [field]: value } }));
    };
    const saveOverride = (code) => {
        const draft = overrideDrafts[code] || {};
        setSavingOverride(code);
        router.put(route('panel.catalog.products.variants.price.override.update', [product.id, editedVariantId, code]), {
            regular_amount: draft.regular_amount || '',
            regular_mode: draft.regular_mode || 'gross',
            promotional_amount: draft.promotional_amount || '',
            promotional_mode: draft.promotional_mode || 'gross',
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => setOverrideErrors((current) => ({ ...current, [code]: errors })),
            onSuccess: () => setOverrideErrors((current) => ({ ...current, [code]: {} })),
            onFinish: () => setSavingOverride(null),
        });
    };
    const status = usePage().props.flash?.status;
    const canManagePricing = usePage().props.auth.can.managePricing;
    const canManageInventory = usePage().props.auth.can.manageInventory;
    const productType = types.find((option) => option.value === product.type) ?? {};
    const submit = (event) => { event.preventDefault(); form.put(route('panel.catalog.products.update', product.id), { preserveScroll: true }); };
    const archive = () => {
        if (window.confirm(t('catalog.edit.archive_confirm', 'Archive this product? Its data and history will be kept.'))) router.delete(route('panel.catalog.products.destroy', product.id));
    };

    // Creating an attribute/value from the modal reloads this page's props
    // (redirect-back). preserveState keeps the rest of this component's
    // local state (unsaved product fields, etc.) intact, but attributesForm
    // and generator each copied productAttributes/variantAttributes into
    // local state at mount, so a new definition or value never shows up
    // there on its own. Merge instead of replace, so in-progress edits on
    // rows the staff member already touched (assigned/value_ids/...) survive.
    const productAttributesSignature = productAttributes.map((a) => `${a.attribute_definition_id}:${a.values.length}`).join(',');
    useEffect(() => {
        attributesForm.setData((current) => {
            const byId = new Map(current.attributes.map((a) => [a.attribute_definition_id, a]));

            return {
                attributes: productAttributes.map((fresh) => {
                    const existing = byId.get(fresh.attribute_definition_id);

                    return existing ? { ...existing, values: fresh.values } : fresh;
                }),
            };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [productAttributesSignature]);

    const variantAttributeIds = variantAttributes.map((a) => a.id).join(',');
    useEffect(() => {
        generator.setData((current) => {
            const existingIds = new Set(current.options.map((o) => o.attribute_definition_id));
            const additions = variantAttributes
                .filter((attribute) => !existingIds.has(attribute.id))
                .map((attribute) => ({ attribute_definition_id: attribute.id, attribute_value_ids: [] }));

            return additions.length === 0 ? current : { options: [...current.options, ...additions] };
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [variantAttributeIds]);

    const toggleValue = (attributeId, valueId, checked) => {
        generator.setData('options', generator.data.options.map((option) => option.attribute_definition_id !== attributeId ? option : {
            ...option,
            attribute_value_ids: checked ? [...option.attribute_value_ids, valueId] : option.attribute_value_ids.filter((id) => id !== valueId),
        }));
    };
    const generate = (event) => {
        event.preventDefault();
        generator.transform((data) => ({ options: data.options.filter((option) => option.attribute_value_ids.length > 0) }));
        generator.post(route('panel.catalog.products.variants.generate', product.id), { preserveScroll: true, preserveState: true });
    };
    const editVariant = (variant) => {
        setEditedVariantId(variant.id);
        variantForm.clearErrors();
        variantForm.setData({
            locale: variant.locale || product.locale,
            sku: variant.sku,
            gtin: variant.gtin,
            mpn: variant.mpn,
            enabled: variant.enabled,
            name: variant.name,
            short_description: variant.short_description,
            description_html: variant.description_html,
            weight_grams: variant.weight_grams,
            length_mm: variant.length_mm,
            width_mm: variant.width_mm,
            height_mm: variant.height_mm,
        });
        priceForm.clearErrors();
        priceForm.setData({
            regular_amount: variant.price.regular_amount,
            regular_mode: variant.price.regular_mode,
            promotional_amount: variant.price.promotional_amount || '',
            promotional_mode: variant.price.promotional_mode || 'gross',
        });
        setOverrideDrafts(Object.fromEntries(currencies.map((c) => [c.code, {
            regular_amount: variant.price.overrides[c.code]?.regular_amount || '',
            regular_mode: variant.price.overrides[c.code]?.regular_mode || 'gross',
            promotional_amount: variant.price.overrides[c.code]?.promotional_amount || '',
            promotional_mode: variant.price.overrides[c.code]?.promotional_mode || 'gross',
        }])));
        setOverrideErrors({});
    };
    const saveVariant = (event) => {
        event.preventDefault();
        variantForm.put(route('panel.catalog.products.variants.update', [product.id, editedVariantId]), { preserveScroll: true, preserveState: true });
    };

    // Digital files — only relevant while editing a digital product's
    // variant. digital_files comes fresh from the server on every reload
    // (preserveState keeps editedVariantId, so the modal picks up the
    // updated list automatically after any action below).
    const newDigitalFileForm = useForm({ label: '', max_downloads: '', validity_days: '', file: null });
    const uploadDigitalFile = (event) => {
        event.preventDefault();
        newDigitalFileForm.post(route('panel.catalog.products.variants.digital-files.store', [product.id, editedVariantId]), {
            preserveScroll: true, preserveState: true,
            onSuccess: () => newDigitalFileForm.reset('label', 'max_downloads', 'validity_days', 'file'),
        });
    };
    const [digitalFileDrafts, setDigitalFileDrafts] = useState({});
    const digitalFileDraft = (file) => ({
        label: file.label, max_downloads: file.max_downloads ?? '', validity_days: file.validity_days ?? '', active: file.active,
        ...digitalFileDrafts[file.id],
    });
    const setDigitalFileDraftField = (fileId, field, value) => {
        setDigitalFileDrafts((current) => ({ ...current, [fileId]: { ...current[fileId], [field]: value } }));
    };
    const saveDigitalFileSettings = (file) => {
        const draft = digitalFileDraft(file);
        router.put(route('panel.catalog.products.variants.digital-files.update', [product.id, editedVariantId, file.id]), {
            label: draft.label, max_downloads: draft.max_downloads, validity_days: draft.validity_days, active: draft.active,
        }, { preserveScroll: true, preserveState: true });
    };
    const uploadDigitalFileVersion = (file, uploadedFile) => {
        router.post(route('panel.catalog.products.variants.digital-files.versions.store', [product.id, editedVariantId, file.id]), { file: uploadedFile }, { preserveScroll: true, preserveState: true });
    };
    const setAttribute = (definitionId, changes) => {
        attributesForm.setData('attributes', attributesForm.data.attributes.map((attribute) => attribute.attribute_definition_id === definitionId ? { ...attribute, ...changes } : attribute));
    };
    const saveAttributes = (event) => {
        event.preventDefault();
        attributesForm.transform((data) => ({
            attributes: data.attributes.filter((attribute) => attribute.assigned).map((attribute) => ({
                attribute_definition_id: attribute.attribute_definition_id,
                value_ids: attribute.value_ids,
                text_value: attribute.text_value,
                number_value: attribute.number_value,
                boolean_value: attribute.boolean_value,
            })),
        }));
        attributesForm.put(route('panel.catalog.products.attributes.update', product.id), { preserveScroll: true, preserveState: true });
    };

    // Product image gallery.
    const [uploadingImage, setUploadingImage] = useState(false);
    const uploadImage = (file) => {
        setUploadingImage(true);
        router.post(route('panel.catalog.products.images.store', product.id), { image: file }, {
            preserveScroll: true, preserveState: true, onFinish: () => setUploadingImage(false),
        });
    };
    const removeImage = (mediaId) => {
        router.delete(route('panel.catalog.products.images.destroy', [product.id, mediaId]), { preserveScroll: true, preserveState: true });
    };
    const moveImage = (index, direction) => {
        const target = index + direction;
        if (target < 0 || target >= images.length) return;
        const orderedIds = images.map((image) => image.id);
        [orderedIds[index], orderedIds[target]] = [orderedIds[target], orderedIds[index]];
        router.put(route('panel.catalog.products.images.reorder', product.id), { media_ids: orderedIds }, { preserveScroll: true, preserveState: true });
    };

    // Attribute quick-create, reachable without leaving the product form.
    const [showAttributeModal, setShowAttributeModal] = useState(false);
    const quickAttribute = useForm({ locale: product.locale, key: '', name: '', type: 'text', is_filterable: false });
    const quickValue = useForm({ locale: product.locale, attribute_definition_id: '', value_key: '', name: '', color_hex: '#2563eb' });
    const createAttribute = (event) => {
        event.preventDefault();
        quickAttribute.post(route('panel.catalog.products.references.attributes.store'), {
            preserveScroll: true, preserveState: true,
            onSuccess: () => quickAttribute.reset('key', 'name'),
        });
    };
    const createAttributeValue = (event) => {
        event.preventDefault();
        quickValue.post(route('panel.catalog.products.references.attribute-values.store', quickValue.data.attribute_definition_id), {
            preserveScroll: true, preserveState: true,
            onSuccess: () => quickValue.reset('value_key', 'name', 'color_hex'),
        });
    };
    const quickValueAttribute = productAttributes.find((a) => a.attribute_definition_id === Number(quickValue.data.attribute_definition_id));

    // Upsell / cross-sell relations.
    const relationsForm = useForm({ relations });
    const [relationSearch, setRelationSearch] = useState({});
    const [relationResults, setRelationResults] = useState({});
    const searchRelationCandidates = async (type, term) => {
        setRelationSearch((s) => ({ ...s, [type]: term }));
        if (term.trim().length < 2) {
            setRelationResults((r) => ({ ...r, [type]: [] }));
            return;
        }
        const response = await fetch(`${route('panel.catalog.products.relations.search', product.id)}?q=${encodeURIComponent(term)}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await response.json();
        setRelationResults((r) => ({ ...r, [type]: json.data }));
    };
    const addRelation = (type, candidate) => {
        if (!relationsForm.data.relations.some((r) => r.type === type && r.target_product_id === candidate.id)) {
            relationsForm.setData('relations', [...relationsForm.data.relations, { type, target_product_id: candidate.id, name: candidate.name, sku: candidate.sku }]);
        }
        setRelationSearch((s) => ({ ...s, [type]: '' }));
        setRelationResults((r) => ({ ...r, [type]: [] }));
    };
    const removeRelation = (type, targetProductId) => {
        relationsForm.setData('relations', relationsForm.data.relations.filter((r) => !(r.type === type && r.target_product_id === targetProductId)));
    };
    const saveRelations = (event) => {
        event.preventDefault();
        relationsForm.transform((data) => ({ relations: data.relations.map(({ type, target_product_id }) => ({ type, target_product_id })) }));
        relationsForm.put(route('panel.catalog.products.relations.update', product.id), { preserveScroll: true, preserveState: true });
    };

    const pageTitle = t('catalog.edit.title', 'Edit: :name', { name: product.name });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={pageTitle}
                    actions={
                        product.status !== 'archived' && (
                            <Button variant="destructive" size="sm" onClick={archive}>
                                {t('catalog.edit.archive', 'Archive')}
                            </Button>
                        )
                    }
                />
            }
        >
            <Head title={pageTitle} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 800 }}>
                {status && <Alert tone="success">{status}</Alert>}

                <ProductForm form={form} languages={languages} types={types} statuses={statuses} variantSeoModes={variantSeoModes} hasAxes={hasAxes} units={units} brands={brands} categories={categories} onSubmit={submit} submitLabel={t('catalog.edit.save_product', 'Save product')} />

                {canManagePricing && (
                    <Card title={t('catalog.edit.tax_title', 'VAT rate')} description={t('catalog.edit.tax_description', "A variant inherits the product's rate. No assignment means 0%.")}>
                        <form onSubmit={saveTax} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <Select
                                label={t('catalog.edit.vat_rate', 'VAT rate')}
                                value={taxForm.data.tax_rate_id}
                                options={[{ label: t('catalog.edit.no_vat', 'None (0%)'), value: '' }, ...taxRates.map((rate) => ({ label: rate.kind === 'percentage' ? `${rate.name} (${rate.percentage}%)` : rate.name, value: rate.id }))]}
                                error={taxForm.errors.tax_rate_id}
                                onChange={(e) => taxForm.setData('tax_rate_id', e.target.value ? Number(e.target.value) : '')}
                            />
                            <div><Button type="submit" loading={taxForm.processing}>{t('catalog.edit.save', 'Save')}</Button></div>
                        </form>
                    </Card>
                )}

                <Card
                    title={t('catalog.edit.images_title', 'Product images')}
                    description={t('catalog.edit.images_description', 'The first image is the main image. Variant images take precedence over product images.')}
                >
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
                            {images.map((image, index) => (
                                <div key={image.id} style={{ width: 96 }}>
                                    <div style={{ width: 96, height: 96, borderRadius: 'var(--radius-md)', overflow: 'hidden', border: '1px solid var(--border-subtle)', background: 'var(--gray-2)' }}>
                                        {image.url && <img src={image.url} alt={image.original_filename} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />}
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginTop: 4 }}>
                                        <div style={{ display: 'flex' }}>
                                            <button type="button" disabled={index === 0} onClick={() => moveImage(index, -1)} style={iconButtonStyle}><MoveUp size={14} /></button>
                                            <button type="button" disabled={index === images.length - 1} onClick={() => moveImage(index, 1)} style={iconButtonStyle}><MoveDown size={14} /></button>
                                        </div>
                                        <button type="button" onClick={() => removeImage(image.id)} style={iconButtonStyle}><Trash2 size={14} color="var(--danger-text)" /></button>
                                    </div>
                                </div>
                            ))}
                            <label
                                style={{
                                    width: 96, height: 96, border: '1px dashed var(--border-default)', borderRadius: 'var(--radius-md)',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', color: 'var(--text-tertiary)',
                                }}
                            >
                                <Plus size={20} />
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/gif"
                                    hidden
                                    disabled={uploadingImage}
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];
                                        if (file) uploadImage(file);
                                        event.target.value = '';
                                    }}
                                />
                            </label>
                        </div>
                        {images.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.edit.no_images', 'No images.')}</div>}
                    </div>
                </Card>

                <Card
                    title={t('catalog.edit.attributes_title', 'Descriptive attributes')}
                    description={t('catalog.edit.attributes_description', 'The same definitions and values are used in the product description, filters, and variant options.')}
                    action={<Button type="button" variant="outline" size="sm" onClick={() => setShowAttributeModal(true)}>{t('catalog.edit.new_attribute', 'New attribute')}</Button>}
                >
                    <form onSubmit={saveAttributes} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        {attributesForm.data.attributes.map((attribute, index) => (
                            <div
                                key={attribute.attribute_definition_id}
                                className="grid grid-cols-1 sm:grid-cols-[180px_1fr]"
                                style={{ alignItems: 'start', gap: 16, borderTop: index === 0 ? 'none' : '1px solid var(--border-subtle)', paddingTop: index === 0 ? 0 : 16 }}
                            >
                                <Checkbox label={attribute.name} checked={attribute.assigned} onChange={(checked) => setAttribute(attribute.attribute_definition_id, { assigned: checked })} />
                                <div style={{ opacity: attribute.assigned ? 1 : 0.45, pointerEvents: attribute.assigned ? 'auto' : 'none' }}>
                                    {attribute.type === 'text' && <Input value={attribute.text_value} onChange={(event) => setAttribute(attribute.attribute_definition_id, { text_value: event.target.value })} />}
                                    {attribute.type === 'number' && <Input type="number" step="0.000001" value={attribute.number_value} onChange={(event) => setAttribute(attribute.attribute_definition_id, { number_value: event.target.value })} />}
                                    {attribute.type === 'boolean' && <Checkbox label={t('catalog.edit.yes', 'Yes')} checked={attribute.boolean_value} onChange={(checked) => setAttribute(attribute.attribute_definition_id, { boolean_value: checked })} />}
                                    {(attribute.type === 'single_select' || attribute.type === 'color') && <Select value={attribute.value_ids[0] || ''} options={[{ label: t('catalog.edit.select_value', 'Select a value'), value: '' }, ...attribute.values.map((value) => ({ label: value.name, value: value.id }))]} onChange={(event) => setAttribute(attribute.attribute_definition_id, { value_ids: event.target.value ? [Number(event.target.value)] : [] })} />}
                                    {attribute.type === 'multi_select' && <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>{attribute.values.map((value) => <Checkbox key={value.id} label={value.name} checked={attribute.value_ids.includes(value.id)} onChange={(checked) => setAttribute(attribute.attribute_definition_id, { value_ids: checked ? [...attribute.value_ids, value.id] : attribute.value_ids.filter((id) => id !== value.id) })} />)}</div>}
                                    {(attributesForm.errors[`attributes.${index}`] || attributesForm.errors[`attributes.${index}.value_ids`]) && <div style={{ marginTop: 6, fontSize: 12, color: 'var(--danger-text)' }}>{attributesForm.errors[`attributes.${index}`] || attributesForm.errors[`attributes.${index}.value_ids`]}</div>}
                                </div>
                            </div>
                        ))}
                        {productAttributes.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.edit.no_attributes_yet', 'No attributes created yet.')}</div>}
                        <div><Button type="submit" loading={attributesForm.processing}>{t('catalog.edit.save_attributes', 'Save attributes')}</Button></div>
                    </form>
                </Card>

                <Card
                    title={t('catalog.edit.variants_title', 'Variants')}
                    description={(
                        <>
                            {t('catalog.edit.variants_description', 'The generator only adds missing combinations and keeps existing SKUs. Product limit: :max variants.', { max: maxVariants })}
                            {productType.tracks_stock && t('catalog.edit.variants_description_physical_suffix', ' Stock receipts and corrections are done from the warehouse view.')}
                        </>
                    )}
                    action={canManageInventory && productType.tracks_stock && (
                        <Button variant="outline" size="sm" href={route('panel.warehouse.index')}>{t('catalog.edit.warehouse', 'Warehouse')}</Button>
                    )}
                >
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <Table
                            columns={[
                                {
                                    header: t('catalog.edit.column_sku', 'SKU'),
                                    render: (v) => (
                                        <span style={{ fontFamily: 'var(--font-mono)' }}>
                                            {v.sku}
                                            {v.is_default && <span style={{ marginLeft: 8, fontSize: 12, color: 'var(--text-tertiary)' }}>{t('catalog.edit.default_suffix', 'default')}</span>}
                                        </span>
                                    ),
                                },
                                { header: t('catalog.edit.column_options', 'Options'), render: (v) => v.options.join(' · ') || t('catalog.edit.simple_variant', 'Simple variant') },
                                {
                                    header: t('catalog.edit.column_state', 'State'),
                                    render: (v) => {
                                        if (!v.enabled) return t('catalog.edit.disabled', 'Disabled');
                                        if (!v.price.has_price) return <Badge tone="warning">{t('catalog.edit.active_no_price', 'Active — no price')}</Badge>;

                                        return t('catalog.edit.active', 'Active');
                                    },
                                },
                                {
                                    header: t('catalog.edit.column_net', 'Net (:currency)', { currency: baseCurrencyCode }),
                                    align: 'right',
                                    render: (v) => v.price.base_quote?.net || '—',
                                },
                                {
                                    header: t('catalog.edit.column_gross', 'Gross (:currency)', { currency: baseCurrencyCode }),
                                    align: 'right',
                                    render: (v) => v.price.base_quote?.gross || '—',
                                },
                                {
                                    header: t('catalog.edit.column_promotion', 'Promotion'),
                                    align: 'right',
                                    render: (v) => (v.price.base_quote?.promotional_active ? (v.price.promotional_amount || '—') : (v.price.promotional_amount ? t('catalog.edit.inactive', 'inactive') : '—')),
                                },
                                {
                                    header: t('catalog.edit.column_stock', 'Stock'),
                                    align: 'right',
                                    render: (v) => (v.stock ? <Badge tone={stockTone(v.stock.available_quantity)}>{v.stock.available_quantity}</Badge> : '—'),
                                },
                                { header: '', align: 'right', render: (v) => <Button variant="outline" size="sm" onClick={() => editVariant(v)}>{t('catalog.edit.edit', 'Edit')}</Button> },
                            ]}
                            rows={variants}
                        />

                        <form onSubmit={generate} style={{ display: 'flex', flexDirection: 'column', gap: 16, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('catalog.edit.generate_combinations_title', 'Generate missing combinations')}</div>
                                <Button type="button" variant="outline" size="sm" onClick={() => setShowAttributeModal(true)}>{t('catalog.edit.new_attribute', 'New attribute')}</Button>
                            </div>
                            {variantAttributes.map((attribute) => (
                                <fieldset key={attribute.id} style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 14 }}>
                                    <legend style={{ padding: '0 4px', fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{attribute.name}</legend>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16 }}>
                                        {attribute.values.map((value) => (
                                            <div key={value.id} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                                <Checkbox
                                                    checked={generator.data.options.find((option) => option.attribute_definition_id === attribute.id)?.attribute_value_ids.includes(value.id) || false}
                                                    onChange={(checked) => toggleValue(attribute.id, value.id, checked)}
                                                />
                                                {value.color_hex && (
                                                    <span style={{ width: 14, height: 14, borderRadius: '50%', border: '1px solid var(--border-default)', background: value.color_hex }} />
                                                )}
                                                <span style={{ fontSize: 13 }}>{value.name}</span>
                                            </div>
                                        ))}
                                    </div>
                                </fieldset>
                            ))}
                            {variantAttributes.length === 0 && (
                                <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>
                                    {t('catalog.edit.no_variant_attributes', 'First add an attribute and its values in the brands/categories/attributes catalog.')}
                                </div>
                            )}
                            {generator.errors.options && <span style={{ fontSize: 13, color: 'var(--danger-text)' }}>{generator.errors.options}</span>}
                            <div>
                                <Button type="submit" variant="solid" disabled={generator.processing || variantAttributes.length === 0}>
                                    {t('catalog.edit.generate_variants', 'Generate variants')}
                                </Button>
                            </div>
                        </form>
                    </div>
                </Card>

                <Card
                    title={t('catalog.edit.relations_title', 'Related products')}
                    description={t('catalog.edit.relations_description', 'Upsell/cross-sell relations are one-directional — adding one here does not automatically appear on the related product.')}
                >
                    <form onSubmit={saveRelations} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        {relationTypes.map((relationType) => {
                            const items = relationsForm.data.relations.filter((r) => r.type === relationType.value);
                            const results = relationResults[relationType.value] || [];

                            return (
                                <div key={relationType.value} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                    <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{relationType.label}</div>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                        {items.map((item) => (
                                            <span
                                                key={item.target_product_id}
                                                style={{
                                                    display: 'inline-flex', alignItems: 'center', gap: 6,
                                                    background: 'var(--gray-3)', borderRadius: 'var(--radius-sm)', padding: '4px 6px 4px 10px', fontSize: 12,
                                                }}
                                            >
                                                {item.name}{item.sku ? ` · ${item.sku}` : ''}
                                                <button type="button" onClick={() => removeRelation(relationType.value, item.target_product_id)} style={{ ...iconButtonStyle, padding: 2 }}>
                                                    <X size={12} />
                                                </button>
                                            </span>
                                        ))}
                                        {items.length === 0 && <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('catalog.edit.no_related_products', 'No related products.')}</span>}
                                    </div>
                                    <div style={{ position: 'relative', maxWidth: 360 }}>
                                        <Input
                                            placeholder={t('catalog.edit.search_placeholder', 'Search product by name or SKU…')}
                                            value={relationSearch[relationType.value] || ''}
                                            onChange={(event) => searchRelationCandidates(relationType.value, event.target.value)}
                                        />
                                        {results.length > 0 && (
                                            <div
                                                style={{
                                                    position: 'absolute', zIndex: 10, top: '100%', left: 0, right: 0, marginTop: 4,
                                                    background: 'var(--bg-surface)', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)',
                                                    boxShadow: 'var(--shadow-sm)', maxHeight: 220, overflowY: 'auto',
                                                }}
                                            >
                                                {results.map((candidate) => (
                                                    <button
                                                        key={candidate.id}
                                                        type="button"
                                                        onClick={() => addRelation(relationType.value, candidate)}
                                                        style={{
                                                            display: 'block', width: '100%', textAlign: 'left', padding: '8px 12px',
                                                            fontSize: 13, background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-primary)',
                                                        }}
                                                    >
                                                        {candidate.name}{candidate.sku ? ` · ${candidate.sku}` : ''}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                        <div><Button type="submit" loading={relationsForm.processing}>{t('catalog.edit.save_relations', 'Save relations')}</Button></div>
                    </form>
                </Card>
            </div>

            <Modal
                show={editedVariantId !== null}
                title={`${t('catalog.edit.variant_modal_title_prefix', 'Edit variant')}${editingVariant ? ' — ' + editingVariant.sku : ''}`}
                onClose={() => setEditedVariantId(null)}
                maxWidth={760}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <form onSubmit={saveVariant} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('catalog.edit.variant_data_title', 'Variant data')}</div>
                            <Checkbox label={t('catalog.edit.variant_active', 'Variant active')} checked={variantForm.data.enabled} onChange={(checked) => variantForm.setData('enabled', checked)} />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 12 }}>
                            <Input label={t('catalog.product_form.sku', 'SKU')} value={variantForm.data.sku} error={variantForm.errors.sku} onChange={(event) => variantForm.setData('sku', event.target.value)} required />
                            <Input label={t('catalog.product_form.gtin', 'GTIN / EAN / UPC')} value={variantForm.data.gtin} error={variantForm.errors.gtin} onChange={(event) => variantForm.setData('gtin', event.target.value)} />
                            <Input label={t('catalog.product_form.mpn', 'MPN')} value={variantForm.data.mpn} error={variantForm.errors.mpn} onChange={(event) => variantForm.setData('mpn', event.target.value)} />
                        </div>
                        <Select label={t('catalog.edit.content_language', 'Own content language')} value={variantForm.data.locale} options={languages.map((language) => ({ label: language.name, value: language.code }))} error={variantForm.errors.locale} onChange={(event) => variantForm.setData('locale', event.target.value)} />
                        <Input label={t('catalog.edit.custom_variant_name', 'Custom variant name (optional)')} value={variantForm.data.name} error={variantForm.errors.name} onChange={(event) => variantForm.setData('name', event.target.value)} />
                        <Textarea label={t('catalog.edit.custom_short_description', 'Custom short description (optional)')} rows={3} value={variantForm.data.short_description} error={variantForm.errors.short_description} onChange={(value) => variantForm.setData('short_description', value)} />
                        <WysiwygEditor minHeight={160} label={t('catalog.edit.custom_description', 'Custom HTML description (optional)')} value={variantForm.data.description_html} error={variantForm.errors.description_html} onChange={(value) => variantForm.setData('description_html', value)} />
                        <div className="grid grid-cols-2 sm:grid-cols-4" style={{ gap: 12 }}>
                            <Input type="number" min="0" label={t('catalog.edit.weight_g', 'Weight (g)')} value={variantForm.data.weight_grams} error={variantForm.errors.weight_grams} onChange={(event) => variantForm.setData('weight_grams', event.target.value)} />
                            <Input type="number" min="0" label={t('catalog.edit.length_mm', 'Length (mm)')} value={variantForm.data.length_mm} error={variantForm.errors.length_mm} onChange={(event) => variantForm.setData('length_mm', event.target.value)} />
                            <Input type="number" min="0" label={t('catalog.edit.width_mm', 'Width (mm)')} value={variantForm.data.width_mm} error={variantForm.errors.width_mm} onChange={(event) => variantForm.setData('width_mm', event.target.value)} />
                            <Input type="number" min="0" label={t('catalog.edit.height_mm', 'Height (mm)')} value={variantForm.data.height_mm} error={variantForm.errors.height_mm} onChange={(event) => variantForm.setData('height_mm', event.target.value)} />
                        </div>
                        <div><Button type="submit" loading={variantForm.processing}>{t('catalog.edit.save_variant', 'Save variant')}</Button></div>
                    </form>

                    {canManagePricing && (
                        <form onSubmit={savePrice} style={{ display: 'flex', flexDirection: 'column', gap: 16, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                            <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('catalog.edit.price_title', 'Price — :currency', { currency: baseCurrencyCode || t('catalog.edit.base_currency_fallback', 'base currency') })}</div>
                            {!priceForm.data.regular_amount && (
                                <Alert tone="warning">{t('catalog.edit.no_price_warning', 'This variant has no price yet — customers cannot order it until you set one. "0.00" is a valid price for a free product; an empty price is not.')}</Alert>
                            )}
                            <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12, alignItems: 'end' }}>
                                <Input label={t('catalog.edit.regular_price', 'Regular price')} value={priceForm.data.regular_amount} error={priceForm.errors.regular_amount} onChange={(e) => priceForm.setData('regular_amount', e.target.value)} required />
                                <Select
                                    label={t('catalog.edit.mode', 'Mode')}
                                    value={priceForm.data.regular_mode}
                                    options={[{ label: t('catalog.edit.net', 'Net'), value: 'net' }, { label: t('catalog.edit.gross', 'Gross'), value: 'gross' }]}
                                    onChange={(e) => priceForm.setData('regular_mode', e.target.value)}
                                />
                                <Input label={t('catalog.edit.promotional_price_optional', 'Promotional price (optional)')} value={priceForm.data.promotional_amount} error={priceForm.errors.promotional_amount} onChange={(e) => priceForm.setData('promotional_amount', e.target.value)} />
                                <Select
                                    label={t('catalog.edit.mode', 'Mode')}
                                    value={priceForm.data.promotional_mode}
                                    options={[{ label: t('catalog.edit.net', 'Net'), value: 'net' }, { label: t('catalog.edit.gross', 'Gross'), value: 'gross' }]}
                                    onChange={(e) => priceForm.setData('promotional_mode', e.target.value)}
                                />
                            </div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                                {t('catalog.edit.promotion_note', 'If the promotional price, once converted to the effective amount, is not lower than the regular price, the promotion is automatically skipped when pricing — with no save error.')}
                            </div>
                            <div><Button type="submit" loading={priceForm.processing}>{t('catalog.edit.save_price', 'Save price')}</Button></div>

                            {currencies.length > 0 && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('catalog.edit.overrides_title', 'Per-currency price overrides')}</div>
                                    <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('catalog.edit.overrides_hint', 'Empty field = converted from the base price using the exchange rate.')}</div>
                                    {currencies.map((currency) => {
                                        const draft = overrideDrafts[currency.code] || {};
                                        const errors = overrideErrors[currency.code] || {};

                                        return (
                                            <div key={currency.code} style={{ display: 'flex', flexDirection: 'column', gap: 8, borderTop: '1px solid var(--border-subtle)', paddingTop: 12 }}>
                                                <div style={{ fontSize: 13, fontWeight: 600 }}>{currency.code} — {currency.name}</div>
                                                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12, alignItems: 'end' }}>
                                                    <Input
                                                        label={t('catalog.edit.regular_price', 'Regular price')}
                                                        value={draft.regular_amount || ''}
                                                        error={errors.regular_amount}
                                                        onChange={(e) => setOverrideField(currency.code, 'regular_amount', e.target.value)}
                                                    />
                                                    <Select
                                                        label={t('catalog.edit.mode', 'Mode')}
                                                        value={draft.regular_mode || 'gross'}
                                                        options={[{ label: t('catalog.edit.net', 'Net'), value: 'net' }, { label: t('catalog.edit.gross', 'Gross'), value: 'gross' }]}
                                                        onChange={(e) => setOverrideField(currency.code, 'regular_mode', e.target.value)}
                                                    />
                                                    <Input
                                                        label={t('catalog.edit.promotional_price', 'Promotional price')}
                                                        value={draft.promotional_amount || ''}
                                                        error={errors.promotional_amount}
                                                        onChange={(e) => setOverrideField(currency.code, 'promotional_amount', e.target.value)}
                                                    />
                                                    <Select
                                                        label={t('catalog.edit.mode', 'Mode')}
                                                        value={draft.promotional_mode || 'gross'}
                                                        options={[{ label: t('catalog.edit.net', 'Net'), value: 'net' }, { label: t('catalog.edit.gross', 'Gross'), value: 'gross' }]}
                                                        onChange={(e) => setOverrideField(currency.code, 'promotional_mode', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Button type="button" variant="outline" size="sm" loading={savingOverride === currency.code} onClick={() => saveOverride(currency.code)}>
                                                        {t('catalog.edit.save_override', 'Save :currency override', { currency: currency.code })}
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </form>
                    )}

                    {productType.grants_digital_access && editingVariant && (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                            <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-primary)' }}>{t('catalog.edit.digital_files_title', 'Digital files')}</div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                                {t('catalog.edit.digital_files_hint', "A purchase locks in the current file version — uploading a new version does not change access already granted to customers. Disabling a file does not revoke access already granted.")}
                            </div>
                            {editingVariant.digital_files.map((file) => {
                                const draft = digitalFileDraft(file);

                                return (
                                    <div key={file.id} style={{ display: 'flex', flexDirection: 'column', gap: 10, border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 14 }}>
                                        <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                                            <Input label={t('catalog.edit.file_name', 'File name')} value={draft.label} onChange={(e) => setDigitalFileDraftField(file.id, 'label', e.target.value)} />
                                            <Checkbox label={t('catalog.edit.file_active', 'File active (available for new purchases)')} checked={draft.active} onChange={(checked) => setDigitalFileDraftField(file.id, 'active', checked)} />
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                                            <Input type="number" min="1" label={t('catalog.edit.download_limit_hint', 'Download limit (empty = unlimited)')} value={draft.max_downloads} onChange={(e) => setDigitalFileDraftField(file.id, 'max_downloads', e.target.value)} />
                                            <Input type="number" min="1" label={t('catalog.edit.validity_days_hint', 'Validity in days from purchase (empty = unlimited)')} value={draft.validity_days} onChange={(e) => setDigitalFileDraftField(file.id, 'validity_days', e.target.value)} />
                                        </div>
                                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                                            {t('catalog.edit.current_version', 'Current version')}: {file.current_version ? `${file.current_version.original_filename} (v${file.current_version.version_number})` : t('catalog.edit.none', 'none')}
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                            <Button type="button" variant="outline" size="sm" onClick={() => saveDigitalFileSettings(file)}>{t('catalog.edit.save_settings', 'Save settings')}</Button>
                                            <label style={{ fontSize: 13, color: 'var(--accent-text)', cursor: 'pointer' }}>
                                                {t('catalog.edit.upload_new_version', 'Upload new version')}
                                                <input
                                                    type="file"
                                                    hidden
                                                    onChange={(event) => {
                                                        const uploaded = event.target.files?.[0];
                                                        if (uploaded) uploadDigitalFileVersion(file, uploaded);
                                                        event.target.value = '';
                                                    }}
                                                />
                                            </label>
                                        </div>
                                    </div>
                                );
                            })}
                            {editingVariant.digital_files.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.edit.no_files_yet', 'No file has been added yet.')}</div>}

                            <form onSubmit={uploadDigitalFile} style={{ display: 'flex', flexDirection: 'column', gap: 12, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                                <div style={{ fontSize: 13, fontWeight: 600 }}>{t('catalog.edit.add_new_file', 'Add new file')}</div>
                                <Input label={t('catalog.edit.file_name', 'File name')} required value={newDigitalFileForm.data.label} error={newDigitalFileForm.errors.label} onChange={(e) => newDigitalFileForm.setData('label', e.target.value)} />
                                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                                    <Input type="number" min="1" label={t('catalog.edit.download_limit_optional', 'Download limit (optional)')} value={newDigitalFileForm.data.max_downloads} error={newDigitalFileForm.errors.max_downloads} onChange={(e) => newDigitalFileForm.setData('max_downloads', e.target.value)} />
                                    <Input type="number" min="1" label={t('catalog.edit.validity_days_optional', 'Validity in days (optional)')} value={newDigitalFileForm.data.validity_days} error={newDigitalFileForm.errors.validity_days} onChange={(e) => newDigitalFileForm.setData('validity_days', e.target.value)} />
                                </div>
                                <input
                                    type="file"
                                    required
                                    onChange={(event) => newDigitalFileForm.setData('file', event.target.files?.[0] || null)}
                                />
                                {newDigitalFileForm.errors.file && <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{newDigitalFileForm.errors.file}</span>}
                                <div><Button type="submit" loading={newDigitalFileForm.processing}>{t('catalog.edit.add_file', 'Add file')}</Button></div>
                            </form>
                        </div>
                    )}
                </div>
            </Modal>

            <Modal show={showAttributeModal} title={t('catalog.edit.new_attribute_modal_title', 'New attribute')} onClose={() => setShowAttributeModal(false)}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <form onSubmit={createAttribute} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div style={{ fontSize: 13, fontWeight: 600 }}>{t('catalog.edit.new_attribute_definition', 'New attribute definition')}</div>
                        <Input label={t('catalog.edit.name', 'Name')} required value={quickAttribute.data.name} error={quickAttribute.errors.name} onChange={(e) => quickAttribute.setData('name', e.target.value)} />
                        <Input
                            label={t('catalog.edit.technical_key', 'Technical key')}
                            required
                            placeholder={t('catalog.edit.key_placeholder', 'e.g. color')}
                            value={quickAttribute.data.key}
                            error={quickAttribute.errors.key}
                            onChange={(e) => quickAttribute.setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                        />
                        <Select
                            label={t('catalog.edit.type', 'Type')}
                            value={quickAttribute.data.type}
                            options={[
                                { label: t('catalog.edit.text_type', 'Text'), value: 'text' },
                                { label: t('catalog.edit.number_type', 'Number'), value: 'number' },
                                { label: t('catalog.edit.boolean_type', 'Yes / no'), value: 'boolean' },
                                { label: t('catalog.edit.single_select_type', 'Single select'), value: 'single_select' },
                                { label: t('catalog.edit.multi_select_type', 'Multi select'), value: 'multi_select' },
                                { label: t('catalog.edit.color_type', 'Color'), value: 'color' },
                            ]}
                            onChange={(e) => quickAttribute.setData('type', e.target.value)}
                        />
                        <Checkbox label={t('catalog.edit.filterable', 'Available as filter')} checked={quickAttribute.data.is_filterable} onChange={(checked) => quickAttribute.setData('is_filterable', checked)} />
                        <div><Button type="submit" variant="solid" disabled={quickAttribute.processing}>{t('catalog.edit.add_attribute', 'Add attribute')}</Button></div>
                    </form>

                    <form onSubmit={createAttributeValue} style={{ display: 'flex', flexDirection: 'column', gap: 14, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                        <div style={{ fontSize: 13, fontWeight: 600 }}>{t('catalog.edit.new_value_title', 'New dictionary value')}</div>
                        <Select
                            label={t('catalog.edit.attribute', 'Attribute')}
                            placeholder={t('catalog.edit.select', 'Select')}
                            value={quickValue.data.attribute_definition_id}
                            options={productAttributes.filter((a) => ['single_select', 'multi_select', 'color'].includes(a.type)).map((a) => ({ label: a.name, value: a.attribute_definition_id }))}
                            onChange={(e) => quickValue.setData('attribute_definition_id', Number(e.target.value))}
                        />
                        <Input label={t('catalog.edit.value_name', 'Value name')} required value={quickValue.data.name} error={quickValue.errors.name} onChange={(e) => quickValue.setData('name', e.target.value)} />
                        <Input
                            label={t('catalog.edit.value_key', 'Value key')}
                            required
                            placeholder={t('catalog.edit.value_key_placeholder', 'e.g. red')}
                            value={quickValue.data.value_key}
                            error={quickValue.errors.value_key}
                            onChange={(e) => quickValue.setData('value_key', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))}
                        />
                        {quickValueAttribute?.type === 'color' && (
                            <ColorPicker label={t('catalog.edit.color_hex', 'Color hex')} value={quickValue.data.color_hex} error={quickValue.errors.color_hex} onChange={(v) => quickValue.setData('color_hex', v)} />
                        )}
                        <div>
                            <Button type="submit" variant="solid" disabled={quickValue.processing || !quickValue.data.attribute_definition_id}>
                                {t('catalog.edit.add_value', 'Add value')}
                            </Button>
                        </div>
                    </form>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function stockTone(available) {
    const value = Number(available);

    if (value <= 0) return 'danger';
    if (value < 5) return 'warning';

    return 'success';
}

const iconButtonStyle = {
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    width: 22, height: 22, background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-tertiary)', padding: 0,
};
