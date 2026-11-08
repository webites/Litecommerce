import { AlertOctagon, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';

const tones = {
    info: { bg: 'var(--info-subtle-bg)', fg: 'var(--info-text)', Icon: Info },
    success: { bg: 'var(--success-subtle-bg)', fg: 'var(--success-text)', Icon: CheckCircle2 },
    warning: { bg: 'var(--warning-subtle-bg)', fg: 'var(--warning-text)', Icon: AlertTriangle },
    danger: { bg: 'var(--danger-subtle-bg)', fg: 'var(--danger-text)', Icon: AlertOctagon },
};

/**
 * Alert primitive from the Litecommerce panel design system.
 */
export default function Alert({ tone = 'info', title, children, onDismiss }) {
    const t = tones[tone] || tones.info;
    const Icon = t.Icon;

    return (
        <div
            style={{
                display: 'flex',
                gap: 12,
                padding: '12px 14px',
                borderRadius: 'var(--radius-lg)',
                background: t.bg,
                color: t.fg,
                fontFamily: 'var(--font-body)',
            }}
        >
            <Icon size={18} style={{ flexShrink: 0, marginTop: 1 }} />
            <div style={{ flex: 1 }}>
                {title && <div style={{ fontWeight: 600, fontSize: 14 }}>{title}</div>}
                <div style={{ fontSize: 13, marginTop: title ? 2 : 0, opacity: 0.9 }}>{children}</div>
            </div>
            {onDismiss && (
                <button
                    onClick={onDismiss}
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'inherit', opacity: 0.7 }}
                >
                    <X size={16} />
                </button>
            )}
        </div>
    );
}
