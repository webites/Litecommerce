import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import PageHeader from '@/Components/ui/PageHeader';
import Pagination from '@/Components/ui/Pagination';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';

const paymentTones = { unpaid: 'warning', paid: 'success', late_payment: 'danger', overpaid: 'danger' };

function date(value) { return value ? new Date(value).toLocaleString('pl-PL', { dateStyle: 'medium', timeStyle: 'short' }) : '—'; }
function money(value, currency, places) { const [whole, fraction = ''] = String(value).split('.'); return `${whole}${places ? `,${fraction.padEnd(places, '0').slice(0, places)}` : ''} ${currency}`; }

export default function Index({ orders }) {
    const t = useTranslate();
    const flash = usePage().props.flash;
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

    return (
        <AuthenticatedLayout header={<PageHeader title={t('orders.index_title', 'Orders')} description={t('orders.index_description', 'Immutable snapshots of purchases, payments and the handling history')} />}>
            <Head title={t('orders.index_title', 'Orders')} />
            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                <Card>
                    {orders.data.length === 0 ? (
                        <EmptyState icon={ReceiptText} title={t('orders.empty_title', 'No orders')} description={t('orders.empty_description', 'The first order will appear here once checkout is confirmed.')} />
                    ) : (
                        <>
                            <Table columns={[
                                { header: t('orders.column_number', 'Number'), render: (row) => <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12 }}>{row.id}</span> },
                                { header: t('orders.column_customer', 'Customer'), render: (row) => row.email },
                                { header: t('orders.column_status', 'Status'), render: (row) => <Badge>{orderLabels[row.status] ?? row.status}</Badge> },
                                { header: t('orders.column_payment', 'Payment'), render: (row) => <Badge tone={paymentTones[row.payment_status]}>{paymentLabels[row.payment_status] ?? row.payment_status}</Badge> },
                                { header: t('orders.column_value', 'Value'), align: 'right', render: (row) => money(row.total_gross_amount, row.currency_code, row.currency_decimal_places) },
                                { header: t('orders.column_placed_at', 'Placed'), render: (row) => date(row.created_at) },
                                { header: '', align: 'right', render: (row) => <Button href={route('panel.orders.show', row.id)} variant="outline" size="sm">{t('orders.view', 'View')}</Button> },
                            ]} rows={orders.data} />
                            <div style={{ marginTop: 18, display: 'flex', justifyContent: 'flex-end' }}><Pagination paginator={orders} /></div>
                        </>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
