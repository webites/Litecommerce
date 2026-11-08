/**
 * Card primitive from the Litecommerce panel design system.
 */
export default function Card({ title, description, action, children, padding = 20 }) {
    return (
        <div
            style={{
                background: 'var(--bg-surface)',
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-card)',
                boxShadow: 'var(--shadow-sm)',
                fontFamily: 'var(--font-body)',
            }}
        >
            {(title || description || action) && (
                <div
                    className="lc-card-header"
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        gap: 16,
                        padding: `14px ${padding}px`,
                        borderBottom: '1px solid var(--border-subtle)',
                    }}
                >
                    <div style={{ minWidth: 0 }}>
                        {title && (
                            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 15, color: 'var(--text-primary)' }}>
                                {title}
                            </div>
                        )}
                        {description && (
                            <div style={{ marginTop: title ? 4 : 0, fontSize: 13, lineHeight: 1.45, color: 'var(--text-secondary)' }}>
                                {description}
                            </div>
                        )}
                    </div>
                    {action && <div style={{ flexShrink: 0 }}>{action}</div>}
                </div>
            )}
            <div style={{ padding }}>{children}</div>
        </div>
    );
}
