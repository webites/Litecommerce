import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout width={380}>
            <Head title={t('identity.auth.reset_password.head_title', 'Set a new password')} />

            <div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 18, color: 'var(--text-primary)' }}>
                    {t('identity.auth.reset_password.heading', 'Set a new password')}
                </div>
            </div>

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="email"
                    label={t('identity.auth.reset_password.email_label', 'Email')}
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <Input
                    id="password"
                    label={t('identity.auth.reset_password.password_label', 'Password')}
                    type="password"
                    name="password"
                    placeholder="••••••••"
                    value={data.password}
                    autoComplete="new-password"
                    autoFocus
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <Input
                    id="password_confirmation"
                    label={t('identity.auth.reset_password.password_confirmation_label', 'Confirm password')}
                    type="password"
                    name="password_confirmation"
                    placeholder="••••••••"
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    error={errors.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />

                <Button type="submit" variant="solid" fullWidth disabled={processing}>
                    {t('identity.auth.reset_password.submit', 'Save new password')}
                </Button>
            </form>
        </GuestLayout>
    );
}
