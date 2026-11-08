/**
 * Switch primitive from the Litecommerce panel design system.
 */
export default function Switch({ checked = false, onChange, disabled, label }) {
    return (
        <label
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 10,
                cursor: disabled ? 'not-allowed' : 'pointer',
                fontFamily: 'var(--font-body)',
                fontSize: 14,
                color: disabled ? 'var(--text-disabled)' : 'var(--text-primary)',
            }}
        >
            <span
                role="switch"
                aria-checked={checked}
                onClick={() => !disabled && onChange?.(!checked)}
                style={{
                    width: 38,
                    height: 22,
                    borderRadius: 'var(--radius-pill)',
                    background: checked ? 'var(--brand-solid)' : 'var(--gray-6)',
                    position: 'relative',
                    flexShrink: 0,
                    transition: 'background var(--duration-fast) var(--ease-out)',
                }}
            >
                <span
                    style={{
                        position: 'absolute',
                        top: 2,
                        left: checked ? 18 : 2,
                        width: 18,
                        height: 18,
                        borderRadius: '50%',
                        background: '#fff',
                        boxShadow: 'var(--shadow-xs)',
                        transition: 'left var(--duration-fast) var(--ease-out)',
                    }}
                />
            </span>
            {label}
        </label>
    );
}
