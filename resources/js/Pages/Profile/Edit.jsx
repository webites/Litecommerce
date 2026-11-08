import Card from '@/Components/ui/Card';
import PageHeader from '@/Components/ui/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslate } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import TwoFactorAuthenticationForm from './Partials/TwoFactorAuthenticationForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status, twoFactor }) {
    const t = useTranslate();

    return (
        <AuthenticatedLayout header={<PageHeader title={t('identity.profile.page_title', 'Profile')} />}>
            <Head title={t('identity.profile.page_title', 'Profile')} />

            <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 640 }}>
                <Card title={t('identity.profile.card_profile', 'Profile information')}>
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </Card>

                <Card title={t('identity.profile.card_password', 'Password')}>
                    <UpdatePasswordForm />
                </Card>

                <Card title={t('identity.profile.card_two_factor', 'Two-factor verification')}>
                    <TwoFactorAuthenticationForm twoFactor={twoFactor} />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
