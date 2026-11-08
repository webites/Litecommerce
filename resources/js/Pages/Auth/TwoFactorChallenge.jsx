import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslate } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge() {
    const t = useTranslate();
    const { data, setData, post, processing, errors } = useForm({ code: '' });

    const submit = (event) => {
        event.preventDefault();
        post(route('two-factor.challenge'));
    };

    return (
        <GuestLayout width={380}>
            <Head title={t('identity.auth.two_factor_challenge.head_title', 'Two-factor verification')} />

            <div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 18, color: 'var(--text-primary)' }}>
                    {t('identity.auth.two_factor_challenge.heading', 'Two-factor verification')}
                </div>
                <div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 4 }}>
                    {t(
                        'identity.auth.two_factor_challenge.description',
                        'Enter the six-digit code from your authenticator app, or one of your saved recovery codes.',
                    )}
                </div>
            </div>

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="code"
                    label={t('identity.auth.two_factor_challenge.code_label', 'Authentication code')}
                    placeholder="123 456"
                    value={data.code}
                    autoComplete="one-time-code"
                    autoFocus
                    error={errors.code}
                    onChange={(e) => setData('code', e.target.value)}
                />

                <Button type="submit" variant="solid" fullWidth disabled={processing}>
                    {t('identity.auth.two_factor_challenge.submit', 'Confirm')}
                </Button>
            </form>
        </GuestLayout>
    );
}
