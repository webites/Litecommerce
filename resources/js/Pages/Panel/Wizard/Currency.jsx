import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/react';

const PRESETS = [
    { code: 'USD', symbol: '$' },
    { code: 'EUR', symbol: '€' },
    { code: 'GBP', symbol: '£' },
    { code: 'PLN', symbol: 'zł' },
];

export default function Currency({ steps }) {
    const t = useTranslate();
    const { locale } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({ code: '', name: '', symbol: '', decimal_places: 2 });
    const presets = locale === 'pl' ? [PRESETS[3], ...PRESETS.slice(0, 3)] : PRESETS;

    const choose = (preset) => setData({ ...data, code: preset.code, symbol: preset.symbol, name: t(`setup.currency.names.${preset.code}`, preset.code), decimal_places: 2 });

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="currency"
            title={t('setup.currency.title', 'Base currency')}
            description={t('setup.currency.description', 'The base currency has an exchange rate of 1, and changing it will be blocked after the first real order.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store')); }}
            processing={processing}
        >
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                {presets.map((preset) => (
                    <Button key={preset.code} type="button" size="sm" variant={data.code === preset.code ? 'solid' : 'outline'} onClick={() => choose(preset)}>
                        {preset.code} ({preset.symbol})
                    </Button>
                ))}
            </div>

            <Input id="code" label={t('setup.currency.code_label', 'Currency code')} placeholder={t('setup.currency.code_example', 'e.g. USD')} value={data.code} maxLength={3} required error={errors.code} onChange={(e) => setData('code', e.target.value)} />
            <Input id="name" label={t('setup.currency.name_label', 'Name')} placeholder={t('setup.currency.name_example', 'e.g. US Dollar')} value={data.name} required error={errors.name} onChange={(e) => setData('name', e.target.value)} />
            <Input id="symbol" label={t('setup.currency.symbol_label', 'Symbol')} placeholder={t('setup.currency.symbol_example', 'e.g. $')} value={data.symbol} required error={errors.symbol} onChange={(e) => setData('symbol', e.target.value)} />
            <Input id="decimal_places" label={t('setup.currency.decimal_places_label', 'Decimal places')} type="number" min="0" max="6" value={data.decimal_places} required error={errors.decimal_places} onChange={(e) => setData('decimal_places', e.target.value)} />
        </SetupWizardShell>
    );
}
