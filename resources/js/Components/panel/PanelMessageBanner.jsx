import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import { useTranslate } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';

export default function PanelMessageBanner({ banner }) {
    const t = useTranslate();

    if (!banner) {
        return null;
    }

    const { message, more } = banner;
    const dismiss = () => router.post(route('panel.messages.read'), { key: message.key }, { preserveScroll: true, preserveState: true });
    const markRead = () => axios.post(route('panel.messages.read'), { key: message.key }).catch(() => {});

    return (
        <div style={{ padding: '8px 16px' }}>
            <Alert tone={message.severity} title={message.title} onDismiss={dismiss}>
                <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
                    {message.body && <span>{message.body}</span>}
                    {message.action_url && (
                        <Button size="sm" variant="outline" href={message.action_url} onClick={markRead}>{message.action_label}</Button>
                    )}
                    {more > 0 && <Link href={route('dashboard')} style={{ color: 'inherit', textDecoration: 'underline' }}>{t('notifications.more', undefined, { count: more })}</Link>}
                </div>
            </Alert>
        </div>
    );
}
