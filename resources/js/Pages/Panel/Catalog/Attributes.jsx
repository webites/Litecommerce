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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const rowStyle = { display: 'flex', alignItems: 'center', gap: 10, padding: '10px 0', borderBottom: '1px solid var(--border-subtle)', fontSize: 13 };

function ValueRow({ value, color, locale }) {
    const t = useTranslate();
    const form = useForm({ locale, name: value.name, position: value.position, color_hex: value.color_hex || '#2563eb' });
    const remove = () => {
        if (window.confirm(t('catalog.attributes.delete_value_confirm', 'Delete this value? It will be removed from products that use it.'))) {
            router.delete(route('panel.catalog.products.references.attribute-values.destroy', value.id), { preserveScroll: true });
        }
    };

    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8, flexWrap: 'wrap' }}>
            <div style={{ flex: 1, minWidth: 140 }}>
                <Input label={value.key} value={form.data.name} error={form.errors.name} onChange={(e) => form.setData('name', e.target.value)} />
            </div>
            {color && <ColorPicker value={form.data.color_hex} error={form.errors.color_hex} onChange={(v) => form.setData('color_hex', v)} />}
            <div style={{ width: 80 }}>
                <Input type="number" min="0" label={t('catalog.attributes.position', 'Order')} value={form.data.position} error={form.errors.position} onChange={(e) => form.setData('position', e.target.value)} />
            </div>
            <Button type="button" size="sm" variant="outline" loading={form.processing} onClick={() => form.put(route('panel.catalog.products.references.attribute-values.update', value.id), { preserveScroll: true })}>
                {t('catalog.attributes.save', 'Save')}
            </Button>
            <Button type="button" size="sm" variant="ghost" disabled={value.used_as_option} title={value.used_as_option ? t('catalog.attributes.value_in_use', 'Used by variants') : undefined} onClick={remove}>
                {t('catalog.attributes.delete', 'Delete')}
            </Button>
        </div>
    );
}

