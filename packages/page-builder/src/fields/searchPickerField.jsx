import React, { useState } from 'react';

export function createSearchPickerField({ searchUrl, multiple = false, placeholder = 'Szukaj…' }) {
    return {
        type: 'custom',
        render: ({ value, onChange }) => (
            <SearchPicker value={value} onChange={onChange} searchUrl={searchUrl} multiple={multiple} placeholder={placeholder} />
        ),
    };
}

function SearchPicker({ value, onChange, searchUrl, multiple, placeholder }) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState([]);
    const selected = multiple ? (Array.isArray(value) ? value : []) : (value ? [value] : []);

    const search = async (nextTerm) => {
        setTerm(nextTerm);
        if (nextTerm.trim().length < 2) {
            setResults([]);
            return;
        }
        const response = await fetch(`${searchUrl}?q=${encodeURIComponent(nextTerm)}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await response.json();
        setResults(json.data ?? []);
    };

    const pick = (candidate) => {
        if (multiple) {
            if (!selected.some((item) => item.id === candidate.id)) {
                onChange([...selected, candidate]);
            }
        } else {
            onChange(candidate);
        }
        setTerm('');
        setResults([]);
    };

    const remove = (id) => {
        onChange(multiple ? selected.filter((item) => item.id !== id) : null);
    };

    return (
        <div className="lc-page-builder-search-picker">
            {selected.length > 0 && (
                <div className="lc-page-builder-search-picker__selected">
                    {selected.map((item) => (
                        <span key={item.id} className="lc-page-builder-search-picker__chip">
                            {item.name}
                            <button type="button" onClick={() => remove(item.id)}>&times;</button>
                        </span>
                    ))}
                </div>
            )}
            {(multiple || selected.length === 0) && (
                <>
                    <input type="text" value={term} placeholder={placeholder} onChange={(event) => search(event.target.value)} />
                    {results.length > 0 && (
                        <ul className="lc-page-builder-search-picker__results">
                            {results.map((candidate) => (
                                <li key={candidate.id}>
                                    <button type="button" onClick={() => pick(candidate)}>
                                        {candidate.name}{candidate.sku ? ` (${candidate.sku})` : ''}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
