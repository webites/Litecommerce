import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import PanelSlot from '@/Components/ui/PanelSlot';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head } from '@inertiajs/react';

export default function Dashboard({ messages = [] }) {
    const t = useTranslate();

    return (
        <AuthenticatedLayout header={<PageHeader title={t('app.nav_dashboard')} />}>
            <Head title={t('app.dashboard_head_title')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {messages.length > 0 && (
                    <Card title={t('notifications.title')} description={t('notifications.read_hint')}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {messages.map((message) => (
                                <Alert key={message.key} tone={message.severity} title={message.title}>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
                                        {message.body && <span>{message.body}</span>}
                                        {message.action_url && <Button size="sm" variant="outline" href={message.action_url}>{message.action_label}</Button>}
                                        {message.read && <span style={{ opacity: 0.7 }}>({t('notifications.read')})</span>}
                                    </div>
                                </Alert>
                            ))}
                        </div>
                    </Card>
                )}
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
