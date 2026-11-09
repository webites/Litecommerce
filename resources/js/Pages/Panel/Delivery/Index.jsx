import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import CountryCombobox from '@/Components/ui/CountryCombobox';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Switch from '@/Components/ui/Switch';
import Table from '@/Components/ui/Table';
import Textarea from '@/Components/ui/Textarea';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';

function summary(method, t) {
    if (method.kind === 'pickup') {
        return method.price_amount
            ? t('delivery.summary_pickup_priced', ':amount (base)', { amount: method.price_amount })
            : t('delivery.summary_pickup_free', 'Free');
    }

    const count = method.region_rules.length;
    const word = count === 1
        ? t('delivery.summary_rule_singular', 'region rule')
        : t('delivery.summary_rule_plural', 'region rules');

    return `${count} ${word}`;
}

const emptyPoint = (countries) => ({
    name: '', country_code: countries.some((c) => c.code === 'PL') ? 'PL' : '', postal_code: '', city: '', region: '',
    address_line_1: '', address_line_2: '', instructions: {}, active: true,
});

function hasActivePoint(method) {
    return method.pickup_points.some((point) => point.active);
}

function cleanInstructions(instructions) {
    return Object.fromEntries(Object.entries(instructions ?? {}).filter(([, value]) => typeof value === 'string' && value.trim() !== ''));
}

function PickupPointFields({ data, setData, errors, prefix = '', languages, countries }) {
    const t = useTranslate();
    const set = (field, value) => setData(field, value);
    const error = (field) => errors[`${prefix}${field}`];
    const multiLanguage = languages.length > 1;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                <Input
                    required label={t('delivery.fields.point_name', 'Pickup point name')} value={data.name} error={error('name')}
                    placeholder={t('delivery.fields.point_name_placeholder', 'e.g. Main store — Wrocław')}
                    helpText={t('delivery.fields.point_name_help', 'Customers see it at checkout next to the method name.')}
                    onChange={(e) => set('name', e.target.value)}
                />
                <CountryCombobox label={t('delivery.fields.country', 'Country')} countries={countries} value={data.country_code} error={error('country_code')} onChange={(code) => set('country_code', code)} />
                <Input required label={t('delivery.fields.postal_code', 'Postal code')} value={data.postal_code} error={error('postal_code')} onChange={(e) => set('postal_code', e.target.value)} />
                <Input required label={t('delivery.fields.city', 'City')} value={data.city} error={error('city')} onChange={(e) => set('city', e.target.value)} />
                <Input required label={t('delivery.fields.address_line_1', 'Address')} placeholder={t('delivery.fields.address_placeholder', 'Street and number')} value={data.address_line_1} error={error('address_line_1')} onChange={(e) => set('address_line_1', e.target.value)} />
                <Input label={t('delivery.fields.address_line_2', 'Address (cont.)')} value={data.address_line_2} error={error('address_line_2')} onChange={(e) => set('address_line_2', e.target.value)} />
            </div>
            {languages.map((language) => (
                <Textarea
                    key={language.code} rows={3}
                    label={multiLanguage
                        ? t('delivery.fields.instructions_lang', 'Pickup instructions (:language)', { language: language.name })
                        : t('delivery.fields.instructions', 'Pickup instructions')}
                    placeholder={t('delivery.fields.instructions_placeholder', 'e.g. Mon–Fri 10:00–18:00, ring the bell at the side entrance')}
                    helpText={t('delivery.fields.instructions_help', 'Opening hours and how to collect. Shown at checkout and in the “ready for pickup” e-mail.')}
                    value={data.instructions?.[language.code] ?? ''}
                    error={error(`instructions.${language.code}`)}
                    onChange={(value) => set('instructions', { ...data.instructions, [language.code]: value })}
                />
            ))}
        </div>
    );
}

