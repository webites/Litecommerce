import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import Switch from '@/Components/ui/Switch';
import Table from '@/Components/ui/Table';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import MethodPreview from './MethodPreview';
import PickupPointFields from './PickupPointFields';
import RegionRulesEditor from './RegionRulesEditor';
import { basicFields, cleanInstructions, emptyPoint, normalizeInstructions } from './helpers';

let draftCounter = 0;

const blankToNull = (value) => (value === '' || value === undefined ? null : value);

export default function EditMethodModal({ method, currencies, taxRates, priceEntryMode, countries, languages, baseCurrency, onClose }) {
    const t = useTranslate();
    const isPickup = method.kind === 'pickup';
    const nonBaseCurrencies = currencies.filter((c) => !c.is_base);
    const priceModeLabel = priceEntryMode === 'net' ? t('delivery.price_mode_net', 'net') : t('delivery.price_mode_gross', 'gross');

    const form = useForm({
        ...basicFields(method),
        currency_overrides: Object.fromEntries(nonBaseCurrencies.map((c) => {
            const existing = method.currency_overrides.find((o) => o.currency_code === c.code);

            return [c.code, { price_amount: existing?.price_amount ?? '', free_threshold_amount: existing?.free_threshold_amount ?? '' }];
        })),
        region_rules: method.region_rules.map((rule) => ({ ...rule, currency_overrides: rule.currency_overrides ?? [] })),
        pickup_points: method.pickup_points.map((point) => ({ ...point, region: point.region ?? '', address_line_2: point.address_line_2 ?? '', instructions: normalizeInstructions(point.instructions) })),
    });
    const [editingIndex, setEditingIndex] = useState(null);
    const points = form.data.pickup_points;
    const setPoints = (next) => form.setData('pickup_points', next);
    const updatePoint = (index, field, value) => setPoints(points.map((point, i) => (i === index ? { ...point, [field]: value } : point)));
    const addPoint = () => {
        setPoints([...points, { ...emptyPoint(countries), draftKey: `new-${++draftCounter}` }]);
        setEditingIndex(points.length);
    };
    const removePoint = (index) => {
        const point = points[index];

        if (point.id && !confirm(t('delivery.confirm_delete_point', 'Delete pickup point ":name"?', { name: point.name }))) return;

        setPoints(points.filter((_, i) => i !== index));
        setEditingIndex(null);
    };
    const pointErrors = (index) => Object.fromEntries(Object.entries(form.errors).filter(([key]) => key.startsWith(`pickup_points.${index}.`)).map(([key, value]) => [key.replace(`pickup_points.${index}.`, ''), value]));
    const pointHasErrors = (index) => Object.keys(pointErrors(index)).length > 0;

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            currency_overrides: nonBaseCurrencies.map((c) => ({
                currency_code: c.code,
                price_amount: blankToNull(data.currency_overrides[c.code]?.price_amount),
                free_threshold_amount: blankToNull(data.currency_overrides[c.code]?.free_threshold_amount),
            })),
            region_rules: isPickup ? undefined : data.region_rules,
            pickup_points: isPickup ? data.pickup_points.map(({ draftKey, ...point }) => ({ ...point, instructions: cleanInstructions(point.instructions) })) : undefined,
        }));
        form.put(route('panel.delivery.update', method.id), {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errors) => {
                const index = Object.keys(errors).map((key) => key.match(/^pickup_points\.(\d+)\./)).find(Boolean)?.[1];

                if (index !== undefined) setEditingIndex(Number(index));
            },
        });
    };

    const close = () => {
        if (form.isDirty && !confirm(t('delivery.confirm_discard', 'You have unsaved changes. Close without saving?'))) return;
        onClose();
    };

    const overrideField = isPickup ? 'price_amount' : 'free_threshold_amount';
    const setOverride = (code, value) => form.setData('currency_overrides', { ...form.data.currency_overrides, [code]: { ...form.data.currency_overrides[code], [overrideField]: value } });
    const errorKeys = Object.keys(form.errors);
    const hasOtherErrors = errorKeys.some((key) => !['active', 'region_rules'].includes(key) && !key.startsWith('pickup_points.'));

    return (
        <Modal show title={t('delivery.modal_edit_title', 'Editing: :name', { name: method.name })} onClose={close} maxWidth={760}>
            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                <MethodPreview
                    kind={method.kind} name={form.data.name} price={form.data.price_amount} rules={form.data.region_rules}
                    points={points} payOnPickup={form.data.supports_pay_on_pickup} currency={baseCurrency}
                />

                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                    <Input required label={t('delivery.fields.method_name', 'Method name')} helpText={t('delivery.fields.method_name_help', 'What customers see at checkout.')} value={form.data.name} error={form.errors.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <Select
                        label={t('delivery.fields.tax_rate', 'VAT rate')} placeholder={t('delivery.fields.tax_rate_placeholder', 'Not assigned (0%)')} value={form.data.tax_rate_id} error={form.errors.tax_rate_id}
                        options={taxRates.map((r) => ({ value: r.id, label: r.name }))}
                        onChange={(e) => form.setData('tax_rate_id', e.target.value)}
                    />
                    {isPickup ? (
                        <>
                            <Input
                                label={t('delivery.fields.price_with_mode', 'Price (:mode)', { mode: priceModeLabel })} placeholder={t('delivery.fields.price_placeholder_free', '0 = free')}
                                value={form.data.price_amount} error={form.errors.price_amount} onChange={(e) => form.setData('price_amount', e.target.value)}
                            />
                            <Input
                                type="number" min="1" max="365" label={t('delivery.fields.pickup_deadline_days', 'Pickup deadline (days)')} placeholder={t('delivery.fields.pickup_deadline_placeholder', 'No deadline')}
                                value={form.data.pickup_deadline_days} error={form.errors.pickup_deadline_days} onChange={(e) => form.setData('pickup_deadline_days', e.target.value)}
                            />
                        </>
                    ) : (
                        <Input
                            label={t('delivery.fields.free_threshold', 'Free shipping threshold')} placeholder={t('delivery.fields.free_threshold_placeholder_none', 'No threshold')}
                            helpText={t('delivery.fields.free_threshold_help', 'Orders above this amount ship for free.')}
                            value={form.data.free_threshold_amount} error={form.errors.free_threshold_amount} onChange={(e) => form.setData('free_threshold_amount', e.target.value)}
                        />
                    )}
                </div>
                <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                    <Switch label={t('delivery.column_active_method', 'Active')} checked={form.data.active} onChange={(v) => form.setData('active', v)} />
                    {isPickup
                        ? <Checkbox label={t('delivery.fields.supports_pay_on_pickup', 'Customer can pay on pickup')} checked={form.data.supports_pay_on_pickup} onChange={(v) => form.setData('supports_pay_on_pickup', v)} />
                        : <Checkbox label={t('delivery.fields.supports_cod', 'Accepts cash on delivery (COD)')} checked={form.data.supports_cash_on_delivery} onChange={(v) => form.setData('supports_cash_on_delivery', v)} />}
                </div>
                {form.errors.active && <Alert tone="warning">{form.errors.active}</Alert>}

                {nonBaseCurrencies.length > 0 && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 4 }}>{t('delivery.currency_overrides_title', 'Currency overrides')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 10 }}>{t('delivery.currency_overrides_hint', 'An empty field means conversion from the base currency at the current rate.')}</div>
                        {nonBaseCurrencies.map((c) => (
                            <div key={c.code} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', marginBottom: 8 }}>
                                <div style={{ width: 56, fontSize: 13, paddingBottom: 9 }}>{c.code}</div>
                                <Input
                                    size="sm" label={isPickup ? t('delivery.fields.price', 'Price') : t('delivery.fields.threshold_short', 'Threshold')} placeholder="—"
                                    value={form.data.currency_overrides[c.code]?.[overrideField] ?? ''} onChange={(e) => setOverride(c.code, e.target.value)}
                                />
                            </div>
                        ))}
                    </div>
                )}

                {!isPickup && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <RegionRulesEditor rules={form.data.region_rules} onChange={(rules) => form.setData('region_rules', rules)} countries={countries} priceModeLabel={priceModeLabel} error={form.errors.region_rules} />
                    </div>
                )}

                {isPickup && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 4 }}>{t('delivery.pickup_points_title', 'Pickup points')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 10 }}>{t('delivery.pickup_points_hint', 'Points are saved together with the method when you press Save.')}</div>
                        {points.length === 0 ? (
                            <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginBottom: 12 }}>{t('delivery.no_points_yet', 'No pickup points yet. Add the first one — without it customers cannot see this method.')}</div>
                        ) : (
                            <Table
                                columns={[
                                    { header: t('delivery.fields.point_name', 'Pickup point name'), render: (p) => <span>{p.name || t('delivery.point_unnamed', 'New point')} {!p.id && <Badge tone="brand">{t('delivery.point_new_badge', 'New')}</Badge>} {pointHasErrors(p.index) && <Badge tone="danger">{t('delivery.point_has_errors', 'Needs fixing')}</Badge>}</span> },
                                    { header: t('delivery.column_address', 'Address'), render: (p) => [p.address_line_1, `${p.postal_code} ${p.city}`.trim()].filter(Boolean).join(', ') },
                                    { header: t('delivery.column_active_point', 'Active'), render: (p) => <Switch checked={p.active} onChange={(v) => updatePoint(p.index, 'active', v)} /> },
                                    {
                                        header: '', align: 'right',
                                        render: (p) => (
                                            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                                                <Button type="button" variant="outline" size="sm" onClick={() => setEditingIndex(editingIndex === p.index ? null : p.index)}>{editingIndex === p.index ? t('delivery.point_done', 'Done') : t('delivery.edit', 'Edit')}</Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={() => removePoint(p.index)}><Trash2 size={14} /></Button>
                                            </div>
                                        ),
                                    },
                                ]}
                                rows={points.map((point, index) => ({ ...point, index }))}
                                cards
                            />
                        )}
                        {editingIndex !== null && points[editingIndex] && (
                            <div style={{ marginTop: 12, padding: 14, border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', background: 'var(--bg-subtle, transparent)' }}>
                                <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 10 }}>{points[editingIndex].id ? t('delivery.point_form_title_edit', 'Editing point') : t('delivery.point_form_title_new', 'New pickup point')}</div>
                                <PickupPointFields
                                    data={points[editingIndex]} setData={(field, value) => updatePoint(editingIndex, field, value)}
                                    errors={pointErrors(editingIndex)} languages={languages} countries={countries}
                                />
                            </div>
                        )}
                        <div style={{ marginTop: 10 }}>
                            <Button type="button" variant="outline" size="sm" icon={<Plus size={14} />} onClick={addPoint}>{t('delivery.point_add', 'Add pickup point')}</Button>
                        </div>
                    </div>
                )}

                {hasOtherErrors && <Alert tone="danger">{t('delivery.fix_errors', 'Fix the highlighted fields.')}</Alert>}
                <div style={{ position: 'sticky', bottom: 0, background: 'var(--bg-surface)', borderTop: '1px solid var(--border-subtle)', margin: '0 -20px -20px', padding: '12px 20px', display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <Button type="submit" loading={form.processing}>{t('delivery.save', 'Save')}</Button>
                    <Button type="button" variant="ghost" onClick={close}>{t('delivery.close', 'Close')}</Button>
                    {form.isDirty && <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('delivery.unsaved', 'Unsaved changes')}</span>}
                </div>
            </form>
        </Modal>
    );
}
