import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Radio from '@/Components/ui/Radio';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { formatPercent } from '@/lib/format';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ settings, baseCurrency, currencies, taxRates, taxQualificationKinds, embedded = false }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;

    const settingsForm = useForm({ price_entry_mode: settings.price_entry_mode });
    const saveSettings = (event) => {
        event.preventDefault();
        settingsForm.put(route('panel.pricing.settings.update'), { preserveScroll: true });
    };

    const currencyForm = useForm({ code: '', name: '', symbol: '', decimal_places: 2 });
    const addCurrency = (event) => {
        event.preventDefault();
        currencyForm.post(route('panel.pricing.currencies.store'), {
            preserveScroll: true,
            onSuccess: () => currencyForm.reset(),
        });
    };

    const [rateDrafts, setRateDrafts] = useState(() => Object.fromEntries(currencies.filter((c) => !c.is_base).map((c) => [c.code, c.rate || ''])));
    const rateForm = useForm({ base_units_per_unit: '' });
    const saveRate = (code) => {
        rateForm.transform(() => ({ base_units_per_unit: rateDrafts[code] }));
        rateForm.put(route('panel.pricing.currencies.rate.update', code), { preserveScroll: true, preserveState: true });
    };

    const [editingTaxRateId, setEditingTaxRateId] = useState(null);
    const editingTaxRate = taxRates.find((rate) => rate.id === editingTaxRateId) || null;
    const taxRateForm = useForm({ name: '', kind: 'percentage', percentage: '' });
    const startEditTaxRate = (rate) => {
        setEditingTaxRateId(rate.id);
        taxRateForm.clearErrors();
        taxRateForm.setData({ name: rate.name, kind: rate.kind, percentage: rate.percentage || '' });
    };
    const cancelEditTaxRate = () => {
        setEditingTaxRateId(null);
        taxRateForm.clearErrors();
        taxRateForm.setData({ name: '', kind: 'percentage', percentage: '' });
    };
    const submitTaxRate = (event) => {
        event.preventDefault();
        if (editingTaxRateId) {
            taxRateForm.put(route('panel.pricing.tax-rates.update', editingTaxRateId), { preserveScroll: true, onSuccess: cancelEditTaxRate });
        } else {
            taxRateForm.post(route('panel.pricing.tax-rates.store'), { preserveScroll: true, onSuccess: cancelEditTaxRate });
        }
    };

    const kindLabel = (kind) => taxQualificationKinds.find((k) => k.value === kind)?.label || kind;

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('pricing.page_title', 'Pricing')} />}>
            <Head title={t('pricing.page_title', 'Pricing')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 800 }}>
                {status && <Alert tone="success">{status}</Alert>}

                <Card title={t('pricing.entry_mode_card_title', 'Price entry mode')}>
                    <form onSubmit={saveSettings} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div style={{ display: 'flex', gap: 24 }}>
                            <Radio
                                name="mode"
                                label={t('pricing.entry_mode_net', 'Net prices')}
                                checked={settingsForm.data.price_entry_mode === 'net'}
                                onChange={() => settingsForm.setData('price_entry_mode', 'net')}
                            />
                            <Radio
                                name="mode"
                                label={t('pricing.entry_mode_gross', 'Gross prices')}
                                checked={settingsForm.data.price_entry_mode === 'gross'}
                                onChange={() => settingsForm.setData('price_entry_mode', 'gross')}
                            />
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>
                            {t('pricing.entry_mode_hint', 'This is a store-wide setting — a price record keeps its source value and mode; changing this setting does not recalculate existing prices.')}
                        </div>
                        <div><Button type="submit" loading={settingsForm.processing}>{t('pricing.save', 'Save')}</Button></div>
                    </form>
                </Card>

                <Card title={t('pricing.currencies_card_title', 'Currencies and rates')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('pricing.base_currency_prefix', 'Base currency:')} <strong>{baseCurrency || t('pricing.not_set', 'not set')}</strong> {t('pricing.base_currency_suffix', '(rate is always 1, no separate row below).')}
                        </div>
                        <Table
                            columns={[
                                { header: t('pricing.column_currency', 'Currency'), render: (c) => `${c.code} — ${c.name}` },
                                {
                                    header: t('pricing.column_rate', 'Rate (base currency units per 1 unit)'),
                                    render: (c) => (
                                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                            <Input
                                                size="sm"
                                                value={rateDrafts[c.code] ?? ''}
                                                onChange={(e) => setRateDrafts((d) => ({ ...d, [c.code]: e.target.value }))}
                                                error={rateForm.errors.base_units_per_unit}
                                            />
                                            <Button type="button" size="sm" onClick={() => saveRate(c.code)} loading={rateForm.processing}>
                                                {t('pricing.save', 'Save')}
                                            </Button>
                                        </div>
                                    ),
                                },
                            ]}
                            rows={currencies.filter((c) => !c.is_base)}
                        />
                        {currencies.filter((c) => !c.is_base).length === 0 && (
                            <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('pricing.only_base_currency', 'The store currently only has the base currency.')}</div>
                        )}

                        <form onSubmit={addCurrency} style={{ display: 'flex', flexDirection: 'column', gap: 12, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                            <div style={{ fontSize: 14, fontWeight: 600 }}>{t('pricing.add_currency_title', 'Add currency')}</div>
                            <div className="grid grid-cols-2 sm:grid-cols-4" style={{ gap: 12 }}>
                                <Input
                                    label={t('pricing.fields.currency_code', 'Code (ISO 4217)')}
                                    placeholder="EUR"
                                    value={currencyForm.data.code}
                                    error={currencyForm.errors.code}
                                    onChange={(e) => currencyForm.setData('code', e.target.value.toUpperCase())}
                                />
                                <Input label={t('pricing.fields.name', 'Name')} value={currencyForm.data.name} error={currencyForm.errors.name} onChange={(e) => currencyForm.setData('name', e.target.value)} />
                                <Input label={t('pricing.fields.symbol', 'Symbol')} value={currencyForm.data.symbol} error={currencyForm.errors.symbol} onChange={(e) => currencyForm.setData('symbol', e.target.value)} />
                                <Input
                                    type="number"
                                    min="0"
                                    max="6"
                                    label={t('pricing.fields.decimal_places', 'Decimal places')}
                                    value={currencyForm.data.decimal_places}
                                    error={currencyForm.errors.decimal_places}
                                    onChange={(e) => currencyForm.setData('decimal_places', e.target.value)}
                                />
                            </div>
                            <div><Button type="submit" variant="outline" loading={currencyForm.processing}>{t('pricing.add_currency_title', 'Add currency')}</Button></div>
                        </form>
                    </div>
                </Card>

                <Card title={t('pricing.tax_rates_card_title', 'VAT rates')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('pricing.tax_rates_hint', 'A product with no assigned rate is treated as 0%. Rates are assigned to a product in the product editor; a variant inherits it.')}
                        </div>
                        <Table
                            columns={[
                                { header: t('pricing.fields.name', 'Name'), key: 'name' },
                                { header: t('pricing.column_qualification', 'Qualification'), render: (r) => kindLabel(r.kind) },
                                { header: t('pricing.column_tax_rate', 'Rate'), render: (r) => (r.kind === 'percentage' ? formatPercent(r.percentage) : '—') },
                                { header: '', align: 'right', render: (r) => <Button variant="outline" size="sm" onClick={() => startEditTaxRate(r)}>{t('pricing.edit', 'Edit')}</Button> },
                            ]}
                            rows={taxRates}
                        />
                        {taxRates.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('pricing.no_tax_rates_yet', 'No rate has been created yet.')}</div>}

                        <form onSubmit={submitTaxRate} style={{ display: 'flex', flexDirection: 'column', gap: 12, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                            <div style={{ fontSize: 14, fontWeight: 600 }}>{editingTaxRateId ? t('pricing.tax_rate_form_title_edit', 'Editing rate') : t('pricing.tax_rate_form_title_new', 'New rate')}</div>
                            <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 12 }}>
                                <Input label={t('pricing.fields.name', 'Name')} value={taxRateForm.data.name} error={taxRateForm.errors.name} onChange={(e) => taxRateForm.setData('name', e.target.value)} />
                                <Select
                                    label={t('pricing.fields.qualification', 'Qualification')}
                                    value={taxRateForm.data.kind}
                                    options={taxQualificationKinds}
                                    error={taxRateForm.errors.kind}
                                    onChange={(e) => taxRateForm.setData('kind', e.target.value)}
                                />
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    label={t('pricing.fields.rate_percentage', 'Rate (%)')}
                                    disabled={taxRateForm.data.kind !== 'percentage'}
                                    value={taxRateForm.data.percentage}
                                    error={taxRateForm.errors.percentage}
                                    onChange={(e) => taxRateForm.setData('percentage', e.target.value)}
                                />
                            </div>
                            <div style={{ display: 'flex', gap: 8 }}>
                                <Button type="submit" loading={taxRateForm.processing}>{editingTaxRateId ? t('pricing.save', 'Save') : t('pricing.add_tax_rate_submit', 'Add rate')}</Button>
                                {editingTaxRateId && <Button type="button" variant="ghost" onClick={cancelEditTaxRate}>{t('pricing.cancel', 'Cancel')}</Button>}
                            </div>
                        </form>
                    </div>
                </Card>
            </div>
        </SettingsPageFrame>
    );
}
