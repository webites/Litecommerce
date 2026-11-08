const palettes = {
    underline: {
        wrap: { gap: 4, overflowX: 'auto', borderBottom: '1px solid var(--border-subtle)', marginBottom: 20 },
        tab: (active) => ({
            borderBottom: active ? '2px solid var(--brand-solid)' : '2px solid transparent',
            color: active ? 'var(--brand-solid)' : 'var(--text-secondary)',
            padding: '14px 12px 12px', fontSize: 14,
        }),
    },
    pill: {
        wrap: { gap: 6, flexWrap: 'wrap', marginBottom: 16 },
        tab: (active) => ({
            borderRadius: 'var(--radius-control)',
            background: active ? 'var(--brand-subtle-bg)' : 'transparent',
            color: active ? 'var(--brand-solid)' : 'var(--text-secondary)',
            padding: '8px 12px', fontSize: 13,
        }),
    },
};

/**
 * Tabs primitive from the Litecommerce panel design system. `underline` for
 * top-of-page section navigation, `pill` for a switcher nested in a card.
 */
export default function Tabs({ items, value, onChange, ariaLabel, variant = 'underline' }) {
    const palette = palettes[variant] ?? palettes.underline;

    return (
        <div role="tablist" aria-label={ariaLabel} style={{ display: 'flex', ...palette.wrap }}>
            {items.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    role="tab"
                    aria-selected={value === item.key}
                    disabled={item.disabled}
                    onClick={() => onChange(item.key)}
                    style={{
                        border: 0,
                        background: 'transparent',
                        fontFamily: 'var(--font-body)',
                        fontWeight: 600,
                        whiteSpace: 'nowrap',
                        cursor: item.disabled ? 'not-allowed' : 'pointer',
                        ...palette.tab(value === item.key),
                    }}
                >
                    {item.label}
                </button>
            ))}
        </div>
    );
}
