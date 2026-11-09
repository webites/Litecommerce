import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import { useTranslate } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

function AttributeField({ attribute, error, onChange }) {
    const t = useTranslate();

    return (
        <div style={{ opacity: attribute.assigned ? 1 : 0.45, pointerEvents: attribute.assigned ? 'auto' : 'none' }}>
            {attribute.type === 'text' && <Input value={attribute.text_value} onChange={(event) => onChange({ text_value: event.target.value })} />}
            {attribute.type === 'number' && <Input type="number" step="0.000001" value={attribute.number_value} onChange={(event) => onChange({ number_value: event.target.value })} />}
            {attribute.type === 'boolean' && <Checkbox label={t('catalog.edit.yes', 'Yes')} checked={attribute.boolean_value} onChange={(checked) => onChange({ boolean_value: checked })} />}
            {(attribute.type === 'single_select' || attribute.type === 'color') && (
                <Select
                    value={attribute.value_ids[0] || ''}
                    options={[{ label: t('catalog.edit.select_value', 'Select a value'), value: '' }, ...attribute.values.map((value) => ({ label: value.name, value: value.id }))]}
                    onChange={(event) => onChange({ value_ids: event.target.value ? [Number(event.target.value)] : [] })}
                />
            )}
            {attribute.type === 'multi_select' && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
                    {attribute.values.map((value) => (
                        <Checkbox
                            key={value.id}
                            label={value.name}
                            checked={attribute.value_ids.includes(value.id)}
                            onChange={(checked) => onChange({ value_ids: checked ? [...attribute.value_ids, value.id] : attribute.value_ids.filter((id) => id !== value.id) })}
                        />
                    ))}
                </div>
            )}
            {error && <div style={{ marginTop: 6, fontSize: 12, color: 'var(--danger-text)' }}>{error}</div>}
        </div>
    );
}

function Group({ title, attributes, errors, indexOf, onChange }) {
    const t = useTranslate();
    const assigned = attributes.filter((attribute) => attribute.assigned).length;
    const [open, setOpen] = useState(assigned > 0);

    return (
        <div style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)' }}>
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
                style={{ display: 'flex', alignItems: 'center', gap: 8, width: '100%', padding: '10px 14px', border: 'none', background: 'none', cursor: 'pointer', textAlign: 'left', font: 'inherit', color: 'var(--text-primary)' }}
            >
                {open ? <ChevronDown size={15} color="var(--text-tertiary)" /> : <ChevronRight size={15} color="var(--text-tertiary)" />}
                <span style={{ fontSize: 13, fontWeight: 600 }}>{title}</span>
                <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('catalog.edit.attribute_group_count', ':assigned of :total set', { assigned, total: attributes.length })}</span>
            </button>
            <div hidden={!open} style={{ padding: '0 14px 14px', display: 'flex', flexDirection: 'column', gap: 14 }}>
                {attributes.map((attribute) => {
                    const index = indexOf(attribute.attribute_definition_id);

                    return (
                        <div key={attribute.attribute_definition_id} className="grid grid-cols-1 sm:grid-cols-[180px_1fr]" style={{ alignItems: 'start', gap: 16 }}>
                            <Checkbox label={attribute.name} checked={attribute.assigned} onChange={(checked) => onChange(attribute.attribute_definition_id, { assigned: checked })} />
                            <AttributeField
                                attribute={attribute}
                                error={errors[`attributes.${index}`] || errors[`attributes.${index}.value_ids`]}
                                onChange={(changes) => onChange(attribute.attribute_definition_id, changes)}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * Descriptive attributes of one product, arranged by attribute group so a
 * shop with many attributes keeps the form short.
 */
export default function ProductAttributes({ form, groups, onChange, onSubmit, onNewAttribute }) {
    const t = useTranslate();
    const [term, setTerm] = useState('');
    const attributes = form.data.attributes;
    const needle = term.trim().toLowerCase();
    const matching = needle ? attributes.filter((attribute) => attribute.name.toLowerCase().includes(needle)) : attributes;
    const indexOf = (definitionId) => attributes.findIndex((attribute) => attribute.attribute_definition_id === definitionId);

    const sections = [
        ...groups.map((group) => ({ key: `g${group.id}`, title: group.name, items: matching.filter((attribute) => attribute.group_id === group.id) })),
        { key: 'none', title: groups.length > 0 ? t('catalog.edit.attribute_group_none', 'Other attributes') : t('catalog.edit.attribute_group_all', 'All attributes'), items: matching.filter((attribute) => !groups.some((group) => group.id === attribute.group_id)) },
    ].filter((section) => section.items.length > 0);
    const assignedCount = attributes.filter((attribute) => attribute.assigned).length;

    return (
        <Card
            collapsible
            defaultOpen={false}
            summary={t('catalog.edit.attributes_summary', 'Set: :count', { count: assignedCount })}
            title={t('catalog.edit.attributes_title', 'Descriptive attributes')}
            description={t('catalog.edit.attributes_description', 'Shown on the product page (specification tab) when the attribute is set to be visible there, and usable as storefront filters. Attributes and groups are managed in the attribute catalog.')}
            action={(
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <Link href={route('panel.catalog.products.references.attributes.index')} style={{ fontSize: 13, color: 'var(--text-link)' }}>
                        {t('catalog.edit.manage_attributes', 'Manage attributes')}
                    </Link>
                    <Button type="button" variant="outline" size="sm" onClick={onNewAttribute}>{t('catalog.edit.new_attribute', 'New attribute')}</Button>
                </div>
            )}
        >
            <form onSubmit={onSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {attributes.length > 10 && (
                    <Input placeholder={t('catalog.edit.attribute_search', 'Search attributes…')} value={term} onChange={(event) => setTerm(event.target.value)} />
                )}
                {sections.map((section) => (
                    <Group key={`${section.key}-${needle}`} title={section.title} attributes={section.items} errors={form.errors} indexOf={indexOf} onChange={onChange} />
                ))}
                {attributes.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('catalog.edit.no_attributes_yet', 'No attributes created yet.')}</div>}
                <div><Button type="submit" loading={form.processing}>{t('catalog.edit.save_attributes', 'Save attributes')}</Button></div>
            </form>
        </Card>
    );
}
