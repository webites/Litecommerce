import { forwardRef, useState } from 'react';

/**
 * Input primitive from the Litecommerce panel design system.
 * Extended with standard HTML form attributes (id, name, autoComplete,
 * required, maxLength, min, max, step) needed to wire real forms —
 * the design source only modelled label/placeholder/value/onChange.
 */
const Input = forwardRef(function Input(
    { label, error, disabled, prefix, suffix, size = 'md', helpText, className, style, ...rest },
    ref,
) {
    const [focus, setFocus] = useState(false);
    const h = size === 'sm' ? 32 : size === 'lg' ? 44 : 38;
    const border = error ? 'var(--danger-solid)' : focus ? 'var(--brand-solid)' : 'var(--border-default)';

    return (
        <div className={className} style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: 'var(--font-body)', ...style }}>
            {label && (
                <label htmlFor={rest.id} style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>
                    {label}
                    {rest.required && <span aria-hidden="true" style={{ color: 'var(--danger-text)' }}> *</span>}
                </label>
            )}
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    height: h,
                    border: `1px solid ${border}`,
                    borderRadius: 'var(--radius-control)',
                    background: disabled ? 'var(--gray-3)' : 'var(--bg-surface)',
                    boxShadow: focus ? 'var(--shadow-focus)' : 'none',
                    padding: '0 10px',
                    gap: 8,
                    transition: 'box-shadow var(--duration-fast) var(--ease-out)',
                }}
            >
                {prefix && <span style={{ color: 'var(--text-tertiary)', fontSize: 13 }}>{prefix}</span>}
                <input
                    ref={ref}
                    disabled={disabled}
                    onFocus={(e) => {
                        setFocus(true);
                        rest.onFocus?.(e);
                    }}
                    onBlur={(e) => {
                        setFocus(false);
                        rest.onBlur?.(e);
                    }}
                    style={{
                        flex: 1,
                        minWidth: 0,
                        border: 'none',
                        outline: 'none',
                        background: 'transparent',
                        fontSize: 14,
                        fontFamily: 'var(--font-body)',
                        color: 'var(--text-primary)',
                        height: '100%',
                    }}
                    {...rest}
                />
                {suffix && <span style={{ color: 'var(--text-tertiary)', fontSize: 13 }}>{suffix}</span>}
            </div>
            {error ? (
                <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{error}</span>
            ) : helpText ? (
                <span style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{helpText}</span>
            ) : null}
        </div>
    );
});

export default Input;
