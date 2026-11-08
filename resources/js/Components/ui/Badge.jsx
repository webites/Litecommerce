const tones = {
    neutral: { bg: 'var(--gray-4)', fg: 'var(--text-secondary)' },
    brand: { bg: 'var(--brand-subtle-bg)', fg: 'var(--brand-subtle-text)' },
    success: { bg: 'var(--success-subtle-bg)', fg: 'var(--success-text)' },
    warning: { bg: 'var(--warning-subtle-bg)', fg: 'var(--warning-text)' },
    danger: { bg: 'var(--danger-subtle-bg)', fg: 'var(--danger-text)' },
    info: { bg: 'var(--info-subtle-bg)', fg: 'var(--info-text)' },
};

/**
 * Badge primitive from the Litecommerce panel design system.
 */
export default function Badge({ tone = 'neutral', children, dot = false }) {
    const t = tones[tone] || tones.neutral;

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '3px 10px',
                borderRadius: 'var(--radius-pill)',
                background: t.bg,
                color: t.fg,
                fontSize: 12,
                fontWeight: 600,
                fontFamily: 'var(--font-body)',
                lineHeight: 1.4,
                whiteSpace: 'nowrap',
            }}
        >
            {dot && <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'currentColor' }} />}
            {children}
        </span>
    );
}
