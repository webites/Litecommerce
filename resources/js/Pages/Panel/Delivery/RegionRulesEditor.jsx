import Button from '@/Components/ui/Button';
import CountryCombobox from '@/Components/ui/CountryCombobox';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import { useTranslate } from '@/lib/i18n';
import { Plus, Trash2 } from 'lucide-react';
import { emptyRule } from './helpers';

const continentCodes = { EU: 'Europe', AS: 'Asia', AF: 'Africa', NA: 'North America', SA: 'South America', OC: 'Oceania', AN: 'Antarctica' };

export default function RegionRulesEditor({ rules, onChange, countries, priceModeLabel, error }) {
    const t = useTranslate();
    const ruleTypeOptions = [
        { value: 'exclude', label: t('delivery.rule_type_options.exclude', 'Exclude country') },
        { value: 'country_rate', label: t('delivery.rule_type_options.country_rate', 'Country rate') },
        { value: 'continent_rate', label: t('delivery.rule_type_options.continent_rate', 'Continent rate') },
    ];
    const continentOptions = Object.entries(continentCodes).map(([value, label]) => ({ value, label: t(`delivery.continents.${value}`, label) }));
    const update = (index, field, value) => onChange(rules.map((rule, i) => (i === index ? { ...rule, [field]: value } : rule)));

    return (
        <div>
            <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 4 }}>{t('delivery.regions_title', 'Regions')}</div>
            <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 10 }}>
                {t('delivery.regions_hint', 'Matching order: country exclusion → country rate → continent rate. No match means the method is unavailable for that address.')}
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {rules.map((rule, index) => (
                    <div key={index} className="grid grid-cols-1 sm:grid-cols-4" style={{ gap: 8, alignItems: 'flex-end' }}>
                        <Select size="sm" label={t('delivery.fields.rule_type', 'Rule')} value={rule.rule_type} options={ruleTypeOptions} onChange={(e) => update(index, 'rule_type', e.target.value)} />
                        {rule.rule_type === 'continent_rate' ? (
                            <Select label={t('delivery.fields.continent', 'Continent')} placeholder={t('delivery.fields.select_placeholder', 'Select')} value={rule.continent_code ?? ''} options={continentOptions} onChange={(e) => update(index, 'continent_code', e.target.value)} />
                        ) : (
                            <CountryCombobox label={t('delivery.fields.country', 'Country')} countries={countries} value={rule.country_code ?? ''} onChange={(code) => update(index, 'country_code', code)} />
                        )}
                        {rule.rule_type !== 'exclude' ? (
                            <Input label={t('delivery.fields.price_with_mode', 'Price (:mode)', { mode: priceModeLabel })} value={rule.price_amount ?? ''} onChange={(e) => update(index, 'price_amount', e.target.value)} />
                        ) : <div />}
                        <Button type="button" variant="ghost" onClick={() => onChange(rules.filter((_, i) => i !== index))}><Trash2 size={14} /></Button>
                    </div>
                ))}
            </div>
            {error && <div style={{ color: 'var(--danger-text)', fontSize: 12, marginTop: 8 }}>{error}</div>}
            <div style={{ marginTop: 10 }}>
                <Button type="button" variant="outline" size="sm" icon={<Plus size={14} />} onClick={() => onChange([...rules, emptyRule()])}>{t('delivery.add_rule', 'Add rule')}</Button>
            </div>
        </div>
    );
}
