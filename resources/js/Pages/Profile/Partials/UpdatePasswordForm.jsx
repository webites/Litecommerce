import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import { useTranslate } from '@/lib/i18n';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm({ className = '' }) {
    const t = useTranslate();
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <form onSubmit={updatePassword} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                <Input
                    id="current_password"
                    ref={currentPasswordInput}
                    label={t('identity.profile.update_password.current_password_label', 'Current password')}
                    type="password"
                    value={data.current_password}
                    autoComplete="current-password"
                    error={errors.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                />

                <Input
                    id="password"
                    ref={passwordInput}
                    label={t('identity.profile.update_password.new_password_label', 'New password')}
                    type="password"
                    value={data.password}
                    autoComplete="new-password"
                    error={errors.password}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <Input
                    id="password_confirmation"
                    label={t('identity.profile.update_password.confirm_password_label', 'Confirm password')}
                    type="password"
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    error={errors.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />

                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <Button type="submit" variant="solid" disabled={processing}>
                        {t('identity.profile.update_password.submit', 'Save')}
                    </Button>

                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <span style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                            {t('identity.profile.update_password.saved', 'Saved.')}
                        </span>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
