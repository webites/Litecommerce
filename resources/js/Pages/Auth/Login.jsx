import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout width={380}>
            <Head title={t('identity.auth.login.head_title', 'Sign in')} />

            <div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 18, color: 'var(--text-primary)' }}>
                    {t('identity.auth.login.heading', 'Sign in to the panel')}
                </div>
                <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 4 }}>
                    {t('identity.auth.login.subtitle', 'Staff access only')}
                </div>
            </div>

            {status && (
                <Alert tone="success">{status}</Alert>
            )}

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="email"
                    label={t('identity.auth.login.email_label', 'Email')}
                    type="email"
                    name="email"
                    placeholder="ty@sklep.pl"
                    value={data.email}
                    autoComplete="username"
                    autoFocus
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <Input
                    id="password"
                    label={t('identity.auth.login.password_label', 'Password')}
                    type="password"
                    name="password"
                    placeholder="••••••••"
                    value={data.password}
                    autoComplete="current-password"
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Checkbox
                        label={t('identity.auth.login.remember_label', 'Remember me')}
                        checked={data.remember}
                        onChange={(checked) => setData('remember', checked)}
                    />
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            style={{ fontSize: 13, color: 'var(--text-link)' }}
                        >
                            {t('identity.auth.login.forgot_link', 'Forgot your password?')}
                        </Link>
                    )}
                </div>

                <Button type="submit" variant="solid" fullWidth disabled={processing}>
                    {t('identity.auth.login.submit', 'Sign in')}
                </Button>
            </form>
        </GuestLayout>
    );
}
