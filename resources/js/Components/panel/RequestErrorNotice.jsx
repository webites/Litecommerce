import Alert from '@/Components/ui/Alert';
import { useTranslate } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const REASON_BY_STATUS = { 419: 'session_expired', 429: 'too_many' };

function reasonFor(status) {
    if (REASON_BY_STATUS[status]) return REASON_BY_STATUS[status];

    return status >= 500 ? 'server' : 'generic';
}

/**
 * Explains requests that never produced a panel page (lost connection, expired
 * session, throttling, server errors) instead of Inertia's raw-response modal.
 */
export default function RequestErrorNotice() {
    const t = useTranslate();
    const { debug = false } = usePage().props;
    const [reason, setReason] = useState(null);

    useEffect(() => {
        const removers = [
            router.on('exception', (event) => {
                event.preventDefault();
                setReason('network');
            }),
            router.on('invalid', (event) => {
                const status = event.detail.response.status;
                if (debug && status >= 500) return;
                event.preventDefault();
                setReason(reasonFor(status));
            }),
            router.on('start', () => setReason(null)),
        ];

        return () => removers.forEach((remove) => remove());
    }, [debug]);

    if (reason === null) return null;

    return (
        <div role="alert" style={{ padding: '8px 16px' }}>
            <Alert tone="danger" onDismiss={() => setReason(null)}>
                {t(`app.request_error_${reason}`)}
            </Alert>
        </div>
    );
}
