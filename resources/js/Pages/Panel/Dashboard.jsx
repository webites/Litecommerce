import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import PanelSlot from '@/Components/ui/PanelSlot';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Circle } from 'lucide-react';

export default function Dashboard({ messages = [], setup_steps: setupSteps = [] }) {
    const t = useTranslate();
    const pending = setupSteps.filter((step) => !step.done).length;

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
                {pending > 0 && (
                    <Card title={t('app.setup_title', 'Start selling')} description={t('app.setup_description', 'Finish these steps so customers can place orders.')}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {setupSteps.map((step) => (
                                <div key={step.key} style={{ display: 'flex', alignItems: 'flex-start', gap: 12, flexWrap: 'wrap' }}>
                                    {step.done ? <CheckCircle2 size={20} color="var(--success-text)" /> : <Circle size={20} color="var(--text-tertiary)" />}
                                    <div style={{ flex: '1 1 260px', minWidth: 0 }}>
                                        <div style={{ fontWeight: 600, fontSize: 14, color: step.done ? 'var(--text-tertiary)' : 'var(--text-primary)', textDecoration: step.done ? 'line-through' : 'none' }}>{step.title}</div>
                                        {!step.done && <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 2 }}>{step.description}</div>}
                                    </div>
                                    {!step.done && <Button size="sm" variant="outline" href={step.url}>{t('app.setup_open', 'Set up')}</Button>}
                                </div>
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
