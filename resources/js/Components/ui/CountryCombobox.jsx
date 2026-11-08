import { useMemo, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { Combobox, ComboboxInput, ComboboxOptions, ComboboxOption } from '@headlessui/react';
import { useTranslate } from '@/lib/i18n';

/**
 * Searchable country picker for the panel design system — same
 * code/name/flag shape as the storefront's own country widgets
 * (App\Support\I18n\CountryDirectory), but unfiltered: the merchant must be
 * able to pick ANY country to configure a delivery rate or exclusion for it,
 * not just ones already sellable.
 */
export default function CountryCombobox({ label, countries = [], value, onChange, placeholder, error, helpText, size = 'md', className }) {
    const t = useTranslate();
    const [query, setQuery] = useState('');
    const [focus, setFocus] = useState(false);
    const resolvedPlaceholder = placeholder ?? t('app.select_placeholder', 'Select');
    const h = size === 'sm' ? 32 : size === 'lg' ? 44 : 38;
    const border = error ? 'var(--danger-solid)' : focus ? 'var(--brand-solid)' : 'var(--border-default)';

    const selected = useMemo(() => countries.find((country) => country.code === value) ?? null, [countries, value]);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return countries;
        }
        return countries.filter((country) => country.name.toLowerCase().includes(needle) || country.code.toLowerCase().includes(needle));
    }, [countries, query]);

    return (
        <div className={className} style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: 'var(--font-body)' }}>
            {label && <label style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{label}</label>}
            <Combobox as="div" style={{ position: 'relative' }} value={selected} onChange={(country) => onChange(country?.code ?? '')}>
                <ComboboxInput
                    onFocus={() => setFocus(true)}
                    onBlur={() => setFocus(false)}
                    displayValue={(country) => (country ? `${country.flag} ${country.name}` : '')}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={resolvedPlaceholder}
                    style={{
                        width: '100%',
                        height: h,
                        border: `1px solid ${border}`,
                        borderRadius: 'var(--radius-control)',
                        padding: '0 32px 0 10px',
                        fontSize: 14,
                        fontFamily: 'var(--font-body)',
                        background: 'var(--bg-surface)',
                        color: 'var(--text-primary)',
                        boxShadow: focus ? 'var(--shadow-focus)' : 'none',
                        outline: 'none',
                    }}
                />
                <ChevronDown size={16} color="var(--text-tertiary)" style={{ position: 'absolute', right: 10, top: 11, pointerEvents: 'none' }} />
                <ComboboxOptions
                    as="ul"
                    style={{
                        position: 'absolute',
                        left: 0,
                        right: 0,
                        top: 'calc(100% + 4px)',
                        margin: 0,
                        padding: 4,
                        maxHeight: 240,
                        overflowY: 'auto',
                        listStyle: 'none',
                        background: 'var(--bg-surface)',
                        border: '1px solid var(--border-default)',
                        borderRadius: 'var(--radius-control)',
                        boxShadow: 'var(--shadow-md)',
                        zIndex: 20,
                    }}
                >
                    {filtered.length === 0 ? (
                        <li style={{ padding: '8px 10px', fontSize: 14, color: 'var(--text-tertiary)' }}>{t('app.no_country_found', 'No country found.')}</li>
                    ) : (
                        filtered.map((country) => (
                            <ComboboxOption key={country.code} as="li" value={country}>
                                {({ focus: rowFocus }) => (
                                    <div
                                        style={{
                                            padding: '8px 10px',
                                            borderRadius: 6,
                                            fontSize: 14,
                                            cursor: 'pointer',
                                            background: rowFocus ? 'var(--gray-3)' : 'transparent',
                                        }}
                                    >
                                        {country.flag} {country.name}
                                    </div>
                                )}
                            </ComboboxOption>
                        ))
                    )}
                </ComboboxOptions>
            </Combobox>
            {error ? (
                <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{error}</span>
            ) : helpText ? (
                <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{helpText}</span>
            ) : null}
        </div>
    );
}