function CreateMethodModal({ taxRates, priceEntryMode, countries, languages, onClose }) {
    const t = useTranslate();
    const form = useForm({
        kind: 'pickup', name: '', active: true, price_amount: '', free_threshold_amount: '', tax_rate_id: '',
        supports_cash_on_delivery: false, supports_pay_on_pickup: false, pickup_deadline_days: '',
        pickup_point: emptyPoint(countries),
    });
    const isPickup = form.data.kind === 'pickup';
    const priceModeLabel = priceEntryMode === 'net' ? t('delivery.price_mode_net', 'net') : t('delivery.price_mode_gross', 'gross');
    const setPoint = (field, value) => form.setData('pickup_point', { ...form.data.pickup_point, [field]: value });
    const pointErrors = Object.fromEntries(Object.entries(form.errors).map(([key, value]) => [key.replace(/^pickup_point\./, ''), value]));

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            pickup_point: data.kind === 'pickup' ? { ...data.pickup_point, instructions: cleanInstructions(data.pickup_point.instructions) } : undefined,
        }));
        form.post(route('panel.delivery.store'), { preserveScroll: true, onSuccess: onClose });
    };

    const kinds = [
        { value: 'pickup', title: t('delivery.kind_labels.pickup', 'Local pickup'), text: t('delivery.kind_help.pickup', 'Customers collect the order at your shop or another place you define.') },
        { value: 'flat_rate', title: t('delivery.kind_labels.flat_rate', 'Courier / own rate'), text: t('delivery.kind_help.flat_rate', 'You ship the order; the price depends on the destination country or continent.') },
    ];

    return (
        <Modal show title={t('delivery.add_method', 'Add method')} onClose={onClose} maxWidth={760}>
            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                    {kinds.map((kind) => {
                        const selected = form.data.kind === kind.value;

                        return (
                            <button
                                key={kind.value} type="button" aria-pressed={selected} onClick={() => form.setData('kind', kind.value)}
                                style={{
                                    textAlign: 'left', padding: 14, cursor: 'pointer', borderRadius: 'var(--radius-card)', font: 'inherit',
                                    border: `2px solid ${selected ? 'var(--brand-solid)' : 'var(--border-subtle)'}`,
                                    background: selected ? 'var(--brand-subtle-bg)' : 'var(--bg-surface)', color: 'var(--text-primary)',
                                }}
                            >
                                <div style={{ fontWeight: 600, fontSize: 14 }}>{kind.title}</div>
                                <div style={{ fontSize: 12, color: 'var(--text-secondary)', marginTop: 4 }}>{kind.text}</div>
                            </button>
                        );
                    })}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                    <Input
                        required label={t('delivery.fields.method_name', 'Method name')} value={form.data.name} error={form.errors.name}
                        placeholder={isPickup ? t('delivery.fields.method_name_placeholder_pickup', 'e.g. Local pickup') : t('delivery.fields.method_name_placeholder_courier', 'e.g. Courier')}
                        helpText={t('delivery.fields.method_name_help', 'What customers see at checkout.')}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                    <Select
                        label={t('delivery.fields.tax_rate', 'VAT rate')} placeholder={t('delivery.fields.tax_rate_placeholder', 'Not assigned (0%)')} value={form.data.tax_rate_id} error={form.errors.tax_rate_id}
                        options={taxRates.map((r) => ({ value: r.id, label: r.name }))}
                        onChange={(e) => form.setData('tax_rate_id', e.target.value)}
                    />
                    {isPickup ? (
                        <>
                            <Input
                                label={t('delivery.fields.price_with_mode', 'Price (:mode)', { mode: priceModeLabel })} placeholder={t('delivery.fields.price_placeholder_free', '0 = free')}
                                value={form.data.price_amount} error={form.errors.price_amount}
                                onChange={(e) => form.setData('price_amount', e.target.value)}
                            />
                            <Input
                                type="number" min="1" max="365" label={t('delivery.fields.pickup_deadline_days', 'Pickup deadline (days)')} placeholder={t('delivery.fields.pickup_deadline_placeholder', 'No deadline')}
                                helpText={t('delivery.fields.pickup_deadline_help', 'After this time the order is treated as not collected.')}
                                value={form.data.pickup_deadline_days} error={form.errors.pickup_deadline_days}
                                onChange={(e) => form.setData('pickup_deadline_days', e.target.value)}
                            />
                        </>
                    ) : (
                        <Input
                            label={t('delivery.fields.free_threshold', 'Free shipping threshold')} placeholder={t('delivery.fields.free_threshold_placeholder_none', 'No threshold')}
                            helpText={t('delivery.fields.free_threshold_help', 'Orders above this amount ship for free.')}
                            value={form.data.free_threshold_amount} error={form.errors.free_threshold_amount}
                            onChange={(e) => form.setData('free_threshold_amount', e.target.value)}
                        />
                    )}
                </div>

                {isPickup ? (
                    <Checkbox label={t('delivery.fields.supports_pay_on_pickup', 'Customer can pay on pickup')} checked={form.data.supports_pay_on_pickup} onChange={(v) => form.setData('supports_pay_on_pickup', v)} />
                ) : (
                    <Checkbox label={t('delivery.fields.supports_cod', 'Accepts cash on delivery (COD)')} checked={form.data.supports_cash_on_delivery} onChange={(v) => form.setData('supports_cash_on_delivery', v)} />
                )}

                {isPickup && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600 }}>{t('delivery.first_point_title', 'First pickup point')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', margin: '4px 0 12px' }}>{t('delivery.first_point_hint', 'A pickup method is visible to customers only when it has at least one active pickup point.')}</div>
                        <PickupPointFields data={form.data.pickup_point} setData={setPoint} errors={pointErrors} languages={languages} countries={countries} />
                    </div>
                )}

                <div style={{ display: 'flex', gap: 8 }}>
                    <Button type="submit" loading={form.processing}>{t('delivery.add_method', 'Add method')}</Button>
                    <Button type="button" variant="ghost" onClick={onClose}>{t('delivery.cancel', 'Cancel')}</Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ methods, currencies, tax_rates: taxRates, price_entry_mode: priceEntryMode, countries, languages = [], plugin_methods: pluginMethods = [], embedded = false }) {
    const t = useTranslate();
    const { flash, errors } = usePage().props;
    const status = flash?.status;
    const [editingId, setEditingId] = useState(null);
    const [creating, setCreating] = useState(false);
    const editing = methods.find((m) => m.id === editingId) || null;

    const kindLabels = {
        flat_rate: t('delivery.kind_labels.flat_rate', 'Courier / own rate'),
        pickup: t('delivery.kind_labels.pickup', 'Local pickup'),
    };

    const toggleActive = (method) => {
        router.put(route('panel.delivery.update', method.id), { ...method, active: !method.active, tax_rate_id: method.tax_rate_id ?? '' }, { preserveScroll: true });
    };

    const removeMethod = (method) => {
        if (confirm(t('delivery.confirm_delete_method', 'Delete method ":name"?', { name: method.name }))) {
            router.delete(route('panel.delivery.destroy', method.id), { preserveScroll: true });
        }
    };

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('delivery.page_title', 'Delivery')} description={t('delivery.page_description', 'Delivery methods, regions and pickup points.')} />}>
            <Head title={t('delivery.page_title', 'Delivery')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 800 }}>
                {status && <Alert tone="success">{status}</Alert>}
                {errors?.active && <Alert tone="warning">{errors.active}</Alert>}

                <Card
                    title={t('delivery.methods_card_title', 'Delivery methods')}
                    description={t('delivery.methods_card_description', "Regional prices and free-shipping thresholds are calculated in the store's base currency, with optional per-currency overrides in the method editor.")}
                    action={<Button variant="outline" size="sm" icon={<Plus size={14} />} onClick={() => setCreating(true)}>{t('delivery.add_method', 'Add method')}</Button>}
                >
                    {methods.length === 0 ? (
                        <EmptyState
                            icon={Truck} title={t('delivery.empty_title', 'No delivery methods')}
                            description={t('delivery.empty_description', 'Without a delivery method customers cannot check out physical products. Add local pickup or a courier method.')}
                            action={<Button size="sm" icon={<Plus size={14} />} onClick={() => setCreating(true)}>{t('delivery.add_method', 'Add method')}</Button>}
                        />
                    ) : (
                        <Table
                            columns={[
                                {
                                    header: t('delivery.column_name', 'Name'),
                                    render: (m) => (
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: 4, alignItems: 'flex-start' }}>
                                            <strong>{m.name}</strong>
                                            {m.kind === 'pickup' && !hasActivePoint(m) && <Badge tone="warning">{t('delivery.hidden_no_point', 'Hidden from customers: add a pickup point')}</Badge>}
                                        </div>
                                    ),
                                },
                                { header: t('delivery.column_kind', 'Kind'), render: (m) => <Badge tone="brand">{kindLabels[m.kind] ?? m.kind}</Badge> },
                                { header: t('delivery.column_price_regions', 'Price / regions'), render: (m) => summary(m, t) },
                                { header: t('delivery.column_active_method', 'Active'), render: (m) => <Switch checked={m.active} onChange={() => toggleActive(m)} /> },
                                {
                                    header: '', align: 'right',
                                    render: (m) => (
                                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                                            <Button variant="outline" size="sm" onClick={() => setEditingId(m.id)}>{t('delivery.edit', 'Edit')}</Button>
                                            <Button variant="ghost" size="sm" onClick={() => removeMethod(m)}><Trash2 size={14} /></Button>
                                        </div>
                                    ),
                                },
                            ]}
                            rows={methods}
                        />
                    )}
                </Card>

                {pluginMethods.length > 0 && (
                    <Card
                        title={t('delivery.plugin_methods_title', 'Shipping methods from extensions')}
                        description={t('delivery.plugin_methods_description', 'Registered by an installed plugin. The method becomes selectable at checkout only after you enable it.')}
                    >
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {pluginMethods.map((method) => (
                                <PluginMethodSettings key={method.id} method={method} taxRates={taxRates} />
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            {creating && (
                <CreateMethodModal taxRates={taxRates} priceEntryMode={priceEntryMode} countries={countries} languages={languages} onClose={() => setCreating(false)} />
            )}

            {editing && (
                <DeliveryMethodModal
                    method={editing} currencies={currencies} taxRates={taxRates} priceEntryMode={priceEntryMode} countries={countries} languages={languages}
                    onClose={() => setEditingId(null)}
                />
            )}
        </SettingsPageFrame>
    );
}

function PluginMethodSettings({ method, taxRates }) {
    const t = useTranslate();
    const form = useForm({
        active: method.active, tax_rate_id: method.tax_rate_id ?? '',
        allow_cash_on_delivery: method.allow_cash_on_delivery, allow_pay_on_pickup: method.allow_pay_on_pickup,
        pickup_deadline_days: method.pickup_deadline_days ?? '',
    });
    const submit = (event) => {
        event.preventDefault();
        form.put(route('panel.delivery.plugin-methods.update', method.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} data-plugin-method={method.id} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                <div>
                    <strong>{method.name}</strong>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{t('delivery.plugin_methods_provider', 'Plugin: :provider', { provider: method.provider_id })}</div>
                </div>
                <Switch checked={form.data.active} onChange={(value) => form.setData('active', value)} label={t('delivery.plugin_methods_active', 'Enabled')} />
            </div>
            <Select
                label={t('delivery.fields.tax_rate', 'VAT rate')} placeholder={t('delivery.fields.tax_rate_placeholder', 'Not assigned (0%)')}
                value={form.data.tax_rate_id} error={form.errors.tax_rate_id}
                options={taxRates.map((r) => ({ value: r.id, label: r.name }))}
                onChange={(e) => form.setData('tax_rate_id', e.target.value)}
            />
            {method.kind === 'pickup' && (
                <Input
                    type="number" min="1" max="365" label={t('delivery.fields.pickup_deadline_days', 'Pickup deadline (days)')} placeholder={t('delivery.fields.pickup_deadline_placeholder', 'No deadline')}
                    value={form.data.pickup_deadline_days} error={form.errors.pickup_deadline_days}
                    onChange={(e) => form.setData('pickup_deadline_days', e.target.value)}
                />
            )}
            <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                {method.can_cash_on_delivery && (
                    <Checkbox label={t('delivery.fields.supports_cod', 'Accepts cash on delivery (COD)')} checked={form.data.allow_cash_on_delivery} onChange={(v) => form.setData('allow_cash_on_delivery', v)} />
                )}
                {method.can_pay_on_pickup && (
                    <Checkbox label={t('delivery.fields.supports_pay_on_pickup', 'Accepts pay on pickup')} checked={form.data.allow_pay_on_pickup} onChange={(v) => form.setData('allow_pay_on_pickup', v)} />
                )}
            </div>
            <div><Button type="submit" variant="outline" loading={form.processing}>{t('delivery.plugin_methods_save', 'Save')}</Button></div>
        </form>
    );
}

function DeliveryMethodModal({ method, currencies, taxRates, priceEntryMode, countries, languages, onClose }) {
    const t = useTranslate();
    const nonBaseCurrencies = currencies.filter((c) => !c.is_base);

    const ruleTypeOptions = [
        { value: 'exclude', label: t('delivery.rule_type_options.exclude', 'Exclude country') },
        { value: 'country_rate', label: t('delivery.rule_type_options.country_rate', 'Country rate') },
        { value: 'continent_rate', label: t('delivery.rule_type_options.continent_rate', 'Continent rate') },
    ];
    const continentOptions = [
        { value: 'EU', label: t('delivery.continents.EU', 'Europe') },
        { value: 'AS', label: t('delivery.continents.AS', 'Asia') },
        { value: 'AF', label: t('delivery.continents.AF', 'Africa') },
        { value: 'NA', label: t('delivery.continents.NA', 'North America') },
        { value: 'SA', label: t('delivery.continents.SA', 'South America') },
        { value: 'OC', label: t('delivery.continents.OC', 'Oceania') },
        { value: 'AN', label: t('delivery.continents.AN', 'Antarctica') },
    ];

    const priceModeLabel = priceEntryMode === 'net' ? t('delivery.price_mode_net', 'net') : t('delivery.price_mode_gross', 'gross');

    const basicsForm = useForm({
        kind: method.kind, name: method.name, active: method.active,
        price_amount: method.price_amount ?? '', free_threshold_amount: method.free_threshold_amount ?? '',
        tax_rate_id: method.tax_rate_id ?? '', supports_cash_on_delivery: method.supports_cash_on_delivery,
        supports_pay_on_pickup: method.supports_pay_on_pickup, pickup_deadline_days: method.pickup_deadline_days ?? '',
    });
    const saveBasics = (event) => {
        event.preventDefault();
        basicsForm.put(route('panel.delivery.update', method.id), { preserveScroll: true });
    };

    const [overrideDrafts, setOverrideDrafts] = useState(() => Object.fromEntries(nonBaseCurrencies.map((c) => {
        const existing = method.currency_overrides.find((o) => o.currency_code === c.code);

        return [c.code, { price_amount: existing?.price_amount ?? '', free_threshold_amount: existing?.free_threshold_amount ?? '' }];
    })));
    const overrideForm = useForm({ price_amount: '', free_threshold_amount: '' });
    const saveOverride = (code) => {
        overrideForm.transform(() => overrideDrafts[code]);
        overrideForm.put(route('panel.delivery.currencies.update', [method.id, code]), { preserveScroll: true, preserveState: true });
    };

    const [rules, setRules] = useState(() => method.region_rules.map((r) => ({ ...r, currency_overrides: r.currency_overrides ?? [] })));
    const regionForm = useForm({ rules: [] });
    const addRule = () => setRules((r) => [...r, { rule_type: 'country_rate', country_code: '', continent_code: '', price_amount: '', currency_overrides: [] }]);
    const updateRule = (index, field, value) => setRules((r) => r.map((rule, i) => (i === index ? { ...rule, [field]: value } : rule)));
    const removeRule = (index) => setRules((r) => r.filter((_, i) => i !== index));
    const saveRules = () => {
        regionForm.transform(() => ({ rules }));
        regionForm.put(route('panel.delivery.region-rules.update', method.id), { preserveScroll: true, preserveState: true });
    };

    const pointForm = useForm(emptyPoint(countries));
    const [editingPointId, setEditingPointId] = useState(null);
    const startEditPoint = (point) => {
        setEditingPointId(point.id);
        pointForm.clearErrors();
        pointForm.setData({
            name: point.name, country_code: point.country_code, postal_code: point.postal_code, city: point.city,
            region: point.region ?? '', address_line_1: point.address_line_1, address_line_2: point.address_line_2 ?? '', active: point.active,
            instructions: Array.isArray(point.instructions) ? {} : (point.instructions ?? {}),
        });
    };
    const cancelEditPoint = () => {
        setEditingPointId(null);
        pointForm.setData(emptyPoint(countries));
        pointForm.clearErrors();
    };
    const submitPoint = (event) => {
        event.preventDefault();
        pointForm.transform((data) => ({ ...data, instructions: cleanInstructions(data.instructions) }));
        if (editingPointId) {
            pointForm.put(route('panel.delivery.pickup-points.update', [method.id, editingPointId]), { preserveScroll: true, onSuccess: cancelEditPoint });
        } else {
            pointForm.post(route('panel.delivery.pickup-points.store', method.id), { preserveScroll: true, onSuccess: cancelEditPoint });
        }
    };
    const removePoint = (point) => {
        if (confirm(t('delivery.confirm_delete_point', 'Delete pickup point ":name"?', { name: point.name }))) {
            router.delete(route('panel.delivery.pickup-points.destroy', [method.id, point.id]), { preserveScroll: true });
        }
    };

    return (
        <Modal show title={t('delivery.modal_edit_title', 'Editing: :name', { name: method.name })} onClose={onClose} maxWidth={760}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                {method.kind === 'pickup' && !hasActivePoint(method) && (
                    <Alert tone="warning" title={t('delivery.no_point_title', 'Customers cannot see this method yet')}>
                        {t('delivery.no_point_text', 'A pickup method appears at checkout only when it has at least one active pickup point. Add one in the “Pickup points” section below.')}
                    </Alert>
                )}
                <form onSubmit={saveBasics} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                        <Input required label={t('delivery.fields.method_name', 'Method name')} helpText={t('delivery.fields.method_name_help', 'What customers see at checkout.')} value={basicsForm.data.name} error={basicsForm.errors.name} onChange={(e) => basicsForm.setData('name', e.target.value)} />
                        <Select
                            label={t('delivery.fields.tax_rate', 'VAT rate')} placeholder={t('delivery.fields.tax_rate_placeholder', 'Not assigned (0%)')} value={basicsForm.data.tax_rate_id} error={basicsForm.errors.tax_rate_id}
                            options={taxRates.map((r) => ({ value: r.id, label: r.name }))}
                            onChange={(e) => basicsForm.setData('tax_rate_id', e.target.value)}
                        />
                        {method.kind === 'pickup' && (
                            <Input
                                label={t('delivery.fields.price_with_mode', 'Price (:mode)', { mode: priceModeLabel })} placeholder={t('delivery.fields.price_placeholder_free', '0 = free')}
                                value={basicsForm.data.price_amount} error={basicsForm.errors.price_amount}
                                onChange={(e) => basicsForm.setData('price_amount', e.target.value)}
                            />
                        )}
                        {method.kind === 'pickup' && (
                            <Input
                                type="number" min="1" max="365" label={t('delivery.fields.pickup_deadline_days', 'Pickup deadline (days)')} placeholder={t('delivery.fields.pickup_deadline_placeholder', 'No deadline')}
                                value={basicsForm.data.pickup_deadline_days} error={basicsForm.errors.pickup_deadline_days}
                                onChange={(e) => basicsForm.setData('pickup_deadline_days', e.target.value)}
                            />
                        )}
                        {method.kind === 'flat_rate' && (
                            <Input
                                label={t('delivery.fields.free_threshold', 'Free shipping threshold')} placeholder={t('delivery.fields.free_threshold_placeholder_none', 'No threshold')}
                                helpText={t('delivery.fields.free_threshold_help', 'Orders above this amount ship for free.')}
                                value={basicsForm.data.free_threshold_amount} error={basicsForm.errors.free_threshold_amount}
                                onChange={(e) => basicsForm.setData('free_threshold_amount', e.target.value)}
                            />
                        )}
                    </div>
                    <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                        <Switch label={t('delivery.column_active_method', 'Active')} checked={basicsForm.data.active} onChange={(v) => basicsForm.setData('active', v)} />
                        {method.kind === 'flat_rate' && (
                            <Checkbox label={t('delivery.fields.supports_cod', 'Accepts cash on delivery (COD)')} checked={basicsForm.data.supports_cash_on_delivery} onChange={(v) => basicsForm.setData('supports_cash_on_delivery', v)} />
                        )}
                        {method.kind === 'pickup' && (
                            <Checkbox label={t('delivery.fields.supports_pay_on_pickup', 'Customer can pay on pickup')} checked={basicsForm.data.supports_pay_on_pickup} onChange={(v) => basicsForm.setData('supports_pay_on_pickup', v)} />
                        )}
                    </div>
                    {basicsForm.errors.active && <Alert tone="warning">{basicsForm.errors.active}</Alert>}
                    <div><Button type="submit" loading={basicsForm.processing}>{t('delivery.save', 'Save')}</Button></div>
                </form>

                {nonBaseCurrencies.length > 0 && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 10 }}>{t('delivery.currency_overrides_title', 'Currency overrides')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 10 }}>{t('delivery.currency_overrides_hint', 'An empty field means conversion from the base currency at the current rate.')}</div>
                        {nonBaseCurrencies.map((c) => (
                            <div key={c.code} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', marginBottom: 8 }}>
                                <div style={{ width: 56, fontSize: 13, paddingBottom: 9 }}>{c.code}</div>
                                {method.kind === 'pickup' && (
                                    <Input
                                        size="sm" label={t('delivery.fields.price', 'Price')} placeholder="—"
                                        value={overrideDrafts[c.code]?.price_amount ?? ''}
                                        onChange={(e) => setOverrideDrafts((d) => ({ ...d, [c.code]: { ...d[c.code], price_amount: e.target.value } }))}
                                    />
                                )}
                                <Input
                                    size="sm" label={t('delivery.fields.threshold_short', 'Threshold')} placeholder="—"
                                    value={overrideDrafts[c.code]?.free_threshold_amount ?? ''}
                                    onChange={(e) => setOverrideDrafts((d) => ({ ...d, [c.code]: { ...d[c.code], free_threshold_amount: e.target.value } }))}
                                />
                                <Button type="button" size="sm" variant="outline" onClick={() => saveOverride(c.code)} loading={overrideForm.processing}>{t('delivery.save', 'Save')}</Button>
                            </div>
                        ))}
                    </div>
                )}

                {method.kind === 'flat_rate' && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 10 }}>{t('delivery.regions_title', 'Regions')}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 10 }}>
                            {t('delivery.regions_hint', 'Matching order: country exclusion → country rate → continent rate. No match means the method is unavailable for that address.')}
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {rules.map((rule, index) => (
                                <div key={index} className="grid grid-cols-1 sm:grid-cols-4" style={{ gap: 8, alignItems: 'flex-end' }}>
                                    <Select
                                        size="sm" label={t('delivery.fields.rule_type', 'Rule')} value={rule.rule_type} options={ruleTypeOptions}
                                        onChange={(e) => updateRule(index, 'rule_type', e.target.value)}
                                    />
                                    {rule.rule_type === 'continent_rate' ? (
                                        <Select
                                            label={t('delivery.fields.continent', 'Continent')} placeholder={t('delivery.fields.select_placeholder', 'Select')} value={rule.continent_code ?? ''} options={continentOptions}
                                            onChange={(e) => updateRule(index, 'continent_code', e.target.value)}
                                        />
                                    ) : (
                                        <CountryCombobox
                                            label={t('delivery.fields.country', 'Country')} countries={countries} value={rule.country_code ?? ''}
                                            onChange={(code) => updateRule(index, 'country_code', code)}
                                        />
                                    )}
                                    {rule.rule_type !== 'exclude' ? (
                                        <Input
                                            label={t('delivery.fields.price_with_mode', 'Price (:mode)', { mode: priceModeLabel })} value={rule.price_amount ?? ''}
                                            onChange={(e) => updateRule(index, 'price_amount', e.target.value)}
                                        />
                                    ) : <div />}
                                    <Button type="button" variant="ghost" onClick={() => removeRule(index)}><Trash2 size={14} /></Button>
                                </div>
                            ))}
                        </div>
                        <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
                            <Button type="button" variant="outline" size="sm" icon={<Plus size={14} />} onClick={addRule}>{t('delivery.add_rule', 'Add rule')}</Button>
                            <Button type="button" size="sm" onClick={saveRules} loading={regionForm.processing}>{t('delivery.save_regions', 'Save regions')}</Button>
                        </div>
                    </div>
                )}

                {method.kind === 'pickup' && (
                    <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 10 }}>{t('delivery.pickup_points_title', 'Pickup points')}</div>
                        {method.pickup_points.length === 0 && (
                            <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginBottom: 12 }}>{t('delivery.no_points_yet', 'No pickup points yet. Fill in the form below to add the first one.')}</div>
                        )}
                        {method.pickup_points.length > 0 && (
                            <Table
                                columns={[
                                    { header: t('delivery.fields.point_name', 'Pickup point name'), key: 'name' },
                                    { header: t('delivery.column_address', 'Address'), render: (p) => `${p.address_line_1}, ${p.postal_code} ${p.city}` },
                                    { header: t('delivery.column_active_point', 'Active'), render: (p) => (p.active ? <Badge tone="success">{t('delivery.yes', 'Yes')}</Badge> : <Badge>{t('delivery.no', 'No')}</Badge>) },
                                    {
                                        header: '', align: 'right',
                                        render: (p) => (
                                            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                                                <Button variant="outline" size="sm" onClick={() => startEditPoint(p)}>{t('delivery.edit', 'Edit')}</Button>
                                                <Button variant="ghost" size="sm" onClick={() => removePoint(p)}><Trash2 size={14} /></Button>
                                            </div>
                                        ),
                                    },
                                ]}
                                rows={method.pickup_points}
                            />
                        )}
                        <form onSubmit={submitPoint} style={{ display: 'flex', flexDirection: 'column', gap: 12, marginTop: 12 }}>
                            <div style={{ fontSize: 13, fontWeight: 600 }}>{editingPointId ? t('delivery.point_form_title_edit', 'Editing point') : t('delivery.point_form_title_new', 'New pickup point')}</div>
                            <PickupPointFields data={pointForm.data} setData={pointForm.setData} errors={pointForm.errors} languages={languages} countries={countries} />
                            <Switch label={t('delivery.column_active_point', 'Active')} checked={pointForm.data.active} onChange={(v) => pointForm.setData('active', v)} />
                            <div style={{ display: 'flex', gap: 8 }}>
                                <Button type="submit" loading={pointForm.processing}>{editingPointId ? t('delivery.point_save', 'Save point') : t('delivery.point_add', 'Add point')}</Button>
                                {editingPointId && <Button type="button" variant="ghost" onClick={cancelEditPoint}>{t('delivery.cancel', 'Cancel')}</Button>}
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </Modal>
    );
}
