import { useState } from 'react';

/**
 * Textarea primitive from the Litecommerce panel design system.
 */
export default function Textarea({ label, rows = 4, error, helpText, className, onChange, ...rest }) {
    const [focus, setFocus] = useState(false);
    const border = error ? 'var(--danger-solid)' : focus ? 'var(--brand-solid)' : 'var(--border-default)';

    return (
        <div className={className} style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: 'var(--font-body)' }}>
            {label && (
                <label htmlFor={rest.id} style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>
                    {label}
                    {rest.required && <span aria-hidden="true" style={{ color: 'var(--danger-text)' }}> *</span>}
                </label>
            )}
            <textarea
                rows={rows}
                onChange={(e) => onChange?.(e.target.value)}
                onFocus={(e) => {
                    setFocus(true);
                    rest.onFocus?.(e);
                }}
                onBlur={(e) => {
                    setFocus(false);
                    rest.onBlur?.(e);
                }}
                style={{
                    border: `1px solid ${border}`,
                    borderRadius: 'var(--radius-control)',
                    padding: 10,
                    fontSize: 14,
                    fontFamily: 'var(--font-body)',
                    background: rest.disabled ? 'var(--gray-3)' : 'var(--bg-surface)',
                    color: 'var(--text-primary)',
                    boxShadow: focus ? 'var(--shadow-focus)' : 'none',
                    resize: 'vertical',
                }}
                {...rest}
            />
            {error ? (
                <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{error}</span>
            ) : helpText ? (
                <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{helpText}</span>
            ) : null}
        </div>
    );
}
