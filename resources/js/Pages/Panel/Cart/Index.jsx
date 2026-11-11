import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import EmptyState from '@/Components/ui/EmptyState';
import PageHeader from '@/Components/ui/PageHeader';
import Pagination from '@/Components/ui/Pagination';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { formatDateTime } from '@/lib/format';

const statusTone = { active: 'success', abandoned: 'warning', checked_out: 'neutral' };

export default function Index({ carts }) {
    const t = useTranslate();
    const flash = usePage().props.flash;
    const statusLabel = { active: t('cart.status.active'), abandoned: t('cart.status.abandoned'), checked_out: t('cart.status.checked_out') };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('cart.index.title')} description={t('cart.index.description')} />}>
            <Head title={t('cart.index.title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                {carts.data.length === 0 ? (
                    <div style={{ border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-card)', background: 'var(--bg-surface)' }}>
                        <EmptyState icon={ShoppingCart} title={t('cart.index.empty_title')} description={t('cart.index.empty_description')} />
                    </div>
                ) : (
                    <>
                        <Table
                            columns={[
                                { header: t('cart.index.columns.owner'), key: 'owner' },
                                { header: t('cart.index.columns.currency'), key: 'currency_code' },
                                { header: t('cart.index.columns.items'), align: 'right', key: 'items_count' },
                                { header: t('cart.index.columns.status'), render: (r) => <Badge tone={statusTone[r.status] ?? 'neutral'}>{statusLabel[r.status] ?? r.status}</Badge> },
                                { header: t('cart.index.columns.last_activity'), render: (r) => formatDateTime(r.last_activity_at) },
                                { header: '', align: 'right', render: (r) => <Button href={route('panel.cart.show', r.id)} variant="outline" size="sm">{t('cart.index.view')}</Button> },
                            ]}
                            rows={carts.data}
                        />
                        <Pagination paginator={carts} />
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