function AttributeModal({ attribute, groups, locale, onClose }) {
    const t = useTranslate();
    const form = useForm({
        locale,
        name: attribute.name,
        attribute_group_id: attribute.group_id || '',
        position: attribute.position,
        is_filterable: attribute.is_filterable,
        visible_on_product: attribute.visible_on_product,
    });
    const newValue = useForm({ locale, value_key: '', name: '', color_hex: '#2563eb' });
    const isSelect = ['single_select', 'multi_select', 'color'].includes(attribute.type);
    const errors = usePage().props.errors || {};
    const save = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, attribute_group_id: data.attribute_group_id || null }));
        form.put(route('panel.catalog.products.references.attributes.update', attribute.id), { preserveScroll: true });
    };
    const remove = () => {
        const message = attribute.products_count > 0
            ? t('catalog.attributes.delete_confirm_used', 'Delete this attribute? It will be removed from :count products.', { count: attribute.products_count })
            : t('catalog.attributes.delete_confirm', 'Delete this attribute?');
        if (window.confirm(message)) router.delete(route('panel.catalog.products.references.attributes.destroy', attribute.id), { preserveScroll: true, onSuccess: onClose });
    };
    const addValue = (event) => {
        event.preventDefault();
        newValue.post(route('panel.catalog.products.references.attribute-values.store', attribute.id), {
            preserveScroll: true, onSuccess: () => newValue.reset('value_key', 'name'),
        });
    };

    return (
        <Modal show title={t('catalog.attributes.edit_title', 'Attribute: :name', { name: attribute.name })} onClose={onClose} maxWidth={720}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                <form onSubmit={save} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                        <Input label={t('catalog.attributes.name', 'Name')} required value={form.data.name} error={form.errors.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <Select
                            label={t('catalog.attributes.group', 'Group')}
                            value={form.data.attribute_group_id}
                            options={[{ label: t('catalog.attributes.no_group', 'No group'), value: '' }, ...groups.map((group) => ({ label: group.name, value: group.id }))]}
                            error={form.errors.attribute_group_id}
                            onChange={(e) => form.setData('attribute_group_id', e.target.value ? Number(e.target.value) : '')}
                        />
                        <Input type="number" min="0" label={t('catalog.attributes.position', 'Order')} value={form.data.position} error={form.errors.position} onChange={(e) => form.setData('position', e.target.value)} />
                    </div>
                    <Checkbox label={t('catalog.attributes.filterable', 'Available as a storefront filter')} checked={form.data.is_filterable} onChange={(checked) => form.setData('is_filterable', checked)} />
                    <Checkbox label={t('catalog.attributes.visible_on_product', 'Show on the product page')} checked={form.data.visible_on_product} onChange={(checked) => form.setData('visible_on_product', checked)} />
                    {errors.attribute && <Alert tone="danger">{errors.attribute}</Alert>}
                    <div style={{ display: 'flex', gap: 12 }}>
                        <Button type="submit" variant="solid" loading={form.processing}>{t('catalog.attributes.save', 'Save')}</Button>
                        <Button type="button" variant="destructive" disabled={attribute.used_as_option} title={attribute.used_as_option ? t('catalog.attributes.in_use', 'Used as a variant option') : undefined} onClick={remove}>
                            {t('catalog.attributes.delete', 'Delete')}
                        </Button>
                    </div>
                </form>

                {isSelect && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14, borderTop: '1px solid var(--border-subtle)', paddingTop: 20 }}>
                        <div style={{ fontSize: 14, fontWeight: 600 }}>{t('catalog.attributes.values_title', 'Values')}</div>
                        {errors.value && <Alert tone="danger">{errors.value}</Alert>}
                        {attribute.values.map((value) => <ValueRow key={value.id} value={value} color={attribute.type === 'color'} locale={locale} />)}
                        {attribute.values.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.attributes.no_values', 'No values yet.')}</div>}
                        <form onSubmit={addValue} style={{ display: 'flex', alignItems: 'flex-end', gap: 8, flexWrap: 'wrap' }}>
                            <div style={{ flex: 1, minWidth: 140 }}>
                                <Input label={t('catalog.attributes.value_name', 'New value')} value={newValue.data.name} error={newValue.errors.name} onChange={(e) => newValue.setData('name', e.target.value)} />
                            </div>
                            <div style={{ width: 160 }}>
                                <Input label={t('catalog.attributes.value_key', 'Key')} value={newValue.data.value_key} error={newValue.errors.value_key} onChange={(e) => newValue.setData('value_key', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, '-'))} />
                            </div>
                            {attribute.type === 'color' && <ColorPicker value={newValue.data.color_hex} error={newValue.errors.color_hex} onChange={(v) => newValue.setData('color_hex', v)} />}
                            <Button type="submit" size="sm" variant="outline" loading={newValue.processing}>{t('catalog.attributes.add_value', 'Add value')}</Button>
                        </form>
                    </div>
                )}
            </div>
        </Modal>
    );
}

