import Alert from '@/Components/ui/Alert';
import { useTranslate } from '@/lib/i18n';
import { instructionsFor, isVisibleToCustomers, priceSummary } from './helpers';

export default function MethodPreview({ kind, name, price, rules, points, payOnPickup, currency }) {
    const t = useTranslate();
    const locale = document.documentElement.lang || 'en';
    const visible = isVisibleToCustomers(kind, points, rules);
    const point = points.find((candidate) => candidate.active) ?? null;
    const instructions = point ? instructionsFor(point.instructions, locale) : '';
    const muted = { fontSize: 12.5, color: 'var(--text-secondary)', marginTop: 4 };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-tertiary)', textTransform: 'uppercase', letterSpacing: 0.4 }}>{t('delivery.preview_title', 'What customers see at checkout')}</div>
            <div style={{ border: '1px solid var(--border-default)', borderRadius: 'var(--radius-card)', padding: 14, background: 'var(--bg-surface)', opacity: visible ? 1 : 0.6 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <span aria-hidden style={{ width: 16, height: 16, borderRadius: '50%', border: '2px solid var(--brand-solid)', flex: 'none', boxSizing: 'border-box', background: 'radial-gradient(var(--brand-solid) 40%, transparent 45%)' }} />
                    <span style={{ fontWeight: 600, fontSize: 14, flex: 1 }}>{name.trim() || t('delivery.preview_name_placeholder', 'Method name')}</span>
                    <span style={{ fontSize: 14 }}>{priceSummary({ kind, price_amount: price, rules }, t, currency)}</span>
                </div>
                {kind === 'pickup' && point && (
                    <div style={{ marginLeft: 26 }}>
                        <div style={muted}>{[point.name, point.address_line_1, `${point.postal_code} ${point.city}`.trim()].filter(Boolean).join(' — ')}</div>
                        {instructions && <div style={muted}>{instructions}</div>}
                    </div>
                )}
                {kind === 'pickup' && payOnPickup && <div style={{ ...muted, marginLeft: 26 }}>{t('delivery.preview_pay_on_pickup', 'Payment on pickup is offered.')}</div>}
            </div>
            {!visible && (
                <Alert tone="warning" title={t('delivery.no_point_title', 'Customers cannot see this method yet')}>
                    {kind === 'pickup'
                        ? t('delivery.no_point_text', 'A pickup method appears at checkout only when it has at least one active pickup point.')
                        : t('delivery.no_rate_text', 'A courier method appears at checkout only for addresses matched by a rate. Add at least one rate.')}
                </Alert>
            )}
        </div>
    );
}
