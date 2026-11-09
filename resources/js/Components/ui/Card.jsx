import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Card primitive from the Litecommerce panel design system.
 * With `collapsible` the header toggles the body; `forceOpen` opens it
 * (e.g. when a field inside has a validation error) and `summary` is shown
 * next to the title while it is closed.
 */
export default function Card({ title, description, action, children, padding = 20, collapsible = false, defaultOpen = true, forceOpen = false, summary }) {
    const [open, setOpen] = useState(defaultOpen);

    useEffect(() => {
        if (forceOpen) setOpen(true);
    }, [forceOpen]);

    const expanded = !collapsible || open;
    const hasHeader = title || description || action;

    const heading = (
        <div style={{ minWidth: 0, flex: '1 1 220px' }}>
            {title && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 15, color: 'var(--text-primary)' }}>
                    {collapsible && (expanded ? <ChevronDown size={16} color="var(--text-tertiary)" /> : <ChevronRight size={16} color="var(--text-tertiary)" />)}
                    <span>{title}</span>
                    {collapsible && !expanded && summary && (
                        <span style={{ fontFamily: 'var(--font-body)', fontWeight: 400, fontSize: 12, color: 'var(--text-tertiary)' }}>{summary}</span>
                    )}
                </div>
            )}
            {description && expanded && (
                <div style={{ marginTop: title ? 4 : 0, paddingLeft: collapsible ? 24 : 0, fontSize: 13, lineHeight: 1.45, color: 'var(--text-secondary)', textAlign: 'left' }}>
                    {description}
                </div>
            )}
        </div>
    );

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
            {hasHeader && (
                <div
                    className="lc-card-header"
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'space-between',
                        flexWrap: 'wrap',
                        gap: 16,
                        padding: `14px ${padding}px`,
                        borderBottom: expanded ? '1px solid var(--border-subtle)' : 'none',
                    }}
                >
                    {collapsible ? (
                        <button
                            type="button"
                            aria-expanded={expanded}
                            onClick={() => setOpen((current) => !current)}
                            style={{ display: 'block', flex: '1 1 220px', minWidth: 0, padding: 0, border: 'none', background: 'none', cursor: 'pointer', textAlign: 'left', font: 'inherit', color: 'inherit' }}
                        >
                            {heading}
                        </button>
                    ) : heading}
                    {action && <div style={{ flexShrink: 0 }}>{action}</div>}
                </div>
            )}
            <div style={{ padding }} hidden={!expanded}>{children}</div>
        </div>
    );
}
