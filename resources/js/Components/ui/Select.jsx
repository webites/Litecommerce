import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

/**
 * Select primitive from the Litecommerce panel design system.
 * Extended with id/required/disabled passthrough for real forms.
 */
export default function Select({ label, options = [], placeholder, error, helpText, className, ...rest }) {
    const [focus, setFocus] = useState(false);
    const border = error ? 'var(--danger-solid)' : focus ? 'var(--brand-solid)' : 'var(--border-default)';

    return (
        <div className={className} style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: 'var(--font-body)' }}>
            {label && (
                <label htmlFor={rest.id} style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>
                    {label}
                </label>
            )}
            <div style={{ position: 'relative' }}>
                <select
                    onFocus={(e) => {
                        setFocus(true);
                        rest.onFocus?.(e);
                    }}
                    onBlur={(e) => {
                        setFocus(false);
                        rest.onBlur?.(e);
                    }}
                    style={{
                        width: '100%',
                        height: 38,
                        border: `1px solid ${border}`,
                        borderRadius: 'var(--radius-control)',
                        padding: '0 32px 0 10px',
                        fontSize: 14,
                        fontFamily: 'var(--font-body)',
                        background: rest.disabled ? 'var(--gray-3)' : 'var(--bg-surface)',
                        color: 'var(--text-primary)',
                        boxShadow: focus ? 'var(--shadow-focus)' : 'none',
                        appearance: 'none',
                    }}
                    {...rest}
                >
                    {placeholder && (
                        <option value="" disabled hidden>
                            {placeholder}
                        </option>
                    )}
                    {options.map((o, i) => (
                        <option key={i} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
                <ChevronDown
                    size={16}
                    color="var(--text-tertiary)"
                    style={{ position: 'absolute', right: 10, top: 11, pointerEvents: 'none' }}
                />
            </div>
            {error ? (
                <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{error}</span>
            ) : helpText ? (
                <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{helpText}</span>
            ) : null}
        </div>
    );
}
