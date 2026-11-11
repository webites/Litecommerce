import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Pagination from '@/Components/ui/Pagination';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { formatMoney as money } from '@/lib/money';
import { formatDateTime } from '@/lib/format';

const paymentTones = { unpaid: 'warning', paid: 'success', late_payment: 'danger', overpaid: 'danger' };


export default function Index({ orders, filters }) {
    const t = useTranslate();
    const [query, setQuery] = useState(filters?.q ?? '');
    const search = (event) => {
        event.preventDefault();
        router.get(route('panel.orders.index'), query.trim() === '' ? {} : { q: query.trim() }, { preserveState: true });
    };
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
                    <form onSubmit={search} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', marginBottom: 16, maxWidth: 480 }}>
                        <div style={{ flex: 1 }}>
                            <Input type="search" label={t('orders.search_label', 'Search')} placeholder={t('orders.search_placeholder', 'Order number or e-mail')} value={query} onChange={(e) => setQuery(e.target.value)} />
                        </div>
                        <Button type="submit" variant="outline">{t('orders.search_action', 'Search')}</Button>
                    </form>
                    {orders.data.length === 0 ? (
                        <EmptyState icon={ReceiptText} title={filters?.q ? t('orders.search_empty_title', 'No matching orders') : t('orders.empty_title', 'No orders')} description={filters?.q ? t('orders.search_empty_description', 'Try a different number or e-mail address.') : t('orders.empty_description', 'The first order will appear here once checkout is confirmed.')} />
                    ) : (
                        <>
                            <Table columns={[
                                { header: t('orders.column_number', 'Number'), render: (row) => <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12 }}>{row.number}</span> },
                                { header: t('orders.column_customer', 'Customer'), render: (row) => row.email },
                                { header: t('orders.column_status', 'Status'), render: (row) => <Badge>{orderLabels[row.status] ?? row.status}</Badge> },
                                { header: t('orders.column_payment', 'Payment'), render: (row) => <Badge tone={paymentTones[row.payment_status]}>{paymentLabels[row.payment_status] ?? row.payment_status}</Badge> },
                                { header: t('orders.column_value', 'Value'), align: 'right', render: (row) => money(row.total_gross_amount, row.currency_code, row.currency_decimal_places) },
                                { header: t('orders.column_placed_at', 'Placed'), render: (row) => formatDateTime(row.created_at) },
                                { header: '', align: 'right', render: (row) => <Button href={route('panel.orders.show', row.id)} variant="outline" size="sm">{t('orders.view', 'View')}</Button> },
                            ]} rows={orders.data} cards />
                            <div style={{ marginTop: 18, display: 'flex', justifyContent: 'flex-end' }}><Pagination paginator={orders} /></div>
                        </>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
