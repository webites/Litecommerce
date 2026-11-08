/**
 * Page header primitive from the Litecommerce panel design system.
 * Used as the `header` prop for `AuthenticatedLayout`.
 */
export default function PageHeader({ title, description, actions, breadcrumbs }) {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4, fontFamily: 'var(--font-body)' }}>
            {breadcrumbs}
            <div className="lc-page-header-row" style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
                <div>
                    <div
                        style={{
                            fontFamily: 'var(--font-display)',
                            fontWeight: 700,
                            fontSize: 'var(--text-xl)',
                            lineHeight: 'var(--leading-tight)',
                            color: 'var(--text-primary)',
                            letterSpacing: 'var(--tracking-tight)',
                        }}
                    >
                        {title}
                    </div>
                    {description && (
                        <div style={{ fontSize: 13, lineHeight: 'var(--leading-snug)', color: 'var(--text-secondary)', marginTop: 2 }}>{description}</div>
                    )}
                </div>
                {actions && <div className="lc-page-header-actions" style={{ display: 'flex', gap: 8, flexShrink: 0 }}>{actions}</div>}
            </div>
        </div>
    );
}