function GroupModal({ group, locale, onClose }) {
    const t = useTranslate();
    const form = useForm({ locale, name: group.name, position: group.position });
    const save = (event) => {
        event.preventDefault();
        form.put(route('panel.catalog.products.references.attribute-groups.update', group.id), { preserveScroll: true, onSuccess: onClose });
    };
    const remove = () => {
        if (window.confirm(t('catalog.attributes.delete_group_confirm', 'Delete this group? Its attributes will stay, without a group.'))) {
            router.delete(route('panel.catalog.products.references.attribute-groups.destroy', group.id), { preserveScroll: true, onSuccess: onClose });
        }
    };

    return (
        <Modal show title={t('catalog.attributes.edit_group_title', 'Group: :name', { name: group.name })} onClose={onClose} maxWidth={480}>
            <form onSubmit={save} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                <Input label={t('catalog.attributes.name', 'Name')} required value={form.data.name} error={form.errors.name} onChange={(e) => form.setData('name', e.target.value)} />
                <Input type="number" min="0" label={t('catalog.attributes.position', 'Order')} value={form.data.position} error={form.errors.position} onChange={(e) => form.setData('position', e.target.value)} />
                <div style={{ display: 'flex', gap: 12 }}>
                    <Button type="submit" variant="solid" loading={form.processing}>{t('catalog.attributes.save', 'Save')}</Button>
                    <Button type="button" variant="destructive" onClick={remove}>{t('catalog.attributes.delete', 'Delete')}</Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Attributes({ locale, attributeTypes, groups, attributes }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;
    const [editedAttributeId, setEditedAttributeId] = useState(null);
    const [editedGroupId, setEditedGroupId] = useState(null);
    const editedAttribute = attributes.find((item) => item.id === editedAttributeId) || null;
    const editedGroup = groups.find((item) => item.id === editedGroupId) || null;
    const groupForm = useForm({ locale, key: '', name: '' });
    const attributeForm = useForm({ locale, key: '', name: '', type: 'text', attribute_group_id: '', is_filterable: false, visible_on_product: true });
    const typeLabel = (type) => attributeTypes.find((item) => item.value === type)?.label ?? type;

    const sections = [
        ...groups.map((group) => ({ key: `g${group.id}`, title: group.name, items: attributes.filter((item) => item.group_id === group.id) })),
        { key: 'none', title: t('catalog.attributes.no_group_section', 'No group'), items: attributes.filter((item) => !groups.some((group) => group.id === item.group_id)) },
    ].filter((section) => section.items.length > 0);

    const submitGroup = (event) => {
        event.preventDefault();
        groupForm.post(route('panel.catalog.products.references.attribute-groups.store'), { preserveScroll: true, onSuccess: () => groupForm.reset('key', 'name') });
    };
    const submitAttribute = (event) => {
        event.preventDefault();
        attributeForm.transform((data) => ({ ...data, attribute_group_id: data.attribute_group_id || null }));
        attributeForm.post(route('panel.catalog.products.references.attributes.store'), { preserveScroll: true, onSuccess: () => attributeForm.reset('key', 'name') });
    };
    const title = t('catalog.attributes.title', 'Attribute catalog');

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={title}
                    description={t('catalog.attributes.description', 'Descriptive attributes and their groups. Groups keep the product form tidy and the specification on the product page readable.')}
                    actions={<Button variant="outline" href={route('panel.catalog.products.index')}>{t('catalog.references.back_to_products', 'Products')}</Button>}
                />
            )}
        >
            <Head title={title} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 900 }}>
                {status && <Alert tone="success">{status}</Alert>}

                <Card title={t('catalog.attributes.groups_title', 'Attribute groups')} description={t('catalog.attributes.groups_description', 'Examples: Dimensions, Material, Technical data. Groups are listed in the given order.')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div>
                            {groups.map((group) => (
                                <div key={group.id} style={rowStyle}>
                                    <div style={{ flex: 1, fontWeight: 600 }}>{group.name}</div>
                                    <span style={{ color: 'var(--text-tertiary)' }}>{t('catalog.attributes.attributes_count', 'Attributes: :count', { count: group.attributes_count })}</span>
                                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditedGroupId(group.id)}>{t('catalog.attributes.edit', 'Edit')}</Button>
                                </div>
                            ))}
                            {groups.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.attributes.no_groups', 'No groups yet.')}</div>}
                        </div>
                        <form onSubmit={submitGroup} style={{ display: 'flex', alignItems: 'flex-end', gap: 8, flexWrap: 'wrap' }}>
                            <div style={{ flex: 1, minWidth: 160 }}>
                                <Input label={t('catalog.attributes.new_group', 'New group')} value={groupForm.data.name} error={groupForm.errors.name} onChange={(e) => groupForm.setData('name', e.target.value)} />
                            </div>
                            <div style={{ width: 180 }}>
                                <Input label={t('catalog.attributes.key', 'Technical key')} placeholder="dimensions" value={groupForm.data.key} error={groupForm.errors.key} onChange={(e) => groupForm.setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))} />
                            </div>
                            <Button type="submit" variant="outline" loading={groupForm.processing}>{t('catalog.attributes.add_group', 'Add group')}</Button>
                        </form>
                    </div>
                </Card>

                <Card title={t('catalog.attributes.attributes_title', 'Attributes')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        {sections.map((section) => (
                            <div key={section.key}>
                                <div style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--text-secondary)', marginBottom: 4 }}>{section.title}</div>
                                {section.items.map((item) => (
                                    <div key={item.id} style={{ ...rowStyle, flexWrap: 'wrap' }}>
                                        <div style={{ flex: 1, minWidth: 160 }}>
                                            <div style={{ fontWeight: 600 }}>{item.name}</div>
                                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{item.key} · {typeLabel(item.type)} · {t('catalog.attributes.products_count', 'Products: :count', { count: item.products_count })}</div>
                                        </div>
                                        {item.is_filterable && <Badge tone="brand">{t('catalog.attributes.badge_filter', 'Filter')}</Badge>}
                                        {item.visible_on_product ? <Badge tone="success">{t('catalog.attributes.badge_visible', 'On product page')}</Badge> : <Badge>{t('catalog.attributes.badge_hidden', 'Hidden')}</Badge>}
                                        {item.used_as_option && <Badge tone="info">{t('catalog.attributes.badge_option', 'Variant option')}</Badge>}
                                        <Button type="button" size="sm" variant="ghost" onClick={() => setEditedAttributeId(item.id)}>{t('catalog.attributes.edit', 'Edit')}</Button>
                                    </div>
                                ))}
                            </div>
                        ))}
                        {attributes.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.attributes.no_attributes', 'No attributes yet.')}</div>}
                    </div>
                </Card>

                <Card collapsible defaultOpen={attributes.length === 0} title={t('catalog.attributes.add_attribute_title', 'Add attribute')}>
                    <form onSubmit={submitAttribute} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                            <Input label={t('catalog.attributes.name', 'Name')} required value={attributeForm.data.name} error={attributeForm.errors.name} onChange={(e) => attributeForm.setData('name', e.target.value)} />
                            <Input
                                label={t('catalog.attributes.key', 'Technical key')}
                                required
                                placeholder="material"
                                value={attributeForm.data.key}
                                error={attributeForm.errors.key}
                                onChange={(e) => attributeForm.setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                            />
                            <Select label={t('catalog.attributes.type', 'Type')} value={attributeForm.data.type} options={attributeTypes} onChange={(e) => attributeForm.setData('type', e.target.value)} />
                            <Select
                                label={t('catalog.attributes.group', 'Group')}
                                value={attributeForm.data.attribute_group_id}
                                options={[{ label: t('catalog.attributes.no_group', 'No group'), value: '' }, ...groups.map((group) => ({ label: group.name, value: group.id }))]}
                                onChange={(e) => attributeForm.setData('attribute_group_id', e.target.value ? Number(e.target.value) : '')}
                            />
                        </div>
                        <Checkbox label={t('catalog.attributes.filterable', 'Available as a storefront filter')} checked={attributeForm.data.is_filterable} onChange={(checked) => attributeForm.setData('is_filterable', checked)} />
                        <Checkbox label={t('catalog.attributes.visible_on_product', 'Show on the product page')} checked={attributeForm.data.visible_on_product} onChange={(checked) => attributeForm.setData('visible_on_product', checked)} />
                        <div><Button type="submit" variant="solid" loading={attributeForm.processing}>{t('catalog.attributes.add_attribute', 'Add attribute')}</Button></div>
                    </form>
                </Card>
            </div>

            {editedAttribute && <AttributeModal key={editedAttribute.id} attribute={editedAttribute} groups={groups} locale={locale} onClose={() => setEditedAttributeId(null)} />}
            {editedGroup && <GroupModal key={editedGroup.id} group={editedGroup} locale={locale} onClose={() => setEditedGroupId(null)} />}
        </AuthenticatedLayout>
    );
}
