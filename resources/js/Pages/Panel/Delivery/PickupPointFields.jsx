import CountryCombobox from '@/Components/ui/CountryCombobox';
import Input from '@/Components/ui/Input';
import Textarea from '@/Components/ui/Textarea';
import { useTranslate } from '@/lib/i18n';

export default function PickupPointFields({ data, setData, errors, prefix = '', languages, countries }) {
    const t = useTranslate();
    const error = (field) => errors[`${prefix}${field}`];
    const multiLanguage = languages.length > 1;

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                <Input
                    required label={t('delivery.fields.point_name', 'Pickup point name')} value={data.name} error={error('name')}
                    placeholder={t('delivery.fields.point_name_placeholder', 'e.g. Main store — Wrocław')}
                    helpText={t('delivery.fields.point_name_help', 'Customers see it at checkout next to the method name.')}
                    onChange={(e) => setData('name', e.target.value)}
                />
                <CountryCombobox label={t('delivery.fields.country', 'Country')} countries={countries} value={data.country_code} error={error('country_code')} onChange={(code) => setData('country_code', code)} />
                <Input required label={t('delivery.fields.postal_code', 'Postal code')} value={data.postal_code} error={error('postal_code')} onChange={(e) => setData('postal_code', e.target.value)} />
                <Input required label={t('delivery.fields.city', 'City')} value={data.city} error={error('city')} onChange={(e) => setData('city', e.target.value)} />
                <Input required label={t('delivery.fields.address_line_1', 'Address')} placeholder={t('delivery.fields.address_placeholder', 'Street and number')} value={data.address_line_1} error={error('address_line_1')} onChange={(e) => setData('address_line_1', e.target.value)} />
                <Input label={t('delivery.fields.address_line_2', 'Address (cont.)')} value={data.address_line_2} error={error('address_line_2')} onChange={(e) => setData('address_line_2', e.target.value)} />
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
                    onChange={(value) => setData('instructions', { ...data.instructions, [language.code]: value })}
                />
            ))}
        </div>
    );
}
