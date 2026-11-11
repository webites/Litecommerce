import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import Textarea from '@/Components/ui/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatMoney as money } from '@/lib/money';
import { formatDateTime } from '@/lib/format';

const lineStatusTones = { pending: 'warning', approved: 'success', rejected: 'danger' };
const term = { fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 4 };
const value = { margin: 0, color: 'var(--text-primary)', fontSize: 14 };


export default function Show({ returnRequest, order, reasons }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const pendingLines = returnRequest.lines.filter((l) => l.status === 'pending');
    const [decisions, setDecisions] = useState({});
    const [deliveryRefund, setDeliveryRefund] = useState('');
    const [processing, setProcessing] = useState(false);
    const [payoutDrafts, setPayoutDrafts] = useState({});

    const lineStatusLabels = {
        pending: t('returns.line_status.pending', 'Awaiting decision'),
        approved: t('returns.line_status.approved', 'Approved'),
        rejected: t('returns.line_status.rejected', 'Rejected'),
    };

    const reasonName = (id) => reasons.find((r) => r.id === id)?.name;
    const draft = (lineId) => decisions[lineId] || { include: false, decision: 'approved', restock: false, restocked_quantity: '', decision_reason: '' };
    const setField = (lineId, field, val) => setDecisions((c) => ({ ...c, [lineId]: { ...draft(lineId), [field]: val } }));

    const submitDecisions = () => {
        const payload = Object.entries(decisions)
            .filter(([, d]) => d.include)
            .map(([lineId, d]) => ({
                line_id: Number(lineId), decision: d.decision,
                restock: d.decision === 'approved' ? d.restock : undefined,
                restocked_quantity: d.decision === 'approved' && d.restock ? d.restocked_quantity : undefined,
                decision_reason: d.decision_reason || undefined,
            }));
        if (payload.length === 0) return;
        setProcessing(true);
        router.post(route('panel.returns.decide', returnRequest.id), {
            decisions: payload, delivery_refund_gross_amount: deliveryRefund || null,
        }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => { setDecisions({}); setDeliveryRefund(''); },
        });
    };

    const setPayoutField = (refundId, value) => setPayoutDrafts((c) => ({ ...c, [refundId]: value }));
    const submitPayout = (refundId) => {
        router.post(route('panel.returns.refunds.payout', [returnRequest.id, refundId]), {
            reference: payoutDrafts[refundId] || '',
        }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('returns.show_page_title', 'Return request')} description={t('returns.order_label', 'Order :id', { id: order.number })} actions={<Button href={route('panel.orders.show', returnRequest.order_id)} variant="outline" size="sm">{t('returns.view_order', 'View order')}</Button>} />}>
            <Head title={t('returns.show_page_title', 'Return request')} />
            <div style={{ padding: 24, maxWidth: 1100, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash.status && <Alert tone="success">{flash.status}</Alert>}

                <Card title={t('returns.summary_card_title', 'Summary')}>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style={{ gap: 20, margin: 0 }}>
                        <div><dt style={term}>{t('returns.column_status', 'Status')}</dt><dd style={value}><Badge tone={returnRequest.status === 'open' ? 'warning' : 'neutral'}>{returnRequest.status === 'open' ? t('returns.status_open', 'Open') : t('returns.status_closed', 'Closed')}</Badge></dd></div>
                        <div><dt style={term}>{t('returns.fields.source', 'Source')}</dt><dd style={value}>{returnRequest.source === 'staff' ? t('returns.source_staff', 'Staff') : t('returns.source_customer', 'Customer')}</dd></div>
                        <div><dt style={term}>{t('returns.column_customer', 'Customer')}</dt><dd style={value}>{returnRequest.email}</dd></div>
                        <div><dt style={term}>{t('returns.column_created_at', 'Requested')}</dt><dd style={value}>{formatDateTime(returnRequest.created_at)}</dd></div>
                    </dl>
                    {returnRequest.comment && <div style={{ marginTop: 16 }}><div style={term}>{t('returns.comment_label', 'Comment')}</div><div style={value}>{returnRequest.comment}</div></div>}
                </Card>

                <Card title={t('returns.lines_card_title', 'Lines')} description={t('returns.lines_card_description', 'Approving restores digital access/stock and creates a record of the amount to hand back manually. Rejecting affects neither.')}>
                    <Table columns={[
                        {
                            header: t('returns.column_line', 'Line'), render: (l) => (
                                <div>
                                    <strong>{l.display_name}</strong>
                                    <div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-tertiary)' }}>{l.sku}</div>
                                    {(l.reason_id || l.reason_comment) && (
                                        <div style={{ fontSize: 12, color: 'var(--text-tertiary)', marginTop: 2 }}>
                                            {reasonName(l.reason_id) ?? '—'}{l.reason_comment ? ` — ${l.reason_comment}` : ''}
                                        </div>
                                    )}
                                </div>
                            ),
                        },
                        { header: t('returns.column_quantity', 'Quantity'), align: 'right', render: (l) => l.quantity },
                        { header: t('returns.column_status', 'Status'), render: (l) => <Badge tone={lineStatusTones[l.status]}>{lineStatusLabels[l.status] ?? l.status}</Badge> },
                        { header: t('returns.column_amount', 'Amount'), align: 'right', render: (l) => l.gross_amount !== null ? money(l.gross_amount, order.currency_code, order.currency_decimal_places) : '—' },
                        {
                            header: t('returns.column_decision', 'Decision'), render: (l) => {
                                if (l.status !== 'pending') {
                                    return l.status === 'rejected' ? (l.decision_reason || '—') : (l.restock ? t('returns.restocked_label', 'Restocked: :qty', { qty: l.restocked_quantity }) : t('returns.not_restocked', 'Not restocked'));
                                }
                                const d = draft(l.id);

                                return (
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 220 }}>
                                        <Checkbox label={t('returns.include_in_decision', 'Include in decision')} checked={d.include} onChange={(checked) => setField(l.id, 'include', checked)} />
                                        {d.include && (
                                            <>
                                                <Select
                                                    value={d.decision}
                                                    options={[{ label: t('returns.decision_approve', 'Approve'), value: 'approved' }, { label: t('returns.decision_reject', 'Reject'), value: 'rejected' }]}
                                                    onChange={(e) => setField(l.id, 'decision', e.target.value)}
                                                />
                                                {d.decision === 'approved' && l.tracks_stock && (
                                                    <>
                                                        <Checkbox label={t('returns.restock_checkbox', 'Return to stock')} checked={d.restock} onChange={(checked) => setField(l.id, 'restock', checked)} />
                                                        {d.restock && <Input placeholder={t('returns.max_quantity_placeholder', '≤ :qty', { qty: l.quantity })} value={d.restocked_quantity} onChange={(e) => setField(l.id, 'restocked_quantity', e.target.value)} />}
                                                    </>
                                                )}
                                                {d.decision === 'rejected' && <Input placeholder={t('returns.rejection_reason_placeholder', 'Rejection reason')} value={d.decision_reason} onChange={(e) => setField(l.id, 'decision_reason', e.target.value)} />}
                                            </>
                                        )}
                                    </div>
                                );
                            },
                        },
                    ]} rows={returnRequest.lines} />

                    {pendingLines.length > 0 && (
                        <div style={{ marginTop: 20, borderTop: '1px solid var(--border-subtle)', paddingTop: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <Input
                                label={t('returns.delivery_refund_label', 'Additional delivery cost refund — gross (optional)')}
                                style={{ maxWidth: 260 }}
                                value={deliveryRefund}
                                onChange={(e) => setDeliveryRefund(e.target.value)}
                                helpText={order.delivery_gross_amount ? t('returns.delivery_refund_help', 'Order delivery: :amount', { amount: money(order.delivery_gross_amount, order.currency_code, order.currency_decimal_places) }) : t('returns.no_delivery_line', 'This order has no delivery line.')}
                            />
                            <div><Button type="button" loading={processing} onClick={submitDecisions}>{t('returns.save_decisions', 'Save decisions')}</Button></div>
                        </div>
                    )}
                </Card>

                {returnRequest.refunds.length > 0 && (
                    <Card title={t('returns.refunds_card_title', 'Amounts to refund')} description={t('returns.refunds_card_description', 'This action only records the decision — the money is sent by the seller outside the system.')}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {returnRequest.refunds.map((refund) => (
                                <div key={refund.id} style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 14, display: 'flex', flexDirection: 'column', gap: 10 }}>
                                    <dl className="grid grid-cols-2 sm:grid-cols-4" style={{ gap: 12, margin: 0 }}>
                                        <div><dt style={term}>{t('returns.refund_products_gross', 'Products (gross)')}</dt><dd style={value}>{money(refund.products_gross_amount, order.currency_code, order.currency_decimal_places)}</dd></div>
                                        <div><dt style={term}>{t('returns.refund_coupon_restored', 'Restored to coupon')}</dt><dd style={value}>{money(refund.coupon_restored_amount, order.currency_code, order.currency_decimal_places)}</dd></div>
                                        <div><dt style={term}>{t('returns.refund_delivery', 'Delivery')}</dt><dd style={value}>{money(refund.delivery_gross_amount, order.currency_code, order.currency_decimal_places)}</dd></div>
                                        <div><dt style={term}>{t('returns.refund_money_amount', 'To refund as money')}</dt><dd style={{ ...value, fontWeight: 700 }}>{money(refund.money_refund_amount, order.currency_code, order.currency_decimal_places)}</dd></div>
                                    </dl>
                                    {refund.paid_out_at ? (
                                        <Badge tone="success">{t('returns.paid_out_label', 'Paid out :date — ref. :reference', { date: formatDateTime(refund.paid_out_at), reference: refund.payout_reference })}</Badge>
                                    ) : (
                                        <div style={{ display: 'flex', gap: 8, alignItems: 'end', flexWrap: 'wrap' }}>
                                            <Input label={t('returns.payout_reference_label', 'Payout reference')} value={payoutDrafts[refund.id] || ''} onChange={(e) => setPayoutField(refund.id, e.target.value)} />
                                            <Button type="button" variant="outline" size="sm" onClick={() => submitPayout(refund.id)}>{t('returns.mark_paid_out', 'Mark as paid out')}</Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
