import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import { useTranslate } from '@/lib/i18n';

/**
 * Sidebar primitive from the Litecommerce panel design system.
 * Adapted for real routing: items carry an `href` and `active` flag
 * (computed by the caller from the current Inertia/Ziggy route) instead
 * of the design source's client-side `activeId`/`onSelect` selection.
 */
export default function Sidebar({ items = [], brand = 'Litecommerce', open = false, onClose }) {
    const t = useTranslate();

    return (
        <>
            {open && <button type="button" className="lc-sidebar-overlay" aria-label={t('app.sidebar_close_nav', 'Close navigation')} onClick={onClose} />}
            <aside
                className={`lc-sidebar${open ? ' lc-sidebar--open' : ''}`}
                style={{
                    width: 'var(--layout-sidebar-w)',
                    flexShrink: 0,
                    height: '100%',
                    background: 'var(--bg-surface)',
                    borderRight: '1px solid var(--border-subtle)',
                    display: 'flex',
                    flexDirection: 'column',
                    fontFamily: 'var(--font-body)',
                }}
            >
            <div
                style={{
                    height: 'var(--layout-topbar-h)',
                    display: 'flex',
                    alignItems: 'center',
                    padding: '0 20px',
                    fontFamily: 'var(--font-display)',
                    fontWeight: 700,
                    fontSize: 16,
                    color: 'var(--text-primary)',
                    borderBottom: '1px solid var(--border-subtle)',
                    flexShrink: 0,
                }}
            >
                {brand}
            </div>
            <div style={{ flex: 1, overflowY: 'auto', padding: '12px 10px', display: 'flex', flexDirection: 'column', gap: 2 }}>
                {items.map((it, i) => (
                    <Fragment key={i}>
                        {it.section ? (
                            <div
                                style={{
                                    fontSize: 11,
                                    fontWeight: 600,
                                    letterSpacing: 'var(--tracking-wide)',
                                    textTransform: 'uppercase',
                                    color: 'var(--text-tertiary)',
                                    padding: '14px 10px 6px',
                                }}
                            >
                                {it.label}
                            </div>
                        ) : (
                            <Link
                                href={it.href}
                                onClick={onClose}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    padding: '8px 10px',
                                    borderRadius: 'var(--radius-control)',
                                    cursor: 'pointer',
                                    fontSize: 14,
                                    textDecoration: 'none',
                                    background: it.active ? 'var(--brand-subtle-bg)' : 'transparent',
                                    color: it.active ? 'var(--brand-subtle-text)' : 'var(--text-secondary)',
                                    fontWeight: it.active ? 600 : 500,
                                }}
                            >
                                {it.icon && <it.icon size={17} />}
                                {it.label}
                            </Link>
                        )}
                    </Fragment>
                ))}
            </div>
            </aside>
        </>
    );
}
