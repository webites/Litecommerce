import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Pagination from '@/Components/ui/Pagination';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { formatDateTime } from '@/lib/format';


export default function Index({ returnRequests, reasons }) {
    const t = useTranslate();
    const flash = usePage().props.flash;
    const newReason = useForm({ key: '', name: '' });
    const createReason = (event) => {
        event.preventDefault();
        newReason.post(route('panel.returns.reasons.store'), { preserveScroll: true, onSuccess: () => newReason.reset() });
    };
    return (
        <AuthenticatedLayout header={<PageHeader title={t('returns.page_title', 'Returns')} description={t('returns.page_description', 'Return requests, decisions and payouts — recorded by staff.')} />}>
            <Head title={t('returns.page_title', 'Returns')} />
            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                <Card>
                    {returnRequests.data.length === 0 ? (
                        <EmptyState icon={RotateCcw} title={t('returns.empty_title', 'No return requests')} description={t('returns.empty_description', "Create a request from a paid order's details.")} />
                    ) : (
                        <>
                            <Table columns={[
                                { header: t('returns.column_order', 'Order'), render: (row) => <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12 }}>{row.order_number}</span> },
                                { header: t('returns.column_customer', 'Customer'), render: (row) => row.email },
                                { header: t('returns.column_status', 'Status'), render: (row) => <Badge tone={row.status === 'open' ? 'warning' : 'neutral'}>{row.status === 'open' ? t('returns.status_open', 'Open') : t('returns.status_closed', 'Closed')}</Badge> },
                                { header: t('returns.column_pending_count', 'Awaiting decision'), align: 'right', render: (row) => row.pending_count },
                                { header: t('returns.column_created_at', 'Requested'), render: (row) => formatDateTime(row.created_at) },
                                { header: '', align: 'right', render: (row) => <Button href={route('panel.returns.show', row.id)} variant="outline" size="sm">{t('returns.open_action', 'Open')}</Button> },
                            ]} rows={returnRequests.data} />
                            <div style={{ marginTop: 18, display: 'flex', justifyContent: 'flex-end' }}><Pagination paginator={returnRequests} /></div>
                        </>
                    )}
                </Card>

                <Card title={t('returns.reasons_card_title', 'Return reasons')} description={t('returns.reasons_card_description', 'The list used when recording a request.')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        {reasons.map((reason) => <ReasonRow key={reason.id} reason={reason} t={t} />)}
                        {reasons.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-tertiary)' }}>{t('returns.no_reasons_yet', 'No reason has been added yet.')}</div>}
                        <form onSubmit={createReason} style={{ display: 'flex', gap: 10, alignItems: 'end', borderTop: '1px solid var(--border-subtle)', paddingTop: 16, flexWrap: 'wrap' }}>
                            <Input
                                label={t('returns.fields.reason_key', 'Technical key')}
                                required
                                placeholder={t('returns.fields.reason_key_placeholder', 'e.g. damaged')}
                                value={newReason.data.key}
                                error={newReason.errors.key}
                                onChange={(e) => newReason.setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'))}
                            />
                            <Input label={t('returns.fields.name', 'Name')} required value={newReason.data.name} error={newReason.errors.name} onChange={(e) => newReason.setData('name', e.target.value)} />
                            <Button type="submit" loading={newReason.processing}>{t('returns.add_reason_submit', 'Add reason')}</Button>
                        </form>
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function ReasonRow({ reason, t }) {
    const form = useForm({ name: reason.name, active: reason.active });
    const save = () => form.put(route('panel.returns.reasons.update', reason.id), { preserveScroll: true });

    return (
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} style={{ flex: '1 1 240px' }} />
            <Checkbox label={t('returns.fields.active', 'Active')} checked={form.data.active} onChange={(checked) => form.setData('active', checked)} />
            <Button type="button" variant="outline" size="sm" loading={form.processing} onClick={save}>{t('returns.save', 'Save')}</Button>
        </div>
    );
}
