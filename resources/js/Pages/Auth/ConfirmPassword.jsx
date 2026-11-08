import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const t = useTranslate();
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout width={380}>
            <Head title={t('identity.auth.confirm_password.head_title', 'Confirm password')} />

            <div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 18, color: 'var(--text-primary)' }}>
                    {t('identity.auth.confirm_password.heading', 'Confirm password')}
                </div>
                <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 4 }}>
                    {t(
                        'identity.auth.confirm_password.description',
                        'This is a secure area of the panel. Confirm your password to continue.',
                    )}
                </div>
            </div>

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="password"
                    label={t('identity.auth.confirm_password.password_label', 'Password')}
                    type="password"
                    name="password"
                    placeholder="••••••••"
                    value={data.password}
                    autoComplete="current-password"
                    autoFocus
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <Button type="submit" variant="solid" fullWidth disabled={processing}>
                    {t('identity.auth.confirm_password.submit', 'Confirm')}
                </Button>
            </form>
        </GuestLayout>
    );
}
