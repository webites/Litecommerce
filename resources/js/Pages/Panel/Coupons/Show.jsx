import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';
import { formatMoney as money } from '@/lib/money';
import { formatDateTime } from '@/lib/format';

export default function Show({ coupon, base_currency_code: baseCurrencyCode, ledger, reservations }) {
    const t = useTranslate();
    const status = usePage().props.flash?.status;

    const ledgerTypeLabels = {
        issue: t('coupons.ledger_types.issue', 'Issue'),
        usage: t('coupons.ledger_types.usage', 'Usage'),
        restore: t('coupons.ledger_types.restore', 'Restore'),
        adjustment: t('coupons.ledger_types.adjustment', 'Adjustment'),
    };
    const reservationStateLabels = {
        pending: t('coupons.reservation_states.pending', 'Pending'),
        confirmed: t('coupons.reservation_states.confirmed', 'Confirmed'),
        released: t('coupons.reservation_states.released', 'Released'),
        expired: t('coupons.reservation_states.expired', 'Expired'),
    };
    const reservationStateTone = { pending: 'brand', confirmed: 'success', released: 'neutral', expired: 'neutral' };

    const editForm = useForm({
        status: coupon.status, pin: '', expires_at: coupon.expires_at ? coupon.expires_at.slice(0, 10) : '',
        customer_account_id: coupon.customer_account_id ?? '', note: coupon.note ?? '',
        combinable_with_promotional: coupon.combinable_with_promotional,
        combinable_with_other_coupons: coupon.combinable_with_other_coupons,
        applies_to_delivery: coupon.applies_to_delivery,
    });
    const saveEdit = (event) => {
        event.preventDefault();
        editForm.put(route('panel.coupons.update', coupon.id), { preserveScroll: true });
    };

    const topUpForm = useForm({ amount: '', reason: '' });
    const submitTopUp = (event) => {
        event.preventDefault();
        topUpForm.post(route('panel.coupons.top-up', coupon.id), { preserveScroll: true, onSuccess: () => topUpForm.reset() });
    };

    const resetPinForm = useForm({});
    const resetPin = () => resetPinForm.post(route('panel.coupons.reset-pin', coupon.id), { preserveScroll: true });

    return (
        <AuthenticatedLayout header={<PageHeader title={coupon.code} description={t('coupons.show.header_description', 'Details, balance and history for this code.')} />}>
            <Head title={t('coupons.show.head_title', 'Coupon :code', { code: coupon.code })} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 1100 }}>
                {status && <Alert tone="success">{status}</Alert>}

                <Card title={t('coupons.show.balance_title', 'Balance')}>
                    <div style={{ display: 'flex', gap: 32 }}>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('coupons.show.available', 'Available')}</div>
                            <div style={{ fontFamily: 'var(--font-display)', fontSize: 22, fontWeight: 700 }}>{money(coupon.available_balance, baseCurrencyCode)}</div>
                        </div>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('coupons.show.reserved', 'Reserved')}</div>
                            <div style={{ fontSize: 18 }}>{money(coupon.reserved_amount, baseCurrencyCode)}</div>
                        </div>
                        <div>
                            <div style={{ fontSize: 12, color: 'var(--text-tertiary)' }}>{t('coupons.show.total_spent', 'Total spent')}</div>
                            <div style={{ fontSize: 18 }}>{money(coupon.balance, baseCurrencyCode)}</div>
                        </div>
                    </div>

                    <form onSubmit={submitTopUp} style={{ display: 'flex', gap: 12, alignItems: 'flex-end', marginTop: 16, borderTop: '1px solid var(--border-subtle)', paddingTop: 16 }}>
                        <Input label={t('coupons.show.top_up_label', 'Add balance (:currency)', { currency: baseCurrencyCode ?? '' })} value={topUpForm.data.amount} error={topUpForm.errors.amount} onChange={(e) => topUpForm.setData('amount', e.target.value)} />
                        <Input label={t('coupons.show.top_up_reason', 'Reason')} placeholder={t('coupons.show.top_up_reason_placeholder', 'Optional')} value={topUpForm.data.reason} error={topUpForm.errors.reason} onChange={(e) => topUpForm.setData('reason', e.target.value)} />
                        <Button type="submit" variant="outline" loading={topUpForm.processing}>{t('coupons.show.top_up_submit', 'Top up')}</Button>
                    </form>
                </Card>

                <Card title={t('coupons.show.settings_title', 'Settings')}>
                    <form onSubmit={saveEdit} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 12 }}>
                            <Select
                                label={t('coupons.show.status_label', 'Status')} value={editForm.data.status} error={editForm.errors.status}
                                options={[
                                    { value: 'active', label: t('coupons.show.status_active', 'Active') },
                                    { value: 'blocked', label: t('coupons.show.status_blocked', 'Blocked') },
                                ]}
                                onChange={(e) => editForm.setData('status', e.target.value)}
                            />
                            <Input
                                label={t('coupons.fields.valid_until', 'Valid until')} type="date" value={editForm.data.expires_at} error={editForm.errors.expires_at}
                                onChange={(e) => editForm.setData('expires_at', e.target.value)}
                            />
                            <Input
                                label={t('coupons.fields.customer_account_id', 'Customer account ID')} placeholder={t('coupons.fields.customer_account_id_placeholder', 'Any customer')} value={editForm.data.customer_account_id}
                                error={editForm.errors.customer_account_id} onChange={(e) => editForm.setData('customer_account_id', e.target.value)}
                                helpText={coupon.customer_email ?? undefined}
                            />
                            <Input
                                label={t('coupons.show.pin_new', 'New PIN')} placeholder={coupon.has_pin ? t('coupons.show.pin_placeholder_change', 'Leave unchanged') : t('coupons.fields.pin_placeholder_none', 'No PIN')} value={editForm.data.pin}
                                error={editForm.errors.pin} onChange={(e) => editForm.setData('pin', e.target.value)}
                                helpText={t('coupons.show.pin_help', 'Enter to set or change; leave empty to keep the current one.')}
                            />
                        </div>
                        <Input label={t('coupons.show.note', 'Note')} value={editForm.data.note} error={editForm.errors.note} onChange={(e) => editForm.setData('note', e.target.value)} />
                        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                            <Checkbox label={t('coupons.fields.combinable_with_promotional', 'Combines with promotional price')} checked={editForm.data.combinable_with_promotional} onChange={(v) => editForm.setData('combinable_with_promotional', v)} />
                            <Checkbox label={t('coupons.fields.combinable_with_other_coupons', 'Combines with other coupons')} checked={editForm.data.combinable_with_other_coupons} onChange={(v) => editForm.setData('combinable_with_other_coupons', v)} />
                            <Checkbox label={t('coupons.fields.applies_to_delivery', 'Can reduce delivery')} checked={editForm.data.applies_to_delivery} onChange={(v) => editForm.setData('applies_to_delivery', v)} />
                        </div>
                        {coupon.has_pin && coupon.pin_locked && (
                            <Alert tone="danger">
                                {t('coupons.show.pin_locked_message', 'PIN locked after :attempts failed attempts.', { attempts: coupon.failed_pin_attempts })}{' '}
                                <button type="button" onClick={resetPin} style={{ color: 'inherit', textDecoration: 'underline', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                                    {t('coupons.show.reset_pin_attempts', 'Reset attempt counter')}
                                </button>
                            </Alert>
                        )}
                        <div><Button type="submit" loading={editForm.processing}>{t('coupons.show.save', 'Save')}</Button></div>
                    </form>
                </Card>

                <Card title={t('coupons.show.ledger_title', 'Balance history')}>
                    <Table
                        columns={[
                            { header: t('coupons.show.ledger_column_date', 'Date'), render: (e) => formatDateTime(e.occurred_at) },
                            { header: t('coupons.show.ledger_column_type', 'Type'), render: (e) => ledgerTypeLabels[e.type] ?? e.type },
                            { header: t('coupons.show.ledger_column_amount', 'Amount'), align: 'right', render: (e) => money(e.amount, baseCurrencyCode) },
                            { header: t('coupons.show.ledger_column_reference', 'Reference'), render: (e) => (e.reference_type ? `${e.reference_type} #${e.reference_id}` : '—') },
                            { header: t('coupons.show.ledger_column_reason', 'Reason'), render: (e) => e.reason ?? '—' },
                        ]}
                        rows={ledger}
                    />
                </Card>

                <Card title={t('coupons.show.reservations_title', 'Reservations')}>
                    <Table
                        columns={[
                            { header: t('coupons.show.reservations_column_amount', 'Amount'), align: 'right', render: (r) => money(r.amount, baseCurrencyCode) },
                            { header: t('coupons.show.reservations_column_state', 'State'), render: (r) => <Badge tone={reservationStateTone[r.state] ?? 'neutral'}>{reservationStateLabels[r.state] ?? r.state}</Badge> },
                            { header: t('coupons.show.reservations_column_reference', 'Reference'), render: (r) => `${r.reference_type} #${r.reference_id}` },
                            { header: t('coupons.show.reservations_column_expires', 'Expires'), render: (r) => formatDateTime(r.expires_at, t('coupons.fields.valid_until_unlimited', 'Unlimited')) },
                        ]}
                        rows={reservations}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
