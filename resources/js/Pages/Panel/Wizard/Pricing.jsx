import Input from '@/Components/ui/Input';
import Radio from '@/Components/ui/Radio';
import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

export default function Pricing({ steps, skippable, priceEntryMode }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors } = useForm({ price_entry_mode: priceEntryMode, tax_rate_name: '', tax_rate_percentage: '' });

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="pricing"
            skippable={skippable}
            title={t('setup.pricing.title', 'Prices and taxes')}
            description={t('setup.pricing.description', 'How you enter prices in the catalog, and optionally your main tax rate. More rates can be added later in Settings.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store')); }}
            processing={processing}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                <Radio name="price_entry_mode" label={t('setup.pricing.gross', 'Prices include tax (gross)')} checked={data.price_entry_mode === 'gross'} onChange={() => setData('price_entry_mode', 'gross')} />
                <Radio name="price_entry_mode" label={t('setup.pricing.net', 'Prices exclude tax (net)')} checked={data.price_entry_mode === 'net'} onChange={() => setData('price_entry_mode', 'net')} />
                {errors.price_entry_mode && <div style={{ fontSize: 12, color: 'var(--danger-text)' }}>{errors.price_entry_mode}</div>}
            </div>
            <div style={{ display: 'flex', gap: 12 }}>
                <Input id="tax_rate_name" style={{ flex: 2 }} label={t('setup.pricing.tax_name_label', 'Tax rate name (optional)')} placeholder={t('setup.pricing.tax_name_example', 'e.g. Sales tax')} value={data.tax_rate_name} error={errors.tax_rate_name} onChange={(e) => setData('tax_rate_name', e.target.value)} />
                <Input id="tax_rate_percentage" style={{ flex: 1 }} label={t('setup.pricing.tax_percentage_label', 'Rate, %')} placeholder={t('setup.pricing.tax_percentage_example', 'e.g. 8')} type="number" min="0" max="100" step="0.01" value={data.tax_rate_percentage} error={errors.tax_rate_percentage} onChange={(e) => setData('tax_rate_percentage', e.target.value)} />
            </div>
        </SetupWizardShell>
    );
}
