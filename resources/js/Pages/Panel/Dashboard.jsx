import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import PanelSlot from '@/Components/ui/PanelSlot';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    const t = useTranslate();

    return (
        <AuthenticatedLayout header={<PageHeader title={t('app.nav_dashboard')} />}>
            <Head title={t('app.dashboard_head_title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                <Card>
                    <div style={{ fontSize: 14, color: 'var(--text-primary)' }}>
                        {t('app.dashboard_installed_message')}
                    </div>
                </Card>
                <PanelSlot name="dashboard.widgets" />
            </div>
        </AuthenticatedLayout>
    );
}
