import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import { formatMoney as money } from '@/lib/money';
import { formatDateTime, formatQuantity } from '@/lib/format';

const statusTone = { active: 'success', abandoned: 'warning', checked_out: 'neutral' };

export default function Show({ cart }) {
    const t = useTranslate();
    const statusLabel = { active: t('cart.status.active'), abandoned: t('cart.status.abandoned'), checked_out: t('cart.status.checked_out') };
    const draftStatusLabel = { open: t('cart.show.draft_open'), confirmed: t('cart.show.draft_confirmed') };
    const unavailableLabel = {
        unavailable: t('cart.unavailable_reasons.unavailable'),
        no_price: t('cart.unavailable_reasons.no_price'),
        insufficient_stock: t('cart.unavailable_reasons.insufficient_stock'),
    };
    const title = t('cart.show.title', 'Cart #:id', { id: cart.id });

    return (
        <AuthenticatedLayout
            header={(
                <PageHeader
                    title={title}
                    description={t('cart.show.description')}
                    actions={<Button href={route('panel.cart.index')} variant="outline" size="sm">{t('cart.show.back_to_list')}</Button>}
                />
            )}
        >
            <Head title={title} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 1100 }}>
                {cart.status === 'checked_out' && (
                    <Alert tone="info" title={t('cart.show.checked_out_title')}>
                        {t('cart.show.checked_out_body')}
                    </Alert>
                )}
                {!cart.all_available && cart.status !== 'checked_out' && (
                    <Alert tone="warning" title={t('cart.show.needs_attention_title')}>
                        {t('cart.show.needs_attention_body')}
                    </Alert>
                )}

                <Card title={t('cart.show.info_title')}>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style={{ gap: 20, margin: 0 }}>
                        <div><dt style={termStyle}>{t('cart.show.owner')}</dt><dd style={valueStyle}>{cart.owner}</dd></div>
                        <div><dt style={termStyle}>{t('cart.show.status')}</dt><dd style={valueStyle}><Badge tone={statusTone[cart.status]}>{statusLabel[cart.status]}</Badge></dd></div>
                        <div><dt style={termStyle}>{t('cart.show.currency')}</dt><dd style={valueStyle}>{cart.currency_code}</dd></div>
                        <div><dt style={termStyle}>{t('cart.show.version')}</dt><dd style={valueStyle}>{cart.version}</dd></div>
                        <div><dt style={termStyle}>{t('cart.show.created_at')}</dt><dd style={valueStyle}>{formatDateTime(cart.created_at)}</dd></div>
                        <div><dt style={termStyle}>{t('cart.show.last_activity')}</dt><dd style={valueStyle}>{formatDateTime(cart.last_activity_at)}</dd></div>
                    </dl>
                </Card>

                <Card title={t('cart.show.lines_title')} description={t('cart.show.lines_description')}>
                    <Table
                        columns={[
                            { header: t('cart.show.columns.product'), render: (line) => <div><div style={{ fontWeight: 600 }}>{line.name}</div><div style={{ fontSize: 12, color: 'var(--text-tertiary)', fontFamily: 'var(--font-mono)' }}>{line.sku || t('cart.show.variant_fallback', 'variant #:id', { id: line.variant_id })}</div></div> },
                            { header: t('cart.show.columns.type'), render: (line) => line.product_type === 'digital' ? t('cart.show.type_digital') : line.product_type === 'physical' ? t('cart.show.type_physical') : '—' },
                            { header: t('cart.show.columns.quantity'), align: 'right', render: (line) => formatQuantity(line.quantity) },
                            { header: t('cart.show.columns.unit_price'), align: 'right', render: (line) => money(line.unit_amount, cart.currency_code) },
                            { header: t('cart.show.columns.gross'), align: 'right', render: (line) => money(line.line_gross_amount, cart.currency_code) },
                            { header: t('cart.show.columns.availability'), render: (line) => line.available ? <Badge tone="success">{t('cart.show.available')}</Badge> : <Badge tone="danger">{unavailableLabel[line.unavailable_reason] ?? t('cart.show.unavailable')}</Badge> },
                        ]}
                        rows={cart.lines}
                    />
                    <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 12, marginTop: 20 }}>
                        <div><div style={termStyle}>{t('cart.show.net')}</div><div style={totalStyle}>{money(cart.total_net_amount, cart.currency_code)}</div></div>
                        <div><div style={termStyle}>{t('cart.show.vat')}</div><div style={totalStyle}>{money(cart.total_vat_amount, cart.currency_code)}</div></div>
                        <div><div style={termStyle}>{t('cart.show.gross')}</div><div style={totalStyle}>{money(cart.total_gross_amount, cart.currency_code)}</div></div>
                    </div>
                </Card>

                <Card title={t('cart.show.drafts_title')} description={t('cart.show.drafts_description')}>
                    <Table
                        columns={[
                            { header: t('cart.show.drafts_columns.uuid'), render: (draft) => <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12 }}>{draft.id}</span> },
                            { header: t('cart.show.drafts_columns.buyer'), render: (draft) => <div><div>{draft.email || t('cart.show.no_email')}</div>{draft.company_name && <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{draft.company_name}</div>}</div> },
                            { header: t('cart.show.drafts_columns.status'), render: (draft) => <Badge tone={draft.status === 'confirmed' ? 'success' : 'neutral'}>{draftStatusLabel[draft.status] ?? draft.status}</Badge> },
                            { header: t('cart.show.drafts_columns.value'), align: 'right', render: (draft) => money(draft.total_gross_amount, draft.currency_code) },
                            { header: t('cart.show.drafts_columns.confirmed_at'), render: (draft) => formatDateTime(draft.confirmed_at) },
                        ]}
                        rows={cart.checkout_drafts}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

const termStyle = { fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 4 };
const valueStyle = { margin: 0, fontSize: 14, color: 'var(--text-primary)' };
const totalStyle = { marginTop: 4, fontSize: 16, fontWeight: 700, color: 'var(--text-primary)' };
