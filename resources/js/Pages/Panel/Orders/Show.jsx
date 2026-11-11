import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PageHeader from '@/Components/ui/PageHeader';
import PanelSlot from '@/Components/ui/PanelSlot';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import Textarea from '@/Components/ui/Textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatMoney as money } from '@/lib/money';
import { formatAddress, formatDateTime, formatQuantity } from '@/lib/format';

const paymentTones = { unpaid: 'warning', paid: 'success', late_payment: 'danger', overpaid: 'danger' };
const manuallyConfirmableMethods = ['core.bank_transfer', 'core.cash_on_delivery', 'core.pay_on_pickup'];
const term = { fontSize: 12, color: 'var(--text-tertiary)', marginBottom: 4 };
const value = { margin: 0, color: 'var(--text-primary)', fontSize: 14 };
function localInput(v) { if (!v) return ''; const d = new Date(v); const pad = (n) => String(n).padStart(2, '0'); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`; }

export default function Show({ order }) {
    const t = useTranslate();
    const { auth, flash, errors } = usePage().props;
    const manual = useForm({ reference: '' });
    const cancellation = useForm({ reason: '' });
    const reopening = order.status === 'cancelled';
    const status = useForm({ status: reopening ? '' : order.status, reason: '', confirm_reopen: false });
    const [confirmingReopen, setConfirmingReopen] = useState(false);
    const submitStatus = () => {
        status.transform((data) => ({ ...data, confirm_reopen: reopening }));
        status.put(route('panel.orders.status.update', order.id), { onSuccess: () => setConfirmingReopen(false) });
    };
    const note = useForm({ body: '' });
    const customerMessage = useForm({ body: '' });
    const fulfillment = order.fulfillment;
    const ready = useForm({ pickup_deadline_at: localInput(fulfillment?.default_pickup_deadline_at) });
    const handover = useForm({ payment_reference: '', paid_at: '' });
    const digitalGrant = useForm({ reason: '' });
    const manageable = auth.can.manageOrders;
    const canManageRefunds = auth.can.manageRefunds;
    const [grantDrafts, setGrantDrafts] = useState({});
    const grantDraft = (grantId) => ({ until: '', max_downloads: '', reason: '', ...grantDrafts[grantId] });
    const setGrantDraftField = (grantId, field, value) => setGrantDrafts((c) => ({ ...c, [grantId]: { ...grantDraft(grantId), [field]: value } }));
    const extendGrant = (grantId) => router.put(route('panel.orders.digital-access.extend', [order.id, grantId]), { until: grantDraft(grantId).until || null }, { preserveScroll: true, preserveState: true });
    const setGrantLimit = (grantId) => router.put(route('panel.orders.digital-access.limit', [order.id, grantId]), { max_downloads: grantDraft(grantId).max_downloads || null }, { preserveScroll: true, preserveState: true });
    const revokeGrant = (grantId) => {
        const reason = grantDraft(grantId).reason;
        if (!reason) return;
        router.post(route('panel.orders.digital-access.revoke', [order.id, grantId]), { reason }, { preserveScroll: true, preserveState: true });
    };

    const orderLabels = {
        pending_payment: t('orders.statuses.pending_payment', 'Awaiting payment'),
        processing: t('orders.statuses.processing', 'Processing'),
        cancelled: t('orders.statuses.cancelled', 'Cancelled'),
        payment_review: t('orders.statuses.payment_review', 'Under review'),
    };
    const paymentLabels = {
        unpaid: t('orders.payment_statuses.unpaid', 'Unpaid'),
        paid: t('orders.payment_statuses.paid', 'Paid'),
        late_payment: t('orders.payment_statuses.late_payment', 'Late payment'),
        overpaid: t('orders.payment_statuses.overpaid', 'Overpaid'),
    };
    const fulfillmentLabels = {
        pending: t('orders.show.fulfillment_state_pending', 'Not prepared'),
        ready_for_pickup: t('orders.show.fulfillment_state_ready_for_pickup', 'Ready for pickup'),
        handed_over: t('orders.show.fulfillment_state_handed_over', 'Handed over'),
        pickup_expired: t('orders.show.fulfillment_state_pickup_expired', 'Pickup expired'),
    };
    const fulfillmentTones = { pending: 'neutral', ready_for_pickup: 'warning', handed_over: 'success', pickup_expired: 'danger' };
    const fulfillmentEventLabels = {
        ready_for_pickup: t('orders.show.fulfillment_event_ready_for_pickup', 'Marked ready for pickup'),
        handed_over: t('orders.show.fulfillment_event_handed_over', 'Handed over to the customer'),
        pickup_expired: t('orders.show.fulfillment_event_pickup_expired', 'Pickup deadline lapsed'),
    };
    const attemptLabels = {
        created: t('orders.attempt_statuses.created', 'Created'),
        pending: t('orders.attempt_statuses.pending', 'Pending'),
        succeeded: t('orders.attempt_statuses.succeeded', 'Succeeded'),
        failed: t('orders.attempt_statuses.failed', 'Failed'),
        cancelled: t('orders.attempt_statuses.cancelled', 'Cancelled'),
        overpayment: t('orders.attempt_statuses.overpayment', 'Overpaid'),
        late_payment: t('orders.attempt_statuses.late_payment', 'Late'),
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('orders.show.title', 'Order :id', { id: order.number })} description={t('orders.show.description', 'Purchase snapshot and the handling log')} actions={<Button href={route('panel.orders.index')} variant="outline" size="sm">{t('orders.show.back_to_list', 'Back to list')}</Button>} />}>
            <Head title={t('orders.show.title', 'Order :id', { id: order.number })} />
            <div style={{ padding: 24, maxWidth: 1100, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash.status && <Alert tone="success">{flash.status}</Alert>}
                {order.payment_status === 'late_payment' && <Alert tone="danger" title={t('orders.show.late_payment_alert_title', 'Late payment')}>{t('orders.show.late_payment_alert_body', 'Do not automatically resume fulfillment. Stock may have been sold again; this requires a manual decision.')}</Alert>}
                {order.payment_status === 'overpaid' && <Alert tone="danger" title={t('orders.show.overpaid_alert_title', 'Overpayment detected')}>{t('orders.show.overpaid_alert_body', 'The second successful payment did not trigger fulfillment again. Settle the overpayment manually.')}</Alert>}

                <Card title={t('orders.show.summary_card_title', 'Summary')}>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style={{ gap: 20, margin: 0 }}>
                        <div><dt style={term}>{t('orders.show.field_status', 'Status')}</dt><dd style={value}><Badge>{orderLabels[order.status] ?? order.status}</Badge></dd></div>
                        <div><dt style={term}>{t('orders.show.field_payment', 'Payment')}</dt><dd style={value}><Badge tone={paymentTones[order.payment_status]}>{paymentLabels[order.payment_status] ?? order.payment_status}</Badge></dd></div>
                        <div><dt style={term}>{t('orders.show.field_gross_total', 'Gross total')}</dt><dd style={value}>{money(order.total_gross_amount, order.currency_code, order.currency_decimal_places)}</dd></div>
                        <div><dt style={term}>{t('orders.show.field_method', 'Method')}</dt><dd style={value}>{order.payment_method_name}</dd></div>
                        <div><dt style={term}>{t('orders.show.field_placed_at', 'Placed')}</dt><dd style={value}>{formatDateTime(order.created_at)}</dd></div>
                        <div><dt style={term}>{t('orders.show.field_payment_deadline', 'Payment deadline')}</dt><dd style={value}>{formatDateTime(order.payment_deadline_at)}</dd></div>
                        <div><dt style={term}>{t('orders.show.field_paid_at', 'Paid')}</dt><dd style={value}>{formatDateTime(order.paid_at)}</dd></div>
                        <div><dt style={term}>{t('orders.show.field_mode', 'Mode')}</dt><dd style={value}><Badge tone={order.mode === 'test' ? 'warning' : 'neutral'}>{order.mode === 'test' ? t('orders.show.mode_test', 'Test') : t('orders.show.mode_live', 'Live')}</Badge></dd></div>
                    </dl>
                </Card>

                <Card title={t('orders.show.buyer_card_title', 'Buyer')}>
                    <dl className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 20, margin: 0 }}>
                        <div><dt style={term}>{t('orders.show.field_email', 'E-mail')}</dt><dd style={value}>{order.party?.email}</dd></div>
                        <div><dt style={term}>{t('orders.show.field_buyer_type', 'Type')}</dt><dd style={value}>{order.party?.buyer_type === 'company' ? t('orders.show.buyer_type_company', 'Company') : t('orders.show.buyer_type_private', 'Private individual')}</dd></div>
                        {order.party?.phone_formatted && <div><dt style={term}>{t('orders.show.field_phone', 'Phone')}</dt><dd style={value}>{order.party.phone_formatted}</dd></div>}
                        {order.party?.company_name && <div><dt style={term}>{t('orders.show.field_company', 'Company')}</dt><dd style={value}>{order.party.company_name}</dd></div>}
                        {order.party?.tax_identifier && <div><dt style={term}>{t('orders.show.field_tax_id', 'Tax ID')}</dt><dd style={value}>{order.party.tax_identifier}</dd></div>}
                        <div><dt style={term}>{t('orders.show.field_billing_address', 'Billing address')}</dt><dd style={value}>{formatAddress(order.party?.billing_address)}</dd></div>
                        {order.delivery && !order.delivery.pickup_point_snapshot && (
                            <div><dt style={term}>{t('orders.show.field_shipping_address', 'Shipping address')}</dt><dd style={value}>{formatAddress(order.party?.shipping_address ?? order.party?.billing_address)}</dd></div>
                        )}
                        <div>
                            <dt style={term}>{t('orders.show.field_delivery', 'Delivery')}</dt>
                            <dd style={value}>
                                {order.delivery ? `${order.delivery.name}${order.delivery.pickup_point_snapshot ? ` — ${order.delivery.pickup_point_snapshot.name}` : ''}` : t('orders.show.delivery_not_applicable', 'Not applicable (fully digital order)')}
                            </dd>
                        </div>
                    </dl>
                </Card>

                <Card title={t('orders.show.lines_card_title', 'Items')} description={t('orders.show.lines_card_description', 'Data and prices come from the snapshot taken at purchase time.')}>
                    <Table columns={[
                        { header: t('orders.show.column_product', 'Product'), render: (line) => <div><strong>{line.display_name}</strong><div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--text-tertiary)' }}>{line.sku}</div></div> },
                        { header: t('orders.show.column_type', 'Type'), render: (line) => line.product_type === 'physical' ? t('orders.show.type_physical', 'Physical') : t('orders.show.type_digital', 'Digital') },
                        { header: t('orders.show.column_quantity', 'Quantity'), align: 'right', render: (line) => formatQuantity(line.quantity) },
                        { header: t('orders.show.column_unit_price', 'Unit price'), align: 'right', render: (line) => money(line.unit_amount, order.currency_code, order.currency_decimal_places) },
                        { header: t('orders.show.column_gross', 'Gross'), align: 'right', render: (line) => money(line.line_gross_amount, order.currency_code, order.currency_decimal_places) },
                    ]} rows={order.lines} />
                    <div className="grid grid-cols-1 sm:grid-cols-4" style={{ gap: 12, marginTop: 20 }}>
                        <div><div style={term}>{t('orders.show.net_total', 'Net')}</div><strong>{money(order.total_net_amount, order.currency_code, order.currency_decimal_places)}</strong></div>
                        <div><div style={term}>{t('orders.show.vat_total', 'VAT')}</div><strong>{money(order.total_vat_amount, order.currency_code, order.currency_decimal_places)}</strong></div>
                        <div><div style={term}>{t('orders.show.delivery_gross', 'Delivery (gross)')}</div><strong>{order.delivery ? money(order.delivery.gross_amount, order.currency_code, order.currency_decimal_places) : '—'}</strong></div>
                        <div><div style={term}>{t('orders.show.grand_total', 'Gross total')}</div><strong>{money(order.total_gross_amount, order.currency_code, order.currency_decimal_places)}</strong></div>
                    </div>
                    {order.adjustments.length > 0 && (
                        <dl style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 4 }}>
                            {order.adjustments.map((adjustment) => (
                                <div key={adjustment.code} style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <dt style={term}>{adjustment.label}</dt>
                                    <dd style={value}>{money(adjustment.gross_amount, order.currency_code, order.currency_decimal_places)}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </Card>

                {order.coupon_usages.length > 0 && (
                    <Card title={t('orders.show.coupons_card_title', 'Coupons')} description={t('orders.show.coupons_card_description', 'Discount applied when the order was placed — the returned part goes back to the same code only once the return lines are approved.')}>
                        <Table columns={[
                            { header: t('orders.show.column_code', 'Code'), render: (u) => <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 600 }}>{u.coupon_code}</span> },
                            { header: t('orders.show.column_product_discount', 'Product discount'), align: 'right', render: (u) => money(u.product_gross_amount, order.currency_code, order.currency_decimal_places) },
                            { header: t('orders.show.column_delivery_discount', 'Delivery discount'), align: 'right', render: (u) => money(u.delivery_gross_amount, order.currency_code, order.currency_decimal_places) },
                        ]} rows={order.coupon_usages} />
                    </Card>
                )}

                {manageable && order.can_grant_digital_access && (
                    <Card title={t('orders.show.digital_grant_card_title', 'Grant digital access after late payment')} description={t('orders.show.digital_grant_card_description', 'The payment arrived after the order lapsed. Granting access is a staff decision: it gives the customer the download links and is recorded in the audit log.')}>
                        {errors.digital_grant && <div style={{ marginBottom: 14 }}><Alert tone="danger">{errors.digital_grant}</Alert></div>}
                        <form onSubmit={(e) => { e.preventDefault(); digitalGrant.post(route('panel.orders.digital-access.grant', order.id), { preserveScroll: true, onSuccess: () => digitalGrant.reset() }); }} style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                            <Input id="digital_grant_reason" label={t('orders.show.digital_grant_reason', 'Reason')} value={digitalGrant.data.reason} onChange={(e) => digitalGrant.setData('reason', e.target.value)} error={digitalGrant.errors.reason} style={{ flex: '1 1 280px' }} />
                            <Button type="submit" loading={digitalGrant.processing}>{t('orders.show.digital_grant_submit', 'Grant access and email the customer')}</Button>
                        </form>
                    </Card>
                )}

                {order.digital_access.length > 0 && (
                    <Card title={t('orders.show.digital_access_card_title', 'Digital access')} description={t('orders.show.digital_access_card_description', 'A downloaded copy cannot be undone — revoking access only blocks further downloads.')}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            {order.digital_access.map((grant) => {
                                const draft = grantDraft(grant.id);

                                return (
                                    <div key={grant.id} style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-md)', padding: 14, display: 'flex', flexDirection: 'column', gap: 10 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                                            <div>
                                                <strong>{grant.file_label}</strong>
                                                <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{grant.original_filename}</div>
                                            </div>
                                            <Badge tone={grant.revoked_at ? 'danger' : grant.usable ? 'success' : 'warning'}>
                                                {grant.revoked_at ? t('orders.show.grant_status_revoked', 'Revoked') : grant.usable ? t('orders.show.grant_status_active', 'Active') : t('orders.show.grant_status_exhausted', 'Exhausted/expired')}
                                            </Badge>
                                        </div>
                                        <dl className="grid grid-cols-2 sm:grid-cols-4" style={{ gap: 12, margin: 0 }}>
                                            <div><dt style={term}>{t('orders.show.field_granted_at', 'Granted')}</dt><dd style={value}>{formatDateTime(grant.granted_at)}</dd></div>
                                            <div><dt style={term}>{t('orders.show.field_valid_until', 'Valid until')}</dt><dd style={value}>{grant.expires_at ? formatDateTime(grant.expires_at) : t('orders.show.valid_forever', 'indefinitely')}</dd></div>
                                            <div><dt style={term}>{t('orders.show.field_downloads', 'Downloads')}</dt><dd style={value}>{grant.downloads_used}{grant.max_downloads !== null ? ` / ${grant.max_downloads}` : t('orders.show.downloads_unlimited', ' / unlimited')}</dd></div>
                                            {grant.revoked_reason && <div><dt style={term}>{t('orders.show.field_revoke_reason', 'Revocation reason')}</dt><dd style={value}>{grant.revoked_reason}</dd></div>}
                                        </dl>
                                        {manageable && !grant.revoked_at && (
                                            <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 10, alignItems: 'end', borderTop: '1px solid var(--border-subtle)', paddingTop: 12 }}>
                                                <div style={{ display: 'flex', gap: 8, alignItems: 'end' }}>
                                                    <Input type="datetime-local" label={t('orders.show.field_new_expiry', 'New expiry date')} value={draft.until} onChange={(e) => setGrantDraftField(grant.id, 'until', e.target.value)} />
                                                    <Button type="button" variant="outline" size="sm" onClick={() => extendGrant(grant.id)}>{t('orders.show.extend', 'Extend')}</Button>
                                                </div>
                                                <div style={{ display: 'flex', gap: 8, alignItems: 'end' }}>
                                                    <Input type="number" min="0" label={t('orders.show.field_new_limit', 'New download limit')} value={draft.max_downloads} onChange={(e) => setGrantDraftField(grant.id, 'max_downloads', e.target.value)} />
                                                    <Button type="button" variant="outline" size="sm" onClick={() => setGrantLimit(grant.id)}>{t('orders.show.save', 'Save')}</Button>
                                                </div>
                                                <div style={{ display: 'flex', gap: 8, alignItems: 'end' }}>
                                                    <Input label={t('orders.show.field_revoke_reason', 'Revocation reason')} value={draft.reason} onChange={(e) => setGrantDraftField(grant.id, 'reason', e.target.value)} />
                                                    <Button type="button" variant="destructive" size="sm" onClick={() => revokeGrant(grant.id)}>{t('orders.show.revoke', 'Revoke access')}</Button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                )}

                {canManageRefunds && (order.payment_status === 'paid' || order.payment_status === 'overpaid' || order.payment_status === 'late_payment' || order.returns.length > 0) && (
                    <Card
                        title={t('orders.show.returns_card_title', 'Returns')}
                        action={order.payment_status !== 'unpaid' && <Button href={route('panel.orders.returns.create', order.id)} variant="outline" size="sm">{t('orders.show.new_return', 'New return request')}</Button>}
                    >
                        {order.returns.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('orders.show.no_returns', 'No return requests for this order.')}</div>}
                        {order.returns.length > 0 && (
                            <Table columns={[
                                { header: t('orders.show.column_requested_at', 'Requested'), render: (r) => formatDateTime(r.created_at) },
                                { header: t('orders.show.column_status', 'Status'), render: (r) => <Badge tone={r.status === 'open' ? 'warning' : 'neutral'}>{r.status === 'open' ? t('orders.show.return_status_open', 'Open') : t('orders.show.return_status_closed', 'Closed')}</Badge> },
                                { header: t('orders.show.column_pending', 'Pending decision'), align: 'right', render: (r) => r.pending_count },
                                { header: t('orders.show.column_approved', 'Approved'), align: 'right', render: (r) => r.approved_count },
                                { header: '', align: 'right', render: (r) => <Button href={route('panel.returns.show', r.id)} variant="outline" size="sm">{t('orders.show.open', 'Open')}</Button> },
                            ]} rows={order.returns} />
                        )}
                    </Card>
                )}

                {fulfillment && (
                    <Card title={t('orders.show.fulfillment_card_title', 'Pickup fulfillment')} description={t('orders.show.fulfillment_card_description', 'Tracks the physical hand-over; it does not change the order status.')}>
                        {errors.fulfillment && <div style={{ marginBottom: 14 }}><Alert tone="danger">{errors.fulfillment}</Alert></div>}
                        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style={{ gap: 20, margin: 0 }}>
                            <div><dt style={term}>{t('orders.show.field_status', 'Status')}</dt><dd style={value}><Badge tone={fulfillmentTones[fulfillment.state]}>{fulfillmentLabels[fulfillment.state] ?? fulfillment.state}</Badge></dd></div>
                            <div><dt style={term}>{t('orders.show.fulfillment_ready_at', 'Ready since')}</dt><dd style={value}>{formatDateTime(fulfillment.ready_for_pickup_at)}</dd></div>
                            <div><dt style={term}>{t('orders.show.fulfillment_deadline', 'Pickup deadline')}</dt><dd style={value}>{formatDateTime(fulfillment.pickup_deadline_at)}</dd></div>
                            <div><dt style={term}>{t('orders.show.fulfillment_handed_over_at', 'Handed over')}</dt><dd style={value}>{formatDateTime(fulfillment.handed_over_at)}</dd></div>
                        </dl>
                        {manageable && fulfillment.can_mark_ready && (
                            <form onSubmit={(e) => { e.preventDefault(); ready.post(route('panel.orders.fulfillment.ready', order.id), { preserveScroll: true }); }} style={{ marginTop: 18, display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                                <Input id="fulfillment_pickup_deadline" label={t('orders.show.fulfillment_field_deadline', 'Pickup deadline (optional)')} type="datetime-local" value={ready.data.pickup_deadline_at} onChange={(e) => ready.setData('pickup_deadline_at', e.target.value)} error={ready.errors.pickup_deadline_at} helpText={fulfillment.pay_on_pickup ? t('orders.show.fulfillment_deadline_hint_unpaid', 'An uncollected unpaid order is cancelled after this time and its stock is released.') : undefined} style={{ flex: '1 1 280px' }} />
                                <Button type="submit" loading={ready.processing}>{t('orders.show.fulfillment_mark_ready', 'Mark ready for pickup')}</Button>
                            </form>
                        )}
                        {manageable && fulfillment.can_hand_over && (
                            <form onSubmit={(e) => { e.preventDefault(); handover.post(route('panel.orders.fulfillment.handover', order.id), { preserveScroll: true }); }} style={{ marginTop: 18, display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                                {fulfillment.requires_payment_on_handover && (
                                    <Input id="fulfillment_payment_reference" label={t('orders.show.field_full_payment_reference', 'Full payment reference')} value={handover.data.payment_reference} onChange={(e) => handover.setData('payment_reference', e.target.value)} error={handover.errors.payment_reference} style={{ flex: '1 1 280px' }} />
                                )}
                                <Button type="submit" loading={handover.processing}>{fulfillment.requires_payment_on_handover ? t('orders.show.fulfillment_hand_over_paid', 'Take payment and hand over') : t('orders.show.fulfillment_hand_over', 'Confirm hand-over')}</Button>
                            </form>
                        )}
                        {fulfillment.events.length > 0 && (
                            <div style={{ marginTop: 18 }}>
                                <Table columns={[
                                    { header: t('orders.show.column_when', 'When'), render: (e) => formatDateTime(e.occurred_at) },
                                    { header: t('orders.show.fulfillment_column_event', 'Event'), render: (e) => fulfillmentEventLabels[e.type] ?? e.type },
                                    { header: t('orders.show.column_author', 'Author'), render: (e) => e.actor_type === 'staff' ? t('orders.show.staff_member', 'Staff member #:id', { id: e.actor_id }) : t('orders.show.system', 'System') },
                                ]} rows={fulfillment.events} />
                            </div>
                        )}
                    </Card>
                )}

                <Card title={t('orders.show.payment_attempts_card_title', 'Payment attempts')}>
                    <Table columns={[
                        { header: t('orders.show.column_method', 'Method'), render: (a) => a.method_name || a.method_id },
                        { header: t('orders.show.column_transaction', 'Transaction'), render: (a) => <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12 }}>{a.transaction_id ?? '—'}</span> },
                        { header: t('orders.show.column_status', 'Status'), render: (a) => <Badge>{attemptLabels[a.status] ?? a.status}</Badge> },
                        { header: t('orders.show.column_amount', 'Amount'), align: 'right', render: (a) => money(a.amount, a.currency_code, order.currency_decimal_places) },
                    ]} rows={order.payment_attempts} />
                    {manageable && order.payment_status === 'unpaid' && manuallyConfirmableMethods.includes(order.payment_method_id) && (
                        <form onSubmit={(e) => { e.preventDefault(); manual.post(route('panel.orders.payments.manual', order.id)); }} style={{ marginTop: 18, display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                            <Input label={t('orders.show.field_full_payment_reference', 'Full payment reference')} value={manual.data.reference} onChange={(e) => manual.setData('reference', e.target.value)} error={manual.errors.reference} style={{ flex: '1 1 280px' }} />
                            <Button type="submit" loading={manual.processing}>{t('orders.show.confirm_full_payment', 'Confirm full payment')}</Button>
                        </form>
                    )}
                    {manageable && order.payment_status === 'unpaid' && !manuallyConfirmableMethods.includes(order.payment_method_id) && (
                        <div style={{ marginTop: 18 }}><Button onClick={() => manual.post(route('panel.orders.payments.retry', order.id))}>{t('orders.show.retry_payment', 'Retry payment')}</Button></div>
                    )}
                </Card>

                {manageable && <Card title={t('orders.show.handling_card_title', 'Order handling')} description={t('orders.show.handling_card_description', 'Changing the status does not perform a payment, fulfillment, or a return.')}>
                    <form onSubmit={(e) => { e.preventDefault(); if (reopening && status.data.status !== '') { setConfirmingReopen(true); } else { submitStatus(); } }} className="grid grid-cols-1 sm:grid-cols-2" style={{ gap: 12 }}>
                        <Select label={t('orders.show.field_status', 'Status')} options={order.available_statuses.filter((option) => option.value !== 'cancelled')} value={status.data.status} placeholder={reopening ? t('orders.show.field_status', 'Status') : undefined} onChange={(event) => status.setData('status', event.target.value)} error={status.errors.status} />
                        <Input label={t('orders.show.field_reason_optional', 'Reason (optional)')} value={status.data.reason} onChange={(e) => status.setData('reason', e.target.value)} error={status.errors.reason} />
                        <div><Button type="submit" loading={status.processing}>{t('orders.show.change_status', 'Change status')}</Button></div>
                    </form>
                    {order.payment_status === 'unpaid' && order.status !== 'cancelled' && <form onSubmit={(e) => { e.preventDefault(); cancellation.post(route('panel.orders.cancel', order.id)); }} style={{ marginTop: 18, display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <Input label={t('orders.show.field_cancel_reason', 'Cancellation reason')} value={cancellation.data.reason} onChange={(e) => cancellation.setData('reason', e.target.value)} error={cancellation.errors.reason} style={{ flex: '1 1 280px' }} />
                        <Button type="submit" variant="destructive" loading={cancellation.processing}>{t('orders.show.cancel_unpaid', 'Cancel unpaid')}</Button>
                    </form>}
                </Card>}

                <Modal show={confirmingReopen} title={t('orders.show.reopen_title', 'Reopen the cancelled order?')} onClose={() => setConfirmingReopen(false)}>
                    <p style={{ margin: '0 0 16px', fontSize: 14, color: 'var(--text-primary)' }}>{t('orders.show.reopen_body', 'The order was cancelled, so its stock reservations are released.')}</p>
                    <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                        <Button type="button" variant="outline" onClick={() => setConfirmingReopen(false)}>{t('orders.show.reopen_back', 'Go back')}</Button>
                        <Button type="button" variant="destructive" loading={status.processing} onClick={submitStatus}>{t('orders.show.reopen_confirm', 'Yes, change status')}</Button>
                    </div>
                </Modal>

                <PanelSlot name="orders.show.documents" params={{ order_id: order.id }} />

                <Card title={t('orders.show.history_card_title', 'Status history')}>
                    <Table columns={[
                        { header: t('orders.show.column_when', 'When'), render: (h) => formatDateTime(h.occurred_at) },
                        { header: t('orders.show.column_change', 'Change'), render: (h) => `${h.old_status ? (orderLabels[h.old_status] ?? h.old_status) : t('orders.show.creation', 'Creation')} → ${orderLabels[h.new_status] ?? h.new_status}` },
                        { header: t('orders.show.column_author', 'Author'), render: (h) => h.actor_type === 'staff' ? t('orders.show.staff_member', 'Staff member #:id', { id: h.actor_id }) : t('orders.show.system', 'System') },
                        { header: t('orders.show.column_reason', 'Reason'), render: (h) => h.reason ?? '—' },
                    ]} rows={order.status_history} />
                </Card>

                <Card title={t('orders.show.message_card_title', 'Message to customer')} description={t('orders.show.message_card_description', 'The message will be sent via the e-mail queue and stays linked to the order.')}>
                    {order.customer_messages?.map((message) => <div key={message.id} style={{ padding: '8px 0', borderBottom: '1px solid var(--border-subtle)', fontSize: 13 }}>
                        <div style={{ whiteSpace: 'pre-wrap', marginBottom: 4 }}>{message.body}</div>
                        {formatDateTime(message.created_at)} · {t('orders.show.staff_member', 'Staff member #:id', { id: message.actor_id })} · <Badge>{t(`email.ui.statuses.${message.status}`, message.status)}</Badge>
                    </div>)}
                    {manageable && <form onSubmit={(e) => { e.preventDefault(); customerMessage.post(route('panel.orders.customer-messages.store', order.id), { onSuccess: () => customerMessage.reset() }); }} style={{ marginTop: 16 }}>
                        <Textarea label={t('orders.show.field_message_body', 'Message body')} value={customerMessage.data.body} onChange={(e) => customerMessage.setData('body', e.target.value)} error={customerMessage.errors.body} />
                        <div style={{ marginTop: 10 }}><Button type="submit" loading={customerMessage.processing}>{t('orders.show.send_message', 'Send message')}</Button></div>
                    </form>}
                </Card>

                <Card title={t('orders.show.notes_card_title', 'Internal notes')}>
                    {order.notes.map((n) => <div key={n.id} style={{ padding: '10px 0', borderBottom: '1px solid var(--border-subtle)' }}><div>{n.body}</div><small style={{ color: 'var(--text-tertiary)' }}>{formatDateTime(n.created_at)} · {t('orders.show.staff_member', 'Staff member #:id', { id: n.author_id })}</small></div>)}
                    {manageable && <form onSubmit={(e) => { e.preventDefault(); note.post(route('panel.orders.notes.store', order.id), { onSuccess: () => note.reset() }); }} style={{ marginTop: 16 }}>
                        <Textarea label={t('orders.show.field_new_note', 'New note')} value={note.data.body} onChange={(e) => note.setData('body', e.target.value)} error={note.errors.body} />
                        <div style={{ marginTop: 10 }}><Button type="submit" loading={note.processing}>{t('orders.show.add_note', 'Add note')}</Button></div>
                    </form>}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
