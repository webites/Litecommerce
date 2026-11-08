import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import StaffForm from './StaffForm';

export default function Edit({ staffAccount, roles, permissions }) {
    const t = useTranslate();
    const form = useForm({
        name: staffAccount.name,
        email: staffAccount.email,
        password: '',
        role: staffAccount.role,
        is_active: staffAccount.is_active,
        permissions: Object.fromEntries(
            permissions.map(({ key }) => [
                key,
                key in staffAccount.permission_overrides
                    ? (staffAccount.permission_overrides[key] ? 'allow' : 'deny')
                    : 'inherit',
            ]),
        ),
    });

    const submit = (event) => {
        event.preventDefault();
        form.put(route('panel.staff.update', staffAccount.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title={t('identity.staff.edit_page_title')} />}>
            <Head title={t('identity.staff.head_title', 'Staff member: :name', { name: staffAccount.name })} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 800 }}>
                <Link
                    href={route('panel.staff.index')}
                    style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 13, color: 'var(--text-link)' }}
                >
                    <ArrowLeft size={14} /> {t('identity.staff.back_to_staff')}
                </Link>
                <Card>
                    <StaffForm form={form} roles={roles} permissions={permissions} editing onSubmit={submit} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
