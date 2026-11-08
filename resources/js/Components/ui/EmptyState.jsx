import { Inbox } from 'lucide-react';

/**
 * Empty state primitive from the Litecommerce panel design system.
 */
export default function EmptyState({ icon: Icon = Inbox, title, description, action }) {
    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                textAlign: 'center',
                padding: '56px 24px',
                fontFamily: 'var(--font-body)',
                gap: 6,
            }}
        >
            <div
                style={{
                    width: 56,
                    height: 56,
                    borderRadius: 'var(--radius-xl)',
                    background: 'var(--gray-3)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 10,
                }}
            >
                <Icon size={26} color="var(--text-tertiary)" />
            </div>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 16, color: 'var(--text-primary)' }}>{title}</div>
            {description && <div style={{ fontSize: 13, color: 'var(--text-secondary)', maxWidth: 340 }}>{description}</div>}
            {action && <div style={{ marginTop: 14 }}>{action}</div>}
        </div>
    );
}
