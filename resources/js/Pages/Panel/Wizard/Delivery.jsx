import CountryCombobox from '@/Components/ui/CountryCombobox';
import Input from '@/Components/ui/Input';
import Radio from '@/Components/ui/Radio';
import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

export default function Delivery({ steps, skippable, countries, baseCurrency, defaultCountry }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors } = useForm({
        kind: 'pickup', method_name: t('setup.delivery.pickup_name_default', 'Local pickup'), country_code: defaultCountry,
        point_name: '', postal_code: '', city: '', address_line_1: '', price_amount: '',
    });
    const isPickup = data.kind === 'pickup';
    const chooseKind = (kind) => setData((current) => ({
        ...current, kind,
        method_name: current.method_name === t('setup.delivery.pickup_name_default', 'Local pickup') || current.method_name === t('setup.delivery.courier_name_default', 'Courier')
            ? (kind === 'pickup' ? t('setup.delivery.pickup_name_default', 'Local pickup') : t('setup.delivery.courier_name_default', 'Courier'))
            : current.method_name,
    }));

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="delivery"
            skippable={skippable}
            title={t('setup.delivery.title', 'Delivery')}
            description={t('setup.delivery.description', 'Add your first delivery method so customers can check out with physical products. More methods, regions and pickup points can be added later in Settings.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store', { step: 'delivery' })); }}
            processing={processing}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                <Radio name="kind" label={t('setup.delivery.kind_pickup', 'Local pickup — customers collect the order from your shop')} checked={isPickup} onChange={() => chooseKind('pickup')} />
                <Radio name="kind" label={t('setup.delivery.kind_courier', 'Courier — you ship the order for a fixed price')} checked={!isPickup} onChange={() => chooseKind('courier')} />
                {errors.kind && <div style={{ fontSize: 12, color: 'var(--danger-text)' }}>{errors.kind}</div>}
            </div>
            <Input id="method_name" label={t('setup.delivery.method_name_label', 'Method name')} value={data.method_name} required error={errors.method_name} onChange={(e) => setData('method_name', e.target.value)} />
            <CountryCombobox
                label={isPickup ? t('setup.delivery.country_pickup_label', 'Country of the pickup point') : t('setup.delivery.country_courier_label', 'Deliver to country')}
                countries={countries} value={data.country_code} error={errors.country_code} onChange={(code) => setData('country_code', code)}
            />
            {isPickup ? (
                <>
                    <Input id="point_name" label={t('setup.delivery.point_name_label', 'Pickup point name')} placeholder={t('setup.delivery.point_name_example', 'e.g. Main shop')} value={data.point_name} required error={errors.point_name} onChange={(e) => setData('point_name', e.target.value)} />
                    <Input id="address_line_1" label={t('setup.delivery.address_label', 'Street and number')} value={data.address_line_1} required error={errors.address_line_1} onChange={(e) => setData('address_line_1', e.target.value)} />
                    <div style={{ display: 'flex', gap: 12 }}>
                        <Input id="postal_code" style={{ flex: 1, minWidth: 0 }} label={t('setup.delivery.postal_code_label', 'Postal code')} value={data.postal_code} required error={errors.postal_code} onChange={(e) => setData('postal_code', e.target.value)} />
                        <Input id="city" style={{ flex: 2, minWidth: 0 }} label={t('setup.delivery.city_label', 'City')} value={data.city} required error={errors.city} onChange={(e) => setData('city', e.target.value)} />
                    </div>
                </>
            ) : (
                <Input
                    id="price_amount" type="number" min="0" step="0.01" label={t('setup.delivery.price_label', 'Delivery price')} suffix={baseCurrency}
                    placeholder={t('setup.delivery.price_example', 'e.g. 15.00')} value={data.price_amount} required error={errors.price_amount}
                    helpText={t('setup.delivery.price_help', 'Charged for every order delivered to this country.')} onChange={(e) => setData('price_amount', e.target.value)}
                />
            )}
        </SetupWizardShell>
    );
}
