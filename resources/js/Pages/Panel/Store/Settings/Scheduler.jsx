import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import Table from '@/Components/ui/Table';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Clock } from 'lucide-react';

const healthTone = { ok: 'success', pending: 'neutral', delayed: 'warning', failing: 'danger', disabled: 'warning' };

const formatDate = (value) => (value ? new Date(value).toLocaleString() : null);

export default function Scheduler({ mode, health, last_run_at: lastRunAt, system_cron_seen_at: cronSeenAt, cron_command: cronCommand, can_configure_cron: canConfigureCron, tasks, embedded = false }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const cronForm = useForm({});
    const configureCron = () => {
        if (window.confirm(t('scheduler.cron_configure_confirm'))) {
            cronForm.post(route('panel.settings.scheduler.cron'), { preserveScroll: true });
        }
    };
    const interval = (seconds) => {
        if (seconds % 3600 === 0) return t('scheduler.every_hours', undefined, { hours: seconds / 3600 });
        if (seconds % 60 === 0) return t('scheduler.every_minutes', undefined, { minutes: seconds / 60 });
        return t('scheduler.every_seconds', undefined, { seconds });
    };
    const state = (task) => {
        if (task.consecutive_failures > 0) return <Badge tone="danger">{t('scheduler.task_failing', undefined, { count: task.consecutive_failures })}</Badge>;
        if (task.status === 'running') return <Badge tone="info">{t('scheduler.task_running')}</Badge>;
        if (task.overdue) return <Badge tone="warning">{t('scheduler.task_overdue')}</Badge>;
        return <Badge tone="success">{t('scheduler.task_ok')}</Badge>;
    };
    const columns = [
        { header: t('scheduler.columns.task'), render: (task) => <span style={{ fontFamily: 'var(--font-mono)', fontSize: 13 }}>{task.key}</span> },
        { header: t('scheduler.columns.interval'), render: (task) => interval(task.interval_seconds) },
        { header: t('scheduler.columns.next_run'), render: (task) => formatDate(task.next_run_at) ?? '–' },
        { header: t('scheduler.columns.last_success'), render: (task) => formatDate(task.last_succeeded_at) ?? '–' },
        { header: t('scheduler.columns.state'), render: state },
    ];

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('scheduler.title')} description={t('scheduler.description')} />}>
            <Head title={t('scheduler.title')} />
            <div style={{ padding: 24, maxWidth: 1100, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                <Card title={t('scheduler.title')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10, fontSize: 13, color: 'var(--text-secondary)' }}>
                        <div>{t('scheduler.mode_label')}: <strong style={{ color: 'var(--text-primary)' }}>{t(`scheduler.modes.${mode}`, mode)}</strong></div>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                            {t('scheduler.health_label')}: <Badge tone={healthTone[health] ?? 'neutral'}>{t(`scheduler.health.${health}`, health)}</Badge>
                        </div>
                        <div>{t('scheduler.last_run')}: {formatDate(lastRunAt) ?? t('scheduler.never')}</div>
                        <div>{t('scheduler.cron_seen')}: {formatDate(cronSeenAt) ?? t('scheduler.never')}</div>
                    </div>
                </Card>
                <Alert tone={mode === 'traffic' && health === 'ok' ? 'info' : 'warning'} title={t('scheduler.limits_title')}>
                    {t(`scheduler.limits.${mode}`)}
                </Alert>
                <Card title={t('scheduler.tasks_title')}>
                    <Table columns={columns} rows={tasks} />
                </Card>
                {mode !== 'system' && (
                    <Card title={t('scheduler.cron_title')} description={t('scheduler.cron_description')}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            <code style={{ display: 'block', padding: 12, borderRadius: 'var(--radius-card)', background: 'var(--gray-2)', fontFamily: 'var(--font-mono)', fontSize: 13, overflowX: 'auto', whiteSpace: 'nowrap' }}>{cronCommand}</code>
                            {cronSeenAt && <div style={{ fontSize: 13, color: 'var(--success-text)' }}>{t('scheduler.cron_switch_hint')}</div>}
                            {!canConfigureCron && <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>{t('scheduler.cron_auto_unavailable')}</div>}
                            {canConfigureCron && (
                                <div>
                                    <Button variant="outline" icon={<Clock size={14} />} loading={cronForm.processing} onClick={configureCron}>{t('scheduler.cron_configure')}</Button>
                                </div>
                            )}
                        </div>
                    </Card>
                )}
            </div>
        </SettingsPageFrame>
    );
}
