import Button from '@/Components/ui/Button';
import LocaleSwitcher from '@/Components/ui/LocaleSwitcher';
import Wizard from '@/Components/ui/Wizard';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

export default function Done({ steps }) {
    const t = useTranslate();
    const keys = [...steps, 'done'];

    return (
        <GuestLayout width={560}>
            <Head title={t('setup.done.head_title', 'Setup complete')} />

            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <LocaleSwitcher />
            </div>

            <Wizard steps={keys.map((key) => t(`setup.steps.${key}`, key))} activeIndex={keys.length - 1}>
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10, padding: '12px 0' }}>
                    <CheckCircle2 size={36} color="var(--success-solid)" />
                    <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--text-primary)' }}>{t('setup.done.title', 'Your store is ready')}</div>
                    <div style={{ fontSize: 13, color: 'var(--text-secondary)', textAlign: 'center' }}>{t('setup.done.description', 'Everything you set up here can be changed later in Settings.')}</div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 22 }}>
                    <Button href={route('dashboard')} variant="solid">{t('setup.done.continue', 'Go to the panel')}</Button>
                </div>
            </Wizard>
        </GuestLayout>
    );
}
