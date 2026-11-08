import Alert from '@/Components/ui/Alert';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { QrCode } from 'lucide-react';

export default function TwoFactorAuthenticationForm({ twoFactor, className = '' }) {
    const t = useTranslate();
    const confirmForm = useForm({ code: '' });
    const actionForm = useForm({});

    const confirm = (event) => {
        event.preventDefault();
        confirmForm.post(route('profile.two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirmForm.reset('code'),
        });
    };

    return (
        <section className={className} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {!twoFactor.enabled && !twoFactor.pending && (
                <Button variant="solid" disabled={actionForm.processing} onClick={() => actionForm.post(route('profile.two-factor.store'))}>
                    {t('identity.profile.two_factor.enable', 'Enable 2FA')}
                </Button>
            )}

            {twoFactor.pending && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    {twoFactor.setup ? (
                        <>
                            <div
                                style={{
                                    width: 120,
                                    height: 120,
                                    background: 'var(--gray-3)',
                                    borderRadius: 'var(--radius-md)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                }}
                            >
                                <QrCode size={48} color="var(--text-tertiary)" />
                            </div>
                            <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                                {t(
                                    'identity.profile.two_factor.setup_instructions',
                                    'Add the account to your authenticator app using the key below, or open the link on a device with the app.',
                                )}
                            </div>
                            <code
                                style={{
                                    display: 'block',
                                    wordBreak: 'break-all',
                                    background: 'var(--bg-sunken)',
                                    borderRadius: 'var(--radius-md)',
                                    padding: 12,
                                    fontSize: 13,
                                    fontFamily: 'var(--font-mono)',
                                }}
                            >
                                {twoFactor.setup.secret}
                            </code>
                            <a href={twoFactor.setup.otp_auth_uri} style={{ fontSize: 13, color: 'var(--text-link)' }}>
                                {t('identity.profile.two_factor.open_totp_link', 'Open TOTP setup')}
                            </a>
                            <Alert tone="warning" title={t('identity.profile.two_factor.save_recovery_title', 'Save your recovery codes')}>
                                {t(
                                    'identity.profile.two_factor.save_recovery_description',
                                    'Shown only once — use them if you lose access to the app.',
                                )}
                            </Alert>
                            <div
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: '1fr 1fr',
                                    gap: 6,
                                    fontFamily: 'var(--font-mono)',
                                    fontSize: 13,
                                    background: 'var(--bg-sunken)',
                                    borderRadius: 'var(--radius-md)',
                                    padding: 12,
                                }}
                            >
                                {twoFactor.setup.recovery_codes.map((code) => (
                                    <span key={code}>{code}</span>
                                ))}
                            </div>
                        </>
                    ) : (
                        <Alert tone="warning">
                            {t(
                                'identity.profile.two_factor.pending_confirmation',
                                'Setup is awaiting confirmation. Run it again to get a new key and new recovery codes.',
                            )}
                        </Alert>
                    )}

                    <form onSubmit={confirm} style={{ maxWidth: 320, display: 'flex', flexDirection: 'column', gap: 12 }}>
                        <Input
                            id="two_factor_code"
                            label={t('identity.profile.two_factor.code_label', 'Code from the app')}
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={confirmForm.data.code}
                            error={confirmForm.errors.code}
                            onChange={(event) => confirmForm.setData('code', event.target.value)}
                        />
                        <div>
                            <Button type="submit" variant="solid" disabled={confirmForm.processing}>
                                {t('identity.profile.two_factor.confirm_submit', 'Confirm 2FA')}
                            </Button>
                        </div>
                    </form>

                    <div>
                        <Button type="button" variant="ghost" size="sm" onClick={() => actionForm.post(route('profile.two-factor.store'))}>
                            {t('identity.profile.two_factor.regenerate', 'Regenerate setup')}
                        </Button>
                    </div>
                </div>
            )}

            {twoFactor.enabled && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <Alert tone="success">{t('identity.profile.two_factor.enabled_alert', '2FA is enabled.')}</Alert>
                    <div>
                        <Button variant="destructive" disabled={actionForm.processing} onClick={() => actionForm.delete(route('profile.two-factor.destroy'))}>
                            {t('identity.profile.two_factor.disable', 'Disable 2FA')}
                        </Button>
                    </div>
                </div>
            )}
        </section>
    );
}
