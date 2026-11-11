import Alert from '@/Components/ui/Alert';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import Switch from '@/Components/ui/Switch';
import SettingsPageFrame from '@/Components/panel/SettingsPageFrame';
import { useTranslate } from '@/lib/i18n';
import Modal from '@/Components/ui/Modal';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Circle, Download, Loader2, RefreshCw, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatDateTime } from '@/lib/format';


const stepIcon = {
    done: <CheckCircle2 size={16} style={{ color: 'var(--success-text)' }} />,
    running: <Loader2 size={16} className="animate-spin" style={{ color: 'var(--brand-solid)' }} />,
    failed: <XCircle size={16} style={{ color: 'var(--danger-text)' }} />,
    pending: <Circle size={16} style={{ color: 'var(--text-tertiary)' }} />,
};

function useOperation(initial) {
    const [operation, setOperation] = useState(initial);
    const [connectionLost, setConnectionLost] = useState(false);

    useEffect(() => setOperation(initial), [initial?.id, initial?.status]);

    useEffect(() => {
        if (operation?.status !== 'running') {
            return undefined;
        }

        let cancelled = false;
        const advance = async () => {
            try {
                const { data } = await axios.post(route('panel.settings.updates.advance'));
                if (cancelled) return;
                setConnectionLost(false);
                setOperation(data);
            } catch {
                if (cancelled) return;
                setConnectionLost(true);
                setTimeout(() => !cancelled && setOperation((current) => ({ ...current })), 3000);
            }
        };
        advance();

        return () => {
            cancelled = true;
        };
    }, [operation?.status, operation?.steps && Object.values(operation.steps).join(',')]);

    return [operation, connectionLost];
}

export default function Index({ current_version: currentVersion, automatic_checks: automaticChecks, checked_at: checkedAt, error, release, problems, operation: initialOperation, embedded = false }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const [operation, connectionLost] = useOperation(initialOperation);
    const [confirming, setConfirming] = useState(false);
    const checkForm = useForm({});
    const installForm = useForm({});
    const install = () => installForm.post(route('panel.settings.updates.install'), { preserveScroll: true, onSuccess: () => setConfirming(false) });
    const finished = operation?.status === 'completed';

    useEffect(() => {
        if (finished) {
            window.location.reload();
        }
    }, [finished]);

    const check = () => checkForm.post(route('panel.settings.updates.check'), { preserveScroll: true });
    const toggleAutomatic = (enabled) => router.put(route('panel.settings.updates.automatic'), { enabled }, { preserveScroll: true });

    return (
        <SettingsPageFrame embedded={embedded} header={<PageHeader title={t('updates.title')} description={t('updates.description')} />}>
            <Head title={t('updates.title')} />
            <div style={{ padding: 24, maxWidth: 900, display: 'flex', flexDirection: 'column', gap: 20 }}>
                {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
                {error && !flash?.error && <Alert tone="danger">{error}</Alert>}
                <Card title={t('updates.title')}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, fontSize: 13, color: 'var(--text-secondary)' }}>
                        <div>{t('updates.current_version')}: <strong style={{ color: 'var(--text-primary)' }}>{currentVersion}</strong></div>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                            {release ? (
                                <>
                                    {t('updates.latest')}: <strong style={{ color: 'var(--text-primary)' }}>{release.version}</strong>
                                    {release.notes_url && <a href={release.notes_url} target="_blank" rel="noreferrer noopener">{t('updates.release_notes')}</a>}
                                </>
                            ) : (
                                <Badge tone="success">{t('updates.up_to_date')}</Badge>
                            )}
                        </div>
                        <div>{t('updates.checked_at')}: {formatDateTime(checkedAt, null) ?? t('updates.never')}</div>
                        <div>
                            <Button variant="outline" icon={<RefreshCw size={14} />} loading={checkForm.processing} onClick={check}>{t('updates.check_now')}</Button>
                        </div>
                    </div>
                </Card>
                {operation && (
                    <Card title={t('updates.progress_title', undefined, { version: operation.target_version })}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {operation.status === 'running' && <Alert tone="warning">{t('updates.progress_running')}</Alert>}
                            {operation.status === 'running' && connectionLost && <Alert tone="danger">{t('updates.connection_lost')}</Alert>}
                            {operation.status === 'rolled_back' && <Alert tone="warning" title={operation.error}>{t('updates.failed_rolled_back')}</Alert>}
                            {operation.status === 'failed' && <Alert tone="danger" title={operation.error}>{t('updates.failed_in_maintenance', undefined, { id: operation.id })}</Alert>}
                            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
                                {Object.entries(operation.steps).map(([key, status]) => (
                                    <li key={key} style={{ display: 'flex', gap: 8, alignItems: 'center', color: status === 'pending' ? 'var(--text-tertiary)' : 'var(--text-primary)' }}>
                                        {stepIcon[status] ?? stepIcon.pending}
                                        {t(`updates.step_${key}`, key)}
                                    </li>
                                ))}
                            </ul>
                            {operation.detail && operation.status !== 'running' && (
                                <code style={{ fontSize: 12, color: 'var(--text-secondary)', wordBreak: 'break-word' }}>{operation.detail}</code>
                            )}
                        </div>
                    </Card>
                )}
                {release && (
                    <Card title={t('updates.compatibility_title')}>
                        {problems.length === 0 ? (
                            <Alert tone="success">{t('updates.compatible')}</Alert>
                        ) : (
                            <Alert tone="warning" title={t('updates.incompatible')}>
                                <ul style={{ margin: 0, paddingLeft: 18 }}>
                                    {problems.map((problem, index) => (
                                        <li key={index}>{t(`updates.problem_${problem.code}`, problem.code, problem.params)}</li>
                                    ))}
                                </ul>
                            </Alert>
                        )}
                        {problems.length === 0 && operation?.status !== 'running' && (
                            <div style={{ marginTop: 16 }}>
                                <Button icon={<Download size={14} />} onClick={() => setConfirming(true)}>{t('updates.install_button', undefined, { version: release.version })}</Button>
                            </div>
                        )}
                    </Card>
                )}
                <Modal show={confirming} title={t('updates.install_confirm_title', undefined, { version: release?.version })} onClose={() => setConfirming(false)}>
                    <p style={{ margin: '0 0 16px', fontSize: 13, color: 'var(--text-secondary)' }}>{t('updates.install_confirm_body')}</p>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <Button variant="outline" onClick={() => setConfirming(false)}>{t('updates.cancel')}</Button>
                        <Button loading={installForm.processing} onClick={install}>{t('updates.install_confirm_action')}</Button>
                    </div>
                </Modal>
                <Card title={t('updates.automatic_title')} description={t('updates.automatic_description')}>
                    <Switch checked={automaticChecks} onChange={toggleAutomatic} label={t('updates.automatic_label')} />
                </Card>
            </div>
        </SettingsPageFrame>
    );
}
