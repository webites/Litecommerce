import Input from '@/Components/ui/Input';
import SetupWizardShell from '@/Components/panel/SetupWizardShell';
import { useTranslate } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

export default function Profile({ steps, skippable }) {
    const t = useTranslate();
    const { data, setData, post, processing, errors } = useForm({ store_name: '' });

    return (
        <SetupWizardShell
            steps={steps}
            activeKey="profile"
            skippable={skippable}
            title={t('setup.profile.title', 'Your store')}
            description={t('setup.profile.description', 'The name shown in the storefront header, page titles and e-mails.')}
            onSubmit={(e) => { e.preventDefault(); post(route('panel.wizard.store')); }}
            processing={processing}
        >
            <Input id="store_name" label={t('setup.profile.name_label', 'Store name')} placeholder={t('setup.profile.name_example', 'e.g. Acme Store')} value={data.store_name} required autoFocus error={errors.store_name} onChange={(e) => setData('store_name', e.target.value)} />
        </SetupWizardShell>
    );
}
