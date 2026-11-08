import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout width={380}>
            <Head title={t('identity.auth.forgot_password.head_title', 'Forgot password')} />

            <div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 18, color: 'var(--text-primary)' }}>
                    {t('identity.auth.forgot_password.heading', 'Reset your password')}
                </div>
                <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 4 }}>
                    {t(
                        'identity.auth.forgot_password.description',
                        "Enter your account's email address — we'll send you a link to set a new password.",
                    )}
                </div>
            </div>

            {status && <Alert tone="success">{status}</Alert>}

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="email"
                    label={t('identity.auth.forgot_password.email_label', 'Email')}
                    type="email"
                    name="email"
                    placeholder="ty@sklep.pl"
                    value={data.email}
                    autoComplete="username"
                    autoFocus
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <Button type="submit" variant="solid" fullWidth disabled={processing}>
                    {t('identity.auth.forgot_password.submit', 'Send reset link')}
                </Button>
            </form>
        </GuestLayout>
    );
}
