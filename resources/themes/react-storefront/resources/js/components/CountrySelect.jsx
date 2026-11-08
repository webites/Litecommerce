// Searchable country picker for the two address forms in CartCheckout.jsx.
// Mirrors theme::partials.country-select's contract on the Blade side: the
// list (code + translated name + flag, already filtered to what Delivery
// can quote — see CheckoutController::countries()) is fetched once from
// GET /checkout/countries and shared by every instance on the page, on
// demand rather than embedded in the page, so it never delays first paint.

import { useEffect, useMemo, useState } from 'react';
import { Combobox, ComboboxInput, ComboboxOptions, ComboboxOption } from '@headlessui/react';
import { apiFetch, t } from '../api';

let countriesPromise = null;

function loadCountries() {
    if (!countriesPromise) {
        countriesPromise = apiFetch('/checkout/countries').then((body) => body?.countries ?? []).catch(() => []);
    }
    return countriesPromise;
}

export default function CountrySelect({ value, onChange }) {
    const [countries, setCountries] = useState([]);
    const [query, setQuery] = useState('');

    useEffect(() => {
        let active = true;
        loadCountries().then((list) => {
            if (active) {
                setCountries(list);
            }
        });
        return () => {
            active = false;
        };
    }, []);

    const selected = useMemo(() => countries.find((country) => country.code === value) ?? null, [countries, value]);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return countries;
        }
        return countries.filter((country) => country.name.toLowerCase().includes(needle) || country.code.toLowerCase().includes(needle));
    }, [countries, query]);

    return (
        <Combobox as="div" className="sf-country-select" value={selected} onChange={(country) => onChange(country?.code ?? '')}>
            <ComboboxInput
                className="sf-country-search"
                placeholder={t('checkout.country_search_placeholder')}
                displayValue={(country) => (country ? `${country.flag} ${country.name}` : '')}
                onChange={(event) => setQuery(event.target.value)}
            />
            <ComboboxOptions as="ul" className="sf-country-options">
                {filtered.length === 0 ? (
                    <li className="is-empty">{t('checkout.country_no_results')}</li>
                ) : (
                    filtered.map((country) => (
                        <ComboboxOption key={country.code} as="li" value={country}>
                            {country.flag} {country.name}
                        </ComboboxOption>
                    ))
                )}
            </ComboboxOptions>
        </Combobox>
    );
}
