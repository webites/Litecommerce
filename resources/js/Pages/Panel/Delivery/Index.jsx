import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Switch from '@/Components/ui/Switch';
import Table from '@/Components/ui/Table';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';
import CreateMethodWizard from './CreateMethodWizard';
import EditMethodModal from './EditMethodModal';
import { basicFields, hasActivePoint, hasRate, priceSummary } from './helpers';

export default function Index({ methods, currencies, tax_rates: taxRates, price_entry_mode: priceEntryMode, countries, languages = [], plugin_methods: pluginMethods = [], embedded = false }) {
    const t = useTranslate();
    const { flash, errors } = usePage().props;
    const status = flash?.status;
    const [editingId, setEditingId] = useState(null);
    const [creating, setCreating] = useState(false);
    const editing = methods.find((m) => m.id === editingId) || null;
    const baseCurrency = currencies.find((c) => c.is_base)?.code ?? '';

    const kindLabels = {
        flat_rate: t('delivery.kind_labels.flat_rate', 'Courier / own rate'),
        pickup: t('delivery.kind_labels.pickup', 'Local pickup'),
    };

    const toggleActive = (method) => {
        router.put(route('panel.delivery.update', method.id), { ...basicFields(method), active: !method.active }, { preserveScroll: true });
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
                                            {m.kind === 'pickup' && !hasActivePoint(m.pickup_points) && <Badge tone="warning">{t('delivery.hidden_no_point', 'Hidden from customers: add a pickup point')}</Badge>}
                                            {m.kind === 'flat_rate' && !hasRate(m.region_rules) && <Badge tone="warning">{t('delivery.hidden_no_rate', 'Hidden from customers: add a rate')}</Badge>}
                                        </div>
                                    ),
                                },
                                { header: t('delivery.column_kind', 'Kind'), render: (m) => <Badge tone="brand">{kindLabels[m.kind] ?? m.kind}</Badge> },
                                { header: t('delivery.column_price', 'Price'), render: (m) => priceSummary({ kind: m.kind, price_amount: m.price_amount, rules: m.region_rules }, t, baseCurrency) },
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
                            cards
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
                <CreateMethodWizard taxRates={taxRates} priceEntryMode={priceEntryMode} countries={countries} languages={languages} baseCurrency={baseCurrency} onClose={() => setCreating(false)} />
            )}

            {editing && (
                <EditMethodModal
                    key={editing.id} method={editing} currencies={currencies} taxRates={taxRates} priceEntryMode={priceEntryMode} countries={countries} languages={languages}
                    baseCurrency={baseCurrency} onClose={() => setEditingId(null)}
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
