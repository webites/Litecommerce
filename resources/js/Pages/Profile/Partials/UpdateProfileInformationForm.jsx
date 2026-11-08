import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import { useTranslate } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status, className = '' }) {
    const t = useTranslate();
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="name"
                    label={t('identity.profile.update_information.name_label', 'Full name')}
                    value={data.name}
                    required
                    autoFocus
                    autoComplete="name"
                    error={errors.name}
                    onChange={(e) => setData('name', e.target.value)}
                />

                <Input
                    id="email"
                    label={t('identity.profile.update_information.email_label', 'Email')}
                    type="email"
                    value={data.email}
                    required
                    autoComplete="username"
                    error={errors.email}
                    onChange={(e) => setData('email', e.target.value)}
                />

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                        {t('identity.profile.update_information.email_unverified', 'Your email address is unverified.')}{' '}
                        <Link href={route('verification.send')} method="post" as="button" style={{ color: 'var(--text-link)' }}>
                            {t('identity.profile.update_information.resend_verification', 'Click here to re-send the verification email.')}
                        </Link>
                        {status === 'verification-link-sent' && (
                            <div style={{ marginTop: 8, color: 'var(--success-text)', fontWeight: 600 }}>
                                {t('identity.profile.update_information.verification_sent', 'A new verification link has been sent.')}
                            </div>
                        )}
                    </div>
                )}

                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <Button type="submit" variant="solid" disabled={processing}>
                        {t('identity.profile.update_information.submit', 'Save')}
                    </Button>

                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <span style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('identity.profile.update_information.saved', 'Saved.')}
                        </span>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
