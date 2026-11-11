import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import LocaleSwitcher from '@/Components/ui/LocaleSwitcher';
import Wizard from '@/Components/ui/Wizard';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/react';

/** Frame shared by every setup wizard step: progress, language switch, error banner and the footer actions. */
export default function SetupWizardShell({ steps, activeKey, title, description, onSubmit, processing = false, submitLabel, skippable = false, children }) {
    const t = useTranslate();
    const { flash } = usePage().props;
    const keys = [...steps, 'done'];
    const labels = keys.map((key) => t(`setup.steps.${key}`, key));

    return (
        <GuestLayout width={560}>
            <Head title={`${t('setup.head_title', 'Store setup')} — ${t(`setup.steps.${activeKey}`, activeKey)}`} />

            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <LocaleSwitcher />
            </div>

            <Wizard steps={labels} activeIndex={keys.indexOf(activeKey)}>
                <form onSubmit={onSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {title && <div style={{ fontSize: 16, fontWeight: 600, color: 'var(--text-primary)' }}>{title}</div>}
                    {description && <div style={{ fontSize: 14, color: 'var(--text-secondary)' }}>{description}</div>}
                    {flash?.status && <Alert tone="success">{flash.status}</Alert>}
                    {flash?.error && <Alert tone="danger">{flash.error}</Alert>}

                    {children}

                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 }}>
                        {skippable ? (
                            <Button type="button" variant="outline" disabled={processing} onClick={() => router.post(route('panel.wizard.skip', { step: activeKey }))}>
                                {t('setup.skip', 'Skip for now')}
                            </Button>
                        ) : <span />}
                        <Button type="submit" variant="solid" loading={processing} disabled={processing}>
                            {submitLabel ?? t('setup.next', 'Next')}
                        </Button>
                    </div>
                </form>
            </Wizard>
        </GuestLayout>
    );
}
