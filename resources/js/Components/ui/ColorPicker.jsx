/**
 * Color picker primitive from the Litecommerce panel design system.
 */
export default function ColorPicker({ label, value = '#2563eb', onChange, disabled, error }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: 'var(--font-body)' }}>
            {label && <label style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{label}</label>}
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    height: 38,
                    border: `1px solid ${error ? 'var(--danger-solid)' : 'var(--border-default)'}`,
                    borderRadius: 'var(--radius-control)',
                    padding: '0 10px',
                }}
            >
                <input
                    type="color"
                    value={value}
                    disabled={disabled}
                    onChange={(e) => onChange?.(e.target.value)}
                    style={{ width: 24, height: 24, border: 'none', borderRadius: 'var(--radius-sm)', padding: 0, cursor: disabled ? 'not-allowed' : 'pointer' }}
                />
                <input
                    value={value}
                    disabled={disabled}
                    onChange={(e) => onChange?.(e.target.value)}
                    style={{
                        flex: 1,
                        border: 'none',
                        outline: 'none',
                        background: 'transparent',
                        fontSize: 14,
                        fontFamily: 'var(--font-mono)',
                        color: 'var(--text-primary)',
                        textTransform: 'uppercase',
                    }}
                />
            </div>
            {error && <span style={{ fontSize: 12, color: 'var(--danger-text)' }}>{error}</span>}
        </div>
    );
}
