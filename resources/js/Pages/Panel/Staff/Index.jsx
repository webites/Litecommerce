import Badge from '@/Components/ui/Badge';
import Card from '@/Components/ui/Card';
import Pagination from '@/Components/ui/Pagination';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import StaffForm from './StaffForm';

export default function Index({ staffAccounts, roles, permissions }) {
    const t = useTranslate();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: roles[0].value,
        permissions: Object.fromEntries(permissions.map(({ key }) => [key, 'inherit'])),
    });

    const submit = (event) => {
        event.preventDefault();
        form.post(route('panel.staff.store'));
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('identity.staff.page_title')} />}>
            <Head title={t('identity.staff.page_title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                <Card title={t('identity.staff.card_accounts')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <Table
                            columns={[
                                {
                                    header: t('identity.staff.column_staff'),
                                    render: (r) => (
                                        <div>
                                            <div style={{ fontWeight: 600 }}>{r.name}</div>
                                            <div style={{ color: 'var(--text-secondary)', fontSize: 13 }}>{r.email}</div>
                                        </div>
                                    ),
                                },
                                { header: t('identity.staff.column_role'), render: (r) => roles.find(({ value }) => value === r.role)?.label },
                                {
                                    header: t('identity.staff.column_status'),
                                    render: (r) => (
                                        <Badge tone={r.is_active ? 'success' : 'neutral'} dot>
                                            {r.is_active ? t('identity.staff.status_active') : t('identity.staff.status_inactive')}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: '',
                                    align: 'right',
                                    render: (r) => (
                                        <Link href={route('panel.staff.edit', r.id)} style={{ color: 'var(--text-link)', fontSize: 13, fontWeight: 600 }}>
                                            {t('identity.staff.edit')}
                                        </Link>
                                    ),
                                },
                            ]}
                            rows={staffAccounts.data}
                        />
                        <Pagination paginator={staffAccounts} />
                    </div>
                </Card>

                <Card title={t('identity.staff.card_add')}>
                    <StaffForm form={form} roles={roles} permissions={permissions} onSubmit={submit} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
