/**
 * Radio primitive from the Litecommerce panel design system.
 */
export default function Radio({ label, checked = false, onChange, disabled, name }) {
    return (
        <label
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 8,
                cursor: disabled ? 'not-allowed' : 'pointer',
                fontFamily: 'var(--font-body)',
                fontSize: 14,
                color: disabled ? 'var(--text-disabled)' : 'var(--text-primary)',
            }}
        >
            <input
                type="radio"
                name={name}
                checked={checked}
                disabled={disabled}
                onChange={() => onChange?.(true)}
                style={{ position: 'absolute', width: 1, height: 1, opacity: 0, pointerEvents: 'none' }}
            />
            <span
                style={{
                    width: 18,
                    height: 18,
                    borderRadius: '50%',
                    border: `1.5px solid ${checked ? 'var(--brand-solid)' : 'var(--border-strong)'}`,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                }}
            >
                {checked && <span style={{ width: 9, height: 9, borderRadius: '50%', background: 'var(--brand-solid)' }} />}
            </span>
            {label}
        </label>
    );
}
