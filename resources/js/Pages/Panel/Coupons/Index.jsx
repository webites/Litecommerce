import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import Input from '@/Components/ui/Input';
import PageHeader from '@/Components/ui/PageHeader';
import Pagination from '@/Components/ui/Pagination';
import Switch from '@/Components/ui/Switch';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Ticket } from 'lucide-react';
import { formatMoney as money } from '@/lib/money';
import { formatDate } from '@/lib/format';

function statusBadge(coupon, t) {
    if (coupon.is_expired) return <Badge tone="neutral" dot>{t('coupons.status_badges.expired')}</Badge>;
    if (coupon.status === 'blocked') return <Badge tone="danger" dot>{t('coupons.status_badges.blocked')}</Badge>;

    return <Badge tone="success" dot>{t('coupons.status_badges.active')}</Badge>;
}

export default function Index({ coupons, base_currency_code: baseCurrencyCode, settings }) {
    const flash = usePage().props.flash;
    const t = useTranslate();

    const createForm = useForm({
        code: '', pin: '', initial_balance: '', expires_at: '', customer_account_id: '',
        combinable_with_promotional: true, combinable_with_other_coupons: true, applies_to_delivery: false, note: '',
    });
    const submitCreate = (event) => {
        event.preventDefault();
        createForm.post(route('panel.coupons.store'), { preserveScroll: true, onSuccess: () => createForm.reset() });
    };

    const toggleModuleEnabled = () => {
        router.put(route('panel.coupons.settings.update'), { enabled: !settings.enabled }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('coupons.page_title')} description={t('coupons.page_description')} />}>
            <Head title={t('coupons.page_title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}

                <Card
                    title={t('coupons.module_card.title')}
                    description={t('coupons.module_card.description')}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <Switch checked={settings.enabled} onChange={toggleModuleEnabled} />
                        <span style={{ fontSize: 14, color: 'var(--text-secondary)' }}>
                            {settings.enabled ? t('coupons.module_card.enabled') : t('coupons.module_card.disabled')}
                        </span>
                    </div>
                </Card>

                <Card title={t('coupons.list.title')}>
                    {coupons.data.length === 0 ? (
                        <EmptyState
                            icon={Ticket}
                            title={t('coupons.list.empty_title')}
                            description={t('coupons.list.empty_description')}
                        />
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <Table
                                columns={[
                                    { header: t('coupons.list.column_code'), render: (c) => <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 600 }}>{c.code}</span> },
                                    {
                                        header: t('coupons.list.column_available_balance'), align: 'right',
                                        render: (c) => money(c.available_balance, baseCurrencyCode),
                                    },
                                    { header: t('coupons.list.column_status'), render: (c) => statusBadge(c, t) },
                                    { header: t('coupons.list.column_valid_until'), render: (c) => formatDate(c.expires_at, t('coupons.fields.valid_until_unlimited')) },
                                    { header: t('coupons.list.column_account'), render: (c) => c.customer_email ?? t('coupons.list.column_account_any') },
                                    { header: t('coupons.list.column_pin'), render: (c) => (c.has_pin ? (c.pin_locked ? <Badge tone="danger">{t('coupons.status_badges.blocked')}</Badge> : <Badge tone="brand">{t('coupons.fields.pin')}</Badge>) : '—') },
                                    {
                                        header: '', align: 'right',
                                        render: (c) => (
                                            <Link href={route('panel.coupons.show', c.id)} style={{ color: 'var(--text-link)', fontSize: 13, fontWeight: 600 }}>
                                                {t('coupons.list.details')}
                                            </Link>
                                        ),
                                    },
                                ]}
                                rows={coupons.data}
                            />
                            <Pagination paginator={coupons} />
                        </div>
                    )}

                    <form onSubmit={submitCreate} style={{ display: 'flex', flexDirection: 'column', gap: 12, borderTop: '1px solid var(--border-subtle)', marginTop: 16, paddingTop: 16 }}>
                        <div style={{ fontSize: 14, fontWeight: 600 }}>{t('coupons.list.create_title')}</div>
                        <div className="grid grid-cols-1 sm:grid-cols-3" style={{ gap: 12 }}>
                            <Input
                                label={t('coupons.fields.code')} placeholder={t('coupons.fields.code_placeholder')} value={createForm.data.code} error={createForm.errors.code}
                                onChange={(e) => createForm.setData('code', e.target.value.toUpperCase())}
                            />
                            <Input
                                label={t('coupons.fields.initial_balance', undefined, { currency: baseCurrencyCode ?? '' })} placeholder="0" value={createForm.data.initial_balance}
                                error={createForm.errors.initial_balance} onChange={(e) => createForm.setData('initial_balance', e.target.value)}
                            />
                            <Input
                                label={t('coupons.fields.valid_until')} type="date" value={createForm.data.expires_at} error={createForm.errors.expires_at}
                                onChange={(e) => createForm.setData('expires_at', e.target.value)}
                            />
                            <Input
                                label={t('coupons.fields.pin')} placeholder={t('coupons.fields.pin_placeholder_none')} value={createForm.data.pin} error={createForm.errors.pin}
                                onChange={(e) => createForm.setData('pin', e.target.value)}
                            />
                            <Input
                                label={t('coupons.fields.customer_account_id')} placeholder={t('coupons.fields.customer_account_id_placeholder')} value={createForm.data.customer_account_id}
                                error={createForm.errors.customer_account_id} onChange={(e) => createForm.setData('customer_account_id', e.target.value)}
                            />
                        </div>
                        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                            <Checkbox label={t('coupons.fields.combinable_with_promotional')} checked={createForm.data.combinable_with_promotional} onChange={(v) => createForm.setData('combinable_with_promotional', v)} />
                            <Checkbox label={t('coupons.fields.combinable_with_other_coupons')} checked={createForm.data.combinable_with_other_coupons} onChange={(v) => createForm.setData('combinable_with_other_coupons', v)} />
                            <Checkbox label={t('coupons.fields.applies_to_delivery')} checked={createForm.data.applies_to_delivery} onChange={(v) => createForm.setData('applies_to_delivery', v)} />
                        </div>
                        <div><Button type="submit" variant="outline" icon={<Plus size={14} />} loading={createForm.processing}>{t('coupons.list.create_submit')}</Button></div>
                    </form>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
