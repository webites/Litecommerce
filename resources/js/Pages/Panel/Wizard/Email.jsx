import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

export default function Email({ steps, skippable, storeName, adminEmail }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors } = useForm({
        from_name: storeName,
        from_email: adminEmail,
        smtp_host: '',
        smtp_port: 587,
        smtp_encryption: 'tls',
        smtp_username: '',
        smtp_password: '',
    });
    const field = (name) => ({ id: name, value: data[name], error: errors[name], onChange: (e) => setData(name, e.target.value) });

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="email"
            skippable={skippable}
            title={t('setup.email.title', 'E-mail sending')}
            description={t('setup.email.description', 'Order confirmations, password resets and other messages are sent through your SMTP server. Until it is set up, no e-mails leave the store.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store', { step: 'email' })); }}
            processing={processing}
        >
            <div style={{ display: 'flex', gap: 12 }}>
                <Input style={{ flex: 1 }} label={t('setup.email.from_name_label', 'Sender name')} placeholder={t('setup.email.from_name_example', 'e.g. Acme Store')} required {...field('from_name')} />
                <Input style={{ flex: 1 }} label={t('setup.email.from_email_label', 'Sender e-mail')} placeholder={t('setup.email.from_email_example', 'e.g. shop@example.com')} type="email" required {...field('from_email')} />
            </div>
            <div style={{ display: 'flex', gap: 12 }}>
                <Input style={{ flex: 3 }} label={t('setup.email.host_label', 'SMTP server')} placeholder="smtp.example.com" required {...field('smtp_host')} />
                <Input style={{ flex: 1 }} label={t('setup.email.port_label', 'Port')} type="number" min="1" max="65535" required {...field('smtp_port')} />
            </div>
            <Select id="smtp_encryption" label={t('setup.email.encryption_label', 'Encryption')} value={data.smtp_encryption} error={errors.smtp_encryption} onChange={(e) => setData('smtp_encryption', e.target.value)} options={[
                { value: 'tls', label: 'TLS (STARTTLS)' },
                { value: 'ssl', label: 'SSL' },
                { value: '', label: t('setup.email.encryption_none', 'None') },
            ]} />
            <div style={{ display: 'flex', gap: 12 }}>
                <Input style={{ flex: 1 }} label={t('setup.email.username_label', 'Username')} autoComplete="off" {...field('smtp_username')} />
                <Input style={{ flex: 1 }} label={t('setup.email.password_label', 'Password')} type="password" autoComplete="new-password" {...field('smtp_password')} />
            </div>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    loading={processing}
                    disabled={processing || !data.smtp_host || !data.from_email}
                    onClick={() => post(route('panel.wizard.email.test'), { preserveScroll: true })}
                >
                    {t('setup.email.test_button', 'Send test message')}
                </Button>
            </div>
        </SetupWizardShell>
    );
}
