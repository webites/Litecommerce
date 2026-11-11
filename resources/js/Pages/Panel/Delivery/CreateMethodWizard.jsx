import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import Wizard from '@/Components/ui/Wizard';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import MethodPreview from './MethodPreview';
import PickupPointFields from './PickupPointFields';
import RegionRulesEditor from './RegionRulesEditor';
import { cleanInstructions, emptyPoint } from './helpers';

const REQUIRED_POINT_FIELDS = ['name', 'postal_code', 'city', 'address_line_1'];

export default function CreateMethodWizard({ taxRates, priceEntryMode, countries, languages, baseCurrency, onClose }) {
    const t = useTranslate();
    const [step, setStep] = useState(0);
    const form = useForm({
        kind: 'pickup', name: '', active: true, price_amount: '', free_threshold_amount: '', tax_rate_id: '',
        supports_cash_on_delivery: false, supports_pay_on_pickup: false, pickup_deadline_days: '',
        pickup_point: emptyPoint(countries), region_rules: [],
    });
    const isPickup = form.data.kind === 'pickup';
    const priceModeLabel = priceEntryMode === 'net' ? t('delivery.price_mode_net', 'net') : t('delivery.price_mode_gross', 'gross');
    const setPoint = (field, value) => form.setData('pickup_point', { ...form.data.pickup_point, [field]: value });
    const pointErrors = Object.fromEntries(Object.entries(form.errors).map(([key, value]) => [key.replace(/^pickup_point\./, ''), value]));
    const required = t('delivery.required_field', 'This field is required.');

    const kinds = [
        { value: 'pickup', title: t('delivery.kind_labels.pickup', 'Local pickup'), text: t('delivery.kind_help.pickup', 'Customers collect the order at your shop or another place you define.') },
        { value: 'flat_rate', title: t('delivery.kind_labels.flat_rate', 'Courier / own rate'), text: t('delivery.kind_help.flat_rate', 'You ship the order; the price depends on the destination country or continent.') },
    ];

    const validateDetails = () => {
        const problems = {};

        if (form.data.name.trim() === '') problems.name = required;

        if (isPickup) {
            REQUIRED_POINT_FIELDS.forEach((field) => {
                if (String(form.data.pickup_point[field] ?? '').trim() === '') problems[`pickup_point.${field}`] = required;
            });
        } else {
            const blank = (value) => String(value ?? '').trim() === '';

            if (form.data.region_rules.some((rule) => (rule.rule_type !== 'exclude' && blank(rule.price_amount)) || (rule.rule_type === 'continent_rate' ? blank(rule.continent_code) : blank(rule.country_code)))) {
                problems.region_rules = t('delivery.region_rule_incomplete', 'Every rule needs a country or continent, and every rate needs a price.');
            }
        }

        form.clearErrors();
        form.setError(problems);

        return Object.keys(problems).length === 0;
    };

    const next = () => {
        if (step === 1 && !validateDetails()) return;
        setStep(step + 1);
    };

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => (data.kind === 'pickup'
            ? { ...data, region_rules: undefined, pickup_point: { ...data.pickup_point, instructions: cleanInstructions(data.pickup_point.instructions) } }
            : { ...data, pickup_point: undefined }));
        form.post(route('panel.delivery.store'), { preserveScroll: true, onSuccess: onClose, onError: () => setStep(1) });
    };

    const summaryRows = [
        [t('delivery.column_kind', 'Kind'), kinds.find((kind) => kind.value === form.data.kind)?.title],
        [t('delivery.fields.method_name', 'Method name'), form.data.name],
        isPickup ? [t('delivery.first_point_title', 'First pickup point'), `${form.data.pickup_point.name}, ${form.data.pickup_point.address_line_1}, ${form.data.pickup_point.postal_code} ${form.data.pickup_point.city}`] : [t('delivery.regions_title', 'Regions'), t('delivery.summary_rules', ':count rule(s)', { count: form.data.region_rules.length })],
    ];

    return (
        <Modal show title={t('delivery.add_method', 'Add method')} onClose={onClose} maxWidth={760}>
            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                <Wizard steps={[t('delivery.wizard.step_kind', 'Kind'), t('delivery.wizard.step_details', 'Details'), t('delivery.wizard.step_summary', 'Summary')]} activeIndex={step}>
                    {step === 0 && (
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
                    )}

                    {step === 1 && (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
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

                            <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                                {isPickup ? (
                                    <>
                                        <div style={{ fontSize: 14, fontWeight: 600 }}>{t('delivery.first_point_title', 'First pickup point')}</div>
                                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', margin: '4px 0 12px' }}>{t('delivery.first_point_hint', 'A pickup method is visible to customers only when it has at least one active pickup point.')}</div>
                                        <PickupPointFields data={form.data.pickup_point} setData={setPoint} errors={pointErrors} languages={languages} countries={countries} />
                                    </>
                                ) : (
                                    <RegionRulesEditor rules={form.data.region_rules} onChange={(rules) => form.setData('region_rules', rules)} countries={countries} priceModeLabel={priceModeLabel} error={form.errors.region_rules} />
                                )}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <MethodPreview
                                kind={form.data.kind} name={form.data.name} price={form.data.price_amount} rules={form.data.region_rules}
                                points={isPickup ? [form.data.pickup_point] : []} payOnPickup={form.data.supports_pay_on_pickup} currency={baseCurrency}
                            />
                            <dl style={{ margin: 0, display: 'grid', gridTemplateColumns: 'minmax(120px, auto) 1fr', gap: '6px 16px', fontSize: 13 }}>
                                {summaryRows.map(([label, value]) => (
                                    <div key={label} style={{ display: 'contents' }}>
                                        <dt style={{ color: 'var(--text-tertiary)' }}>{label}</dt>
                                        <dd style={{ margin: 0 }}>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                            {Object.keys(form.errors).length > 0 && <Alert tone="danger">{t('delivery.fix_errors', 'Fix the highlighted fields.')}</Alert>}
                        </div>
                    )}
                </Wizard>

                <div style={{ display: 'flex', gap: 8, justifyContent: 'space-between', flexWrap: 'wrap' }}>
                    <Button type="button" variant="ghost" onClick={onClose}>{t('delivery.cancel', 'Cancel')}</Button>
                    <div style={{ display: 'flex', gap: 8 }}>
                        {step > 0 && <Button type="button" variant="outline" onClick={() => setStep(step - 1)}>{t('delivery.wizard.back', 'Back')}</Button>}
                        {step < 2
                            ? <Button key="next" type="button" onClick={next}>{t('delivery.wizard.next', 'Next')}</Button>
                            : <Button key="submit" type="submit" loading={form.processing}>{t('delivery.add_method', 'Add method')}</Button>}
                    </div>
                </div>
            </form>
        </Modal>
    );
}
